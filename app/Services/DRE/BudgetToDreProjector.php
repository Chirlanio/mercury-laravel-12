<?php

namespace App\Services\DRE;

use App\Enums\AccountType;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\ChartOfAccount;
use App\Models\DreBudget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ponte `BudgetUpload` (Budgets UI) → `dre_budgets` (DRE).
 *
 * Quando um `BudgetUpload.is_active` vira `true`, esta classe explode os 12
 * valores mensais de cada `BudgetItem` em até 12 linhas em `dre_budgets`,
 * preservando FK `budget_upload_id` para reprojeção futura.
 *
 * Semântica do "superseding":
 *   - Antes de projetar, apaga `dre_budgets` de qualquer upload anterior no
 *     mesmo (year, scope_label). Uma versão ativa por escopo é a regra do
 *     módulo Budgets — espelhamos isso em dre_budgets.
 *
 * Convenção de sinal idêntica a actuals: grupo 3 positivo, 4/5 negativo,
 * 1/2 loga warning e pula (orçamento não deveria usar contas patrimoniais).
 *
 * Observer em `BudgetUpload` chama `project()` quando o flag `is_active`
 * transiciona para true (fluxo síncrono simples; jobs async são iteração
 * futura quando volumes exigirem).
 */
class BudgetToDreProjector
{
    /**
     * Projeta as linhas do upload em `dre_budgets`. Idempotente — pode rodar
     * várias vezes para o mesmo upload e produz o mesmo resultado.
     *
     * Só projeta se `is_active=true`. Caso contrário retorna skip.
     */
    public function project(BudgetUpload $upload): ProjectReport
    {
        $report = new ProjectReport();

        if (! $upload->is_active) {
            $report->skippedInactive = true;

            return $report;
        }

        // Pré-carrega account_group de todas as contas referenciadas no upload.
        $accountIds = BudgetItem::query()
            ->where('budget_upload_id', $upload->id)
            ->pluck('accounting_class_id')
            ->unique()
            ->all();

        $accounts = ChartOfAccount::query()
            ->whereIn('id', $accountIds)
            ->get(['id', 'code', 'type', 'account_group'])
            ->keyBy('id')
            ->all();

        DB::transaction(function () use ($upload, $accounts, $report) {
            // 1. Remove dre_budgets de uploads anteriores no mesmo escopo.
            $report->deletedFromPrevious = DreBudget::query()
                ->whereIn('budget_upload_id', function ($q) use ($upload) {
                    $q->select('id')
                        ->from('budget_uploads')
                        ->where('year', $upload->year)
                        ->where('scope_label', $upload->scope_label)
                        ->where('id', '!=', $upload->id)
                        ->whereNull('deleted_at');
                })
                ->delete();

            // 2. Remove linhas antigas deste próprio upload (reproject idempotente).
            $report->deletedFromSelf = DreBudget::query()
                ->where('budget_upload_id', $upload->id)
                ->delete();

            // 3. Projeta cada item em N linhas mensais.
            $version = (string) $upload->version_label;
            $now = Carbon::now();
            $buffer = [];

            BudgetItem::query()
                ->where('budget_upload_id', $upload->id)
                ->orderBy('id')
                ->chunk(500, function ($items) use (
                    $upload,
                    $accounts,
                    $version,
                    $now,
                    &$buffer,
                    $report,
                ) {
                    foreach ($items as $item) {
                        $account = $accounts[$item->accounting_class_id] ?? null;

                        if (! $account) {
                            $report->skippedAccountMissing++;
                            continue;
                        }

                        if (($account->type instanceof AccountType ? $account->type->value : $account->type)
                            !== AccountType::ANALYTICAL->value
                        ) {
                            Log::warning('BudgetToDreProjector: conta sintética em BudgetItem', [
                                'budget_item_id' => $item->id,
                                'account_code' => $account->code,
                            ]);
                            $report->skippedSynthetic++;
                            continue;
                        }

                        $group = $account->account_group instanceof \BackedEnum
                            ? (int) $account->account_group->value
                            : (int) $account->account_group;

                        $rows = $this->explodeMonthly($item, $upload, $version, $group, $now, $report);
                        foreach ($rows as $row) {
                            $buffer[] = $row;
                            if (count($buffer) >= 500) {
                                DreBudget::insert($buffer);
                                $report->projected += count($buffer);
                                $buffer = [];
                            }
                        }
                    }
                });

            if ($buffer !== []) {
                DreBudget::insert($buffer);
                $report->projected += count($buffer);
            }
        });

        return $report;
    }

    /**
     * Remove toda projeção deste upload (usado quando is_active vira false
     * ou o upload é deletado).
     */
    public function unproject(BudgetUpload $upload): int
    {
        return DreBudget::query()
            ->where('budget_upload_id', $upload->id)
            ->delete();
    }

    /**
     * Explode um BudgetItem em até 12 linhas (uma por mês não-zero). Converte
     * o sinal conforme `account_group`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function explodeMonthly(
        BudgetItem $item,
        BudgetUpload $upload,
        string $version,
        int $accountGroup,
        Carbon $now,
        ProjectReport $report,
    ): array {
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';
            $raw = (float) $item->{$col};
            if ($raw == 0.0) {
                continue;
            }

            $signed = $this->convertSign($raw, $accountGroup);
            if ($signed === null) {
                $report->skippedNonResultGroup++;
                continue;
            }

            $out[] = [
                'entry_date' => sprintf('%04d-%02d-01', $upload->year, $m),
                'chart_of_account_id' => (int) $item->accounting_class_id,
                'cost_center_id' => $item->cost_center_id,
                'store_id' => $item->store_id,
                'amount' => round($signed, 2),
                'budget_version' => $version,
                'budget_upload_id' => (int) $upload->id,
                'notes' => $this->truncate($item->justification ?? null, 500),
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $out;
    }

    public function convertSign(float $rawAmount, int $accountGroup): ?float
    {
        $abs = abs($rawAmount);

        return match (true) {
            $accountGroup === 3 => +$abs,
            $accountGroup === 4, $accountGroup === 5 => -$abs,
            default => null,
        };
    }

    private function truncate(?string $text, int $limit): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return strlen($text) <= $limit ? $text : substr($text, 0, $limit - 3).'...';
    }
}
