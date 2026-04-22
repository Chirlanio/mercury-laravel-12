<?php

namespace App\Services\DRE;

use App\Enums\AccountType;
use App\Imports\DRE\ActionPlanImport;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreBudget;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa o `Action Plan v1.xlsx` (primeiro orçamento 2026 do Grupo Meia
 * Sola) para `dre_budgets`.
 *
 * Diferenças em relação ao `DreBudgetsImporter` genérico:
 *   - Layout fixo da planilha do CFO (9 colunas), sem cabeçalho customizável.
 *   - Resolve lojas e centros de custo pelo **mesmo código numérico** (ex: 421).
 *     Stores usam o código com prefixo Z ("Z421"); CostCenters usam o puro ("421").
 *   - Idempotente via upsert por chave composta `(entry_date,
 *     chart_of_account_id, cost_center_id, store_id, budget_version)` — rodar
 *     2x o mesmo arquivo não duplica; rodar com `--version` diferente cria
 *     linhas paralelas (coexistência de versões por design).
 *
 * Cabe em RAM: 3861 linhas + lookups preloadados. Uma transação só.
 */
class ActionPlanImporter
{
    public function import(
        string $filePath,
        string $budgetVersionLabel = 'action_plan_v1',
        bool $dryRun = false,
    ): ActionPlanReport {
        $report = new ActionPlanReport();
        $report->dryRun = $dryRun;
        $report->budgetVersion = $budgetVersionLabel;

        if (trim($budgetVersionLabel) === '') {
            $report->errors[] = 'budget_version é obrigatório.';

            return $report;
        }

        $reader = new ActionPlanImport();
        Excel::import($reader, $filePath);
        $report->totalRead = $reader->totalRead;

        if ($reader->totalRead === 0) {
            return $report;
        }

        $lookups = $this->preloadLookups();

        if ($dryRun) {
            foreach ($reader->rows as $row) {
                $this->validateAndBuild($row, $lookups, $budgetVersionLabel, $report);
            }

            return $report;
        }

        DB::transaction(function () use ($reader, $lookups, $budgetVersionLabel, $report) {
            foreach ($reader->rows as $row) {
                $payload = $this->validateAndBuild($row, $lookups, $budgetVersionLabel, $report);
                if ($payload === null) {
                    continue;
                }

                // Upsert por chave composta. updateOrCreate usa o mínimo de
                // queries (1 SELECT + 1 INSERT/UPDATE) e mantém a semântica
                // idempotente exigida pela spec.
                $existing = DreBudget::query()
                    ->where('entry_date', $payload['entry_date'])
                    ->where('chart_of_account_id', $payload['chart_of_account_id'])
                    ->where('cost_center_id', $payload['cost_center_id'])
                    ->where('store_id', $payload['store_id'])
                    ->where('budget_version', $payload['budget_version'])
                    ->first();

                if ($existing) {
                    $existing->fill([
                        'amount' => $payload['amount'],
                        'notes' => $payload['notes'],
                        'updated_at' => $payload['updated_at'],
                    ])->save();
                    $report->updated++;
                } else {
                    DreBudget::create($payload);
                    $report->inserted++;
                }
            }
        });

        return $report;
    }

    private function validateAndBuild(
        array $row,
        array $lookups,
        string $budgetVersion,
        ActionPlanReport $report,
    ): ?array {
        $fileRow = (int) $row['_row'];

        // Month/year ------------------------------------------------------
        $month = $row['month'];
        $year = $row['year'];
        if (! is_numeric($month) || ! is_numeric($year)) {
            $report->addError($fileRow, 'mês/ano inválido ou ausente.');

            return null;
        }

        $month = (int) $month;
        $year = (int) $year;
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $report->addError($fileRow, "mês/ano fora do intervalo esperado (mês=1..12, ano=2000..2100) — recebido {$month}/{$year}.");

            return null;
        }

        $entryDate = sprintf('%04d-%02d-01', $year, $month);

        // Account ---------------------------------------------------------
        $accountCode = (string) ($row['account_code'] ?? '');
        if ($accountCode === '') {
            $report->addError($fileRow, 'class contabil ausente.');

            return null;
        }

        $account = $lookups['accounts'][$accountCode] ?? null;
        if ($account === null) {
            $report->addError($fileRow, "conta contábil '{$accountCode}' não encontrada no plano.");

            return null;
        }

        if ($account['type'] !== AccountType::ANALYTICAL->value) {
            $report->addError($fileRow, "conta '{$accountCode}' é sintética — só analíticas aceitam lançamento.");

            return null;
        }

        // Amount ----------------------------------------------------------
        $rawAmount = $row['amount'];
        if ($rawAmount === null || $rawAmount === '' || ! is_numeric($rawAmount)) {
            $report->addError($fileRow, 'valor inválido ou ausente.');

            return null;
        }

        $signed = $this->convertSign((float) $rawAmount, (int) $account['account_group']);
        if ($signed === null) {
            $report->addError(
                $fileRow,
                "conta '{$accountCode}' pertence ao grupo {$account['account_group']} (Ativo/Passivo) e não pode entrar na DRE."
            );

            return null;
        }

        // Store + CC — o código numérico ("421") vira store Z421 + CC 421.
        // Qualquer um dos dois pode faltar (histórico com rede consolidada
        // no código 0, por exemplo) — o importador aceita null.
        $ccCode = (string) ($row['cc_code'] ?? '');
        [$storeId, $costCenterId] = $this->resolveStoreAndCc($ccCode, $lookups);

        $now = Carbon::now();

        return [
            'entry_date' => $entryDate,
            'chart_of_account_id' => (int) $account['id'],
            'cost_center_id' => $costCenterId,
            'store_id' => $storeId,
            'amount' => round($signed, 2),
            'budget_version' => $budgetVersion,
            'budget_upload_id' => null,
            'notes' => $this->truncate((string) ($row['description'] ?? ''), 500),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Dado o código "421" tenta resolver:
     *   - Store por Z421 (prefixo Z) OU 421 (fallback).
     *   - CostCenter pelo código puro "421".
     *
     * Devolve `[null, null]` quando nenhum resolve — nessas linhas o budget
     * vai consolidado (sem loja/CC).
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveStoreAndCc(string $code, array $lookups): array
    {
        if ($code === '' || $code === '0') {
            return [null, null];
        }

        $storeId = $lookups['stores']['Z'.$code] ?? $lookups['stores'][$code] ?? null;
        $costCenterId = $lookups['cost_centers'][$code] ?? null;

        return [$storeId, $costCenterId];
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

    private function preloadLookups(): array
    {
        $stores = Store::query()
            ->get(['id', 'code'])
            ->keyBy('code')
            ->map(fn ($s) => (int) $s->id)
            ->all();

        $accounts = ChartOfAccount::query()
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'type', 'account_group'])
            ->keyBy('code')
            ->map(fn ($a) => [
                'id' => (int) $a->id,
                'type' => $a->type instanceof AccountType ? $a->type->value : (string) $a->type,
                'account_group' => $a->account_group instanceof \BackedEnum
                    ? (int) $a->account_group->value
                    : (int) $a->account_group,
            ])
            ->all();

        $costCenters = CostCenter::query()
            ->whereNull('deleted_at')
            ->get(['id', 'code'])
            ->keyBy('code')
            ->map(fn ($cc) => (int) $cc->id)
            ->all();

        return [
            'stores' => $stores,
            'accounts' => $accounts,
            'cost_centers' => $costCenters,
        ];
    }

    private function truncate(?string $text, int $limit): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return strlen($text) <= $limit ? $text : substr($text, 0, $limit - 3).'...';
    }
}
