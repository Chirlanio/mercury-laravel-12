<?php

namespace App\Services\DRE;

use App\Enums\AccountType;
use App\Imports\DRE\DreBudgetsImport;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreBudget;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa orçado manual para `dre_budgets`.
 *
 * Mesma mecânica de `DreActualsImporter` (pré-carga de lookups, validação
 * PT-BR, chunks). Diferenças:
 *   - `store_code` é opcional (budget pode ser consolidado por rede).
 *   - `budget_version` vem do parâmetro, não do XLSX.
 *   - Não checa fechamento — orçado é prospectivo.
 *   - Sem `external_id`/dedup — uma versão do orçamento é imutável; reimportar
 *     exige `budget_version` diferente.
 *   - `entry_date` é normalizada para o dia 1 do mês (convenção da tabela).
 *
 * Convenção de sinal idêntica à de actuals (grupo 3 positivo, 4/5 negativo).
 */
class DreBudgetsImporter
{
    public function import(string $filePath, string $budgetVersion, bool $dryRun = false): DreImportReport
    {
        $report = new DreImportReport();
        $report->dryRun = $dryRun;
        $report->budgetVersion = $budgetVersion;

        $budgetVersion = trim($budgetVersion);
        if ($budgetVersion === '') {
            $report->errors[] = 'budget_version é obrigatório.';

            return $report;
        }

        $reader = new DreBudgetsImport();
        Excel::import($reader, $filePath);
        $report->totalRead = $reader->totalRead;

        if ($reader->totalRead === 0) {
            return $report;
        }

        $lookups = $this->preloadLookups();

        if ($dryRun) {
            foreach ($reader->rows as $row) {
                $this->validateAndBuild($row, $lookups, $budgetVersion, $report);
            }

            return $report;
        }

        DB::transaction(function () use ($reader, $lookups, $budgetVersion, $report) {
            $buffer = [];
            foreach ($reader->rows as $row) {
                $payload = $this->validateAndBuild($row, $lookups, $budgetVersion, $report);
                if ($payload === null) {
                    continue;
                }

                $buffer[] = $payload;
                if (count($buffer) >= 500) {
                    DreBudget::insert($buffer);
                    $report->created += count($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DreBudget::insert($buffer);
                $report->created += count($buffer);
            }
        });

        return $report;
    }

    private function validateAndBuild(
        array $row,
        array $lookups,
        string $budgetVersion,
        DreImportReport $report,
    ): ?array {
        $fileRow = (int) $row['_row'];

        $entryDate = $this->parseMonthDate($row['entry_date']);
        if ($entryDate === null) {
            $report->addError($fileRow, "entry_date inválida ou ausente.");

            return null;
        }

        // store é opcional
        $storeId = null;
        $storeCode = $row['store_code'] ?? null;
        if ($storeCode !== null && $storeCode !== '') {
            $storeId = $lookups['stores'][$storeCode] ?? null;
            if ($storeId === null) {
                $report->addError($fileRow, "loja '{$storeCode}' não encontrada.");

                return null;
            }
        }

        $accountCode = (string) ($row['account_code'] ?? '');
        if ($accountCode === '') {
            $report->addError($fileRow, "account_code obrigatório.");

            return null;
        }

        $account = $lookups['accounts'][$accountCode] ?? null;
        if ($account === null) {
            $report->addError($fileRow, "conta '{$accountCode}' não encontrada no plano.");

            return null;
        }

        if ($account['type'] !== AccountType::ANALYTICAL->value) {
            $report->addError($fileRow, "conta '{$accountCode}' é sintética — só analíticas aceitam lançamento.");

            return null;
        }

        $costCenterId = null;
        $costCenterCode = $row['cost_center_code'] ?? null;
        if ($costCenterCode !== null && $costCenterCode !== '') {
            $costCenterId = $lookups['cost_centers'][$costCenterCode] ?? null;
            if ($costCenterId === null) {
                $report->addError($fileRow, "centro de custo '{$costCenterCode}' não encontrado.");

                return null;
            }
        }

        $rawAmount = $row['amount'];
        if ($rawAmount === null || $rawAmount === '' || ! is_numeric($rawAmount)) {
            $report->addError($fileRow, "amount inválido ou ausente.");

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

        $now = Carbon::now();

        return [
            'entry_date' => $entryDate,
            'chart_of_account_id' => (int) $account['id'],
            'cost_center_id' => $costCenterId,
            'store_id' => $storeId,
            'amount' => round($signed, 2),
            'budget_version' => $budgetVersion,
            'budget_upload_id' => null,
            'notes' => $this->truncate($row['notes'] ?? null, 500),
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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

    /**
     * Normaliza `entry_date` para o dia 1 do mês (convenção de `dre_budgets`).
     * Aceita serial Excel, string ISO, ou 'YYYY-MM'.
     */
    private function parseMonthDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-01');
        }

        if (is_numeric($value)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return $dt->format('Y-m-01');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $text = trim((string) $value);
        // Aceita 'YYYY-MM' sem dia.
        if (preg_match('/^\d{4}-\d{2}$/', $text)) {
            $text .= '-01';
        }

        try {
            return Carbon::parse($text)->format('Y-m-01');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function truncate(?string $text, int $limit): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return strlen($text) <= $limit ? $text : substr($text, 0, $limit - 3).'...';
    }
}
