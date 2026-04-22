<?php

namespace App\Services\DRE;

use App\Imports\DRE\ChartOfAccountsImport;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Orquestra a importação do plano de contas vindo do ERP (CIGAM).
 *
 * Fluxo em 4 passos, todos em uma transação:
 *   1. Lê o XLSX via `ChartOfAccountsImport` (classificação: contas × CCs).
 *   2. Upsert flat (sem parent_id) por `reduced_code` em ambas as tabelas.
 *   3. Segunda passada: resolve `parent_id` por prefixo do `code`.
 *   4. Desativa contas/CCs que sumiram do arquivo em relação ao banco
 *      (apenas quando `external_source` bate com a source atual —
 *      linhas criadas manualmente sem source não são tocadas).
 *
 * Dry-run pula os passos 2–4 mas devolve o que seria feito (insert/update
 * contagem estimada via lookup de existência por reduced_code).
 *
 * Idempotência: rodar 2x o mesmo arquivo não cria duplicatas porque o
 * upsert é por `reduced_code` (chave estável do ERP).
 */
class ChartOfAccountsImporter
{
    public function import(string $filePath, string $source = 'CIGAM', bool $dryRun = false): ImportReport
    {
        $report = new ImportReport();
        $report->dryRun = $dryRun;

        // Passo 1 — leitura ------------------------------------------------
        $importReader = new ChartOfAccountsImport();
        Excel::import($importReader, $filePath);

        $report->totalRead = $importReader->totalRead;
        $report->ignoredMasterRow = $importReader->ignoredMasterRow;
        $report->readErrors = $importReader->errors;

        // Contagem por V_Grupo para exibir no comando.
        foreach ($importReader->accounts as $acc) {
            $g = (int) $acc['account_group'];
            $report->groupCounters[$g] = ($report->groupCounters[$g] ?? 0) + 1;
        }
        $report->groupCounters[8] = count($importReader->costCenters);

        if ($dryRun) {
            $this->computeDryRunEstimate($importReader, $source, $report);

            return $report;
        }

        DB::transaction(function () use ($importReader, $source, $report) {
            $now = Carbon::now();

            // Passo 2 — upsert flat -----------------------------------
            foreach ($importReader->accounts as $row) {
                $result = $this->upsertAccount($row, $source, $now);
                if ($result === 'created') {
                    $report->accountsCreated++;
                } elseif ($result === 'updated') {
                    $report->accountsUpdated++;
                }
            }

            foreach ($importReader->costCenters as $row) {
                $result = $this->upsertCostCenter($row, $source, $now);
                if ($result === 'created') {
                    $report->costCentersCreated++;
                } elseif ($result === 'updated') {
                    $report->costCentersUpdated++;
                }
            }

            // Passo 3 — resolução de parent_id -----------------------
            $this->resolveAccountParents($report);
            $this->resolveCostCenterParents($report);

            // Passo 4 — desativação por sumiço -----------------------
            $this->deactivateRemovedAccounts($importReader, $source, $report);
            $this->deactivateRemovedCostCenters($importReader, $source, $report);
        });

        return $report;
    }

    // ------------------------------------------------------------------
    // Passo 2 — upsert por reduced_code
    // ------------------------------------------------------------------

    private function upsertAccount(array $row, string $source, Carbon $now): string
    {
        $data = [
            'reduced_code' => $row['reduced_code'],
            'code' => $row['code'],
            'name' => $row['name'],
            'type' => $row['type'],
            'account_group' => $row['account_group'],
            'classification_level' => $row['classification_level'],
            'is_result_account' => $row['is_result_account'],
            'balance_nature' => $row['balance_nature'],
            'accepts_entries' => $row['type'] === 'analytical',
            'is_active' => $row['is_active'],
            'external_source' => $source,
            'imported_at' => $now,
        ];

        // Match primário: reduced_code. Match de fallback: code — cobre o
        // caso de seed legado que criou contas sem reduced_code.
        $existing = ChartOfAccount::where('reduced_code', $row['reduced_code'])->first()
            ?? ChartOfAccount::where('code', $row['code'])->whereNull('reduced_code')->first();

        if ($existing === null) {
            ChartOfAccount::create($data);

            return 'created';
        }

        $existing->fill($data);
        if ($existing->isDirty()) {
            $existing->save();

            return 'updated';
        }

        // Mesmo sem mudança material, toca `imported_at` — sinaliza que
        // a linha veio no último import (pra desativação por sumiço).
        $existing->forceFill(['imported_at' => $now])->save();

        return 'touched';
    }

    private function upsertCostCenter(array $row, string $source, Carbon $now): string
    {
        $data = [
            'reduced_code' => $row['reduced_code'],
            'code' => $row['code'],
            'name' => $row['name'],
            'is_active' => $row['is_active'],
            'balance_nature' => $row['balance_nature'],
            'external_source' => $source,
            'imported_at' => $now,
        ];

        $existing = CostCenter::where('reduced_code', $row['reduced_code'])->first()
            ?? CostCenter::where('code', $row['code'])->whereNull('reduced_code')->first();

        if ($existing === null) {
            CostCenter::create($data);

            return 'created';
        }

        $existing->fill($data);
        if ($existing->isDirty()) {
            $existing->save();

            return 'updated';
        }

        $existing->forceFill(['imported_at' => $now])->save();

        return 'touched';
    }

