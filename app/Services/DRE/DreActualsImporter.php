<?php

namespace App\Services\DRE;

use App\Enums\AccountType;
use App\Imports\DRE\DreActualsImport;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DrePeriodClosing;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa realizado manual (cash externo, balancete fora do ERP) para
 * `dre_actuals` com `source=MANUAL_IMPORT`.
 *
 * Fluxo em 3 passos:
 *   1. Lê o XLSX via `DreActualsImport`.
 *   2. Pré-carrega lookups: stores, chart_of_accounts, cost_centers
 *      indexados por code. Evita 1 query por linha.
 *   3. Valida linha a linha e faz upsert em chunks de 500 numa transação.
 *
 * Validações (por linha, erros acumulados em PT-BR):
 *   - store_code → deve existir em `stores.code`.
 *   - account_code → deve existir e ser `analytical`.
 *   - cost_center_code (opcional) → se presente, deve existir em `cost_centers.code`.
 *   - amount → numérico.
 *   - entry_date → formato válido; se há fechamento ativo, tem de ser
 *     estritamente posterior (`entry_date > lastClosedUpTo`).
 *
 * Convenção de sinal: XLSX traz valores positivos — service converte por
 * `account_group` (3 → positivo; 4/5 → negativo; 1/2 → erro porque contas
 * patrimoniais não pertencem à DRE).
 *
 * Dedup: `external_id` presente → upsert por `(source=MANUAL_IMPORT, external_id)`.
 * Sem external_id → sempre cria linha nova (import é considerado acréscimo).
 */
class DreActualsImporter
{
    public function import(string $filePath, bool $dryRun = false): DreImportReport
    {
        $report = new DreImportReport();
        $report->dryRun = $dryRun;

        $reader = new DreActualsImport();
        Excel::import($reader, $filePath);
        $report->totalRead = $reader->totalRead;

        if ($reader->totalRead === 0) {
            return $report;
        }

        $lookups = $this->preloadLookups();
        $lastClosed = DrePeriodClosing::lastClosedUpTo();

        // Em dry-run validamos tudo mas não abrimos transação.
        if ($dryRun) {
            foreach ($reader->rows as $row) {
                $this->validateAndBuild($row, $lookups, $lastClosed, $report);
            }

            return $report;
        }

        DB::transaction(function () use ($reader, $lookups, $lastClosed, $report) {
            $buffer = [];
            foreach ($reader->rows as $row) {
                $payload = $this->validateAndBuild($row, $lookups, $lastClosed, $report);
                if ($payload === null) {
                    continue;
                }

                $buffer[] = $payload;

                if (count($buffer) >= 500) {
                    $this->flush($buffer, $report);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $this->flush($buffer, $report);
            }
        });

        return $report;
    }

    /**
     * Valida uma linha e devolve o payload pronto para gravar, ou null
     * quando a linha é pulada (erros registrados no report).
     */
    private function validateAndBuild(
        array $row,
        array $lookups,
        ?string $lastClosed,
        DreImportReport $report,
    ): ?array {
        $fileRow = (int) $row['_row'];

        // entry_date ------------------------------------------------------
        $entryDate = $this->parseDate($row['entry_date']);
        if ($entryDate === null) {
            $report->addError($fileRow, "entry_date inválida ou ausente.");

            return null;
        }

        if ($lastClosed !== null && $entryDate <= $lastClosed) {
            $report->addError(
                $fileRow,
                "entry_date ({$entryDate}) dentro de período fechado ({$lastClosed}). Reabra o período antes de importar."
            );

            return null;
        }

        // store_code ------------------------------------------------------
        $storeCode = (string) ($row['store_code'] ?? '');
        if ($storeCode === '') {
            $report->addError($fileRow, "store_code obrigatório.");

            return null;
        }

        $storeId = $lookups['stores'][$storeCode] ?? null;
        if ($storeId === null) {
            $report->addError($fileRow, "loja '{$storeCode}' não encontrada.");

            return null;
        }

        // account_code ----------------------------------------------------
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

        // cost_center (opcional) -----------------------------------------
        $costCenterId = null;
        $costCenterCode = $row['cost_center_code'] ?? null;
        if ($costCenterCode !== null && $costCenterCode !== '') {
            $costCenterId = $lookups['cost_centers'][$costCenterCode] ?? null;
            if ($costCenterId === null) {
                $report->addError($fileRow, "centro de custo '{$costCenterCode}' não encontrado.");

                return null;
            }
        }

        // amount ----------------------------------------------------------
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

        return [
            'entry_date' => $entryDate,
            'chart_of_account_id' => (int) $account['id'],
            'cost_center_id' => $costCenterId,
            'store_id' => $storeId,
            'amount' => round($signed, 2),
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
            'source_type' => null,
            'source_id' => null,
            'document' => $this->truncate($row['document'] ?? null, 60),
            'description' => $this->truncate($row['description'] ?? null, 500),
            'external_id' => $this->truncate($row['external_id'] ?? null, 100),
            'reported_in_closed_period' => false,
            'imported_at' => Carbon::now(),
        ];
    }

    /**
     * Grava o buffer aplicando upsert por `external_id` quando presente.
     * Linhas sem external_id são inserts puros.
     */
    private function flush(array $buffer, DreImportReport $report): void
    {
        foreach ($buffer as $payload) {
            if ($payload['external_id'] !== null) {
                $existing = DreActual::query()
                    ->where('source', DreActual::SOURCE_MANUAL_IMPORT)
                    ->where('external_id', $payload['external_id'])
                    ->first();

                if ($existing) {
                    $existing->fill($payload)->save();
                    $report->updated++;
                    continue;
                }
            }

            DreActual::create($payload);
            $report->created++;
        }
    }

    /**
     * Converte `amount` positivo vindo do XLSX para valor sinalizado conforme
     * `account_group`. Retorna null quando o grupo não pertence à DRE.
     */
    public function convertSign(float $rawAmount, int $accountGroup): ?float
    {
        $abs = abs($rawAmount);

        return match (true) {
            $accountGroup === 3 => +$abs,
            $accountGroup === 4, $accountGroup === 5 => -$abs,
            default => null, // Grupos 1/2 não vão para DRE.
        };
    }

    /**
     * Pré-carrega lookups em RAM:
     *   - stores: code → id
     *   - accounts: code → ['id', 'type', 'account_group']
     *   - cost_centers: code → id
     */
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

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Maatwebsite devolve serial Excel (float) quando a célula é date.
        if (is_numeric($value)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
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