    // ------------------------------------------------------------------
    // Passo 3 — resolução de parent_id por prefixo de code
    // ------------------------------------------------------------------

    private function resolveAccountParents(ImportReport $report): void
    {
        $codeIdMap = ChartOfAccount::query()
            ->pluck('id', 'code')
            ->all();

        ChartOfAccount::query()
            ->select(['id', 'code', 'parent_id', 'type'])
            ->orderBy('code')
            ->chunk(500, function ($chunk) use ($codeIdMap, $report) {
                foreach ($chunk as $account) {
                    $parentCode = $this->parentCodeOf($account->code);
                    if ($parentCode === null) {
                        continue;
                    }

                    $parentId = $codeIdMap[$parentCode] ?? null;

                    if ($parentId === null) {
                        // Pai não existe no plano (órfão). Warning apenas
                        // para contas analíticas — sintéticas órfãs são
                        // comuns em topo de árvore (não têm pai mesmo).
                        if ($account->type?->value === 'analytical') {
                            $report->orphanWarnings[] = sprintf(
                                'Conta analítica %s sem pai: esperava "%s" em chart_of_accounts.',
                                $account->code,
                                $parentCode
                            );
                        }

                        continue;
                    }

                    if ($account->parent_id !== $parentId) {
                        $account->parent_id = $parentId;
                        $account->save();
                    }

                    $report->accountsLinkedToParent++;
                }
            });
    }

    private function resolveCostCenterParents(ImportReport $report): void
    {
        $codeIdMap = CostCenter::query()
            ->pluck('id', 'code')
            ->all();

        CostCenter::query()
            ->select(['id', 'code', 'parent_id'])
            ->orderBy('code')
            ->chunk(500, function ($chunk) use ($codeIdMap, $report) {
                foreach ($chunk as $cc) {
                    $parentCode = $this->parentCodeOf($cc->code);
                    if ($parentCode === null) {
                        continue;
                    }

                    $parentId = $codeIdMap[$parentCode] ?? null;
                    if ($parentId === null) {
                        continue;
                    }

                    if ($cc->parent_id !== $parentId) {
                        $cc->parent_id = $parentId;
                        $cc->save();
                    }

                    $report->costCentersLinkedToParent++;
                }
            });
    }

    /** "1.1.1.01.00016" → "1.1.1.01" | "1" → null */
    private function parentCodeOf(?string $code): ?string
    {
        if (! $code || ! str_contains($code, '.')) {
            return null;
        }

        $lastDot = strrpos($code, '.');

        return substr($code, 0, $lastDot);
    }

    // ------------------------------------------------------------------
    // Passo 4 — desativação por sumiço
    // ------------------------------------------------------------------

    private function deactivateRemovedAccounts(ChartOfAccountsImport $reader, string $source, ImportReport $report): void
    {
        $importedReducedCodes = array_map(fn ($a) => $a['reduced_code'], $reader->accounts);

        $query = ChartOfAccount::query()
            ->where('external_source', $source)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if (! empty($importedReducedCodes)) {
            $query->whereNotIn('reduced_code', $importedReducedCodes);
        }

        $report->accountsDeactivatedByRemoval = $query->update(['is_active' => false]);
    }

    private function deactivateRemovedCostCenters(ChartOfAccountsImport $reader, string $source, ImportReport $report): void
    {
        $importedReducedCodes = array_map(fn ($c) => $c['reduced_code'], $reader->costCenters);

        $query = CostCenter::query()
            ->where('external_source', $source)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if (! empty($importedReducedCodes)) {
            $query->whereNotIn('reduced_code', $importedReducedCodes);
        }

        $report->costCentersDeactivatedByRemoval = $query->update(['is_active' => false]);
    }

    // ------------------------------------------------------------------
    // Dry-run
    // ------------------------------------------------------------------

    private function computeDryRunEstimate(ChartOfAccountsImport $reader, string $source, ImportReport $report): void
    {
        $accountReducedCodes = array_column($reader->accounts, 'reduced_code');
        $ccReducedCodes = array_column($reader->costCenters, 'reduced_code');

        $existingAccountCodes = ChartOfAccount::whereIn('reduced_code', $accountReducedCodes)
            ->pluck('reduced_code')
            ->all();
        $existingCcCodes = CostCenter::whereIn('reduced_code', $ccReducedCodes)
            ->pluck('reduced_code')
            ->all();

        $report->accountsUpdated = count(array_intersect($accountReducedCodes, $existingAccountCodes));
        $report->accountsCreated = count($accountReducedCodes) - $report->accountsUpdated;

        $report->costCentersUpdated = count(array_intersect($ccReducedCodes, $existingCcCodes));
        $report->costCentersCreated = count($ccReducedCodes) - $report->costCentersUpdated;

        // Desativação estimada: quantas linhas no banco com mesmo source
        // não estão no arquivo.
        $report->accountsDeactivatedByRemoval = ChartOfAccount::query()
            ->where('external_source', $source)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when(! empty($accountReducedCodes), fn ($q) => $q->whereNotIn('reduced_code', $accountReducedCodes))
            ->count();

        $report->costCentersDeactivatedByRemoval = CostCenter::query()
            ->where('external_source', $source)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when(! empty($ccReducedCodes), fn ($q) => $q->whereNotIn('reduced_code', $ccReducedCodes))
            ->count();
    }
}
