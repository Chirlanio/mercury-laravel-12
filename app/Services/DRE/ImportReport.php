<?php

namespace App\Services\DRE;

/**
 * Relatório do `ChartOfAccountsImporter::import()`.
 *
 * Campos segmentados em:
 *   - Estatísticas brutas de leitura (do XLSX).
 *   - Estatísticas de persistência (criadas/atualizadas).
 *   - Avisos e erros (não bloqueantes).
 *
 * Projetado para ser serializável (útil no output do comando artisan
 * e eventualmente em UI de upload futura). Todos os campos são públicos
 * para facilitar inspeção em tests e em logs.
 */
class ImportReport
{
    public int $totalRead = 0;

    public int $ignoredMasterRow = 0;

    /** Contas (V_Grupo 1..5) ------------------------------------------- */
    public int $accountsCreated = 0;

    public int $accountsUpdated = 0;

    public int $accountsDeactivatedByRemoval = 0;

    /** Centros de custo (V_Grupo 8) ------------------------------------ */
    public int $costCentersCreated = 0;

    public int $costCentersUpdated = 0;

    public int $costCentersDeactivatedByRemoval = 0;

    /** Resolução de parent_id ------------------------------------------ */
    public int $accountsLinkedToParent = 0;

    public int $costCentersLinkedToParent = 0;

    /** @var array<int, string> Contas analíticas cujo pai não foi encontrado. */
    public array $orphanWarnings = [];

    /** @var array<int, string> Erros de validação das linhas do XLSX. */
    public array $readErrors = [];

    /** True quando o import foi simulado (nenhum write no DB). */
    public bool $dryRun = false;

    /** Quebra por V_Grupo para visualização rápida no output do comando. */
    public function breakdownByGroup(): array
    {
        return [
            1 => $this->groupCounters[1] ?? 0,
            2 => $this->groupCounters[2] ?? 0,
            3 => $this->groupCounters[3] ?? 0,
            4 => $this->groupCounters[4] ?? 0,
            5 => $this->groupCounters[5] ?? 0,
            8 => $this->groupCounters[8] ?? 0,
        ];
    }

    /** @var array<int,int> Contador interno populado pelo Importer. */
    public array $groupCounters = [];

    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'total_read' => $this->totalRead,
            'ignored_master_row' => $this->ignoredMasterRow,
            'accounts' => [
                'created' => $this->accountsCreated,
                'updated' => $this->accountsUpdated,
                'deactivated_by_removal' => $this->accountsDeactivatedByRemoval,
                'linked_to_parent' => $this->accountsLinkedToParent,
            ],
            'cost_centers' => [
                'created' => $this->costCentersCreated,
                'updated' => $this->costCentersUpdated,
                'deactivated_by_removal' => $this->costCentersDeactivatedByRemoval,
                'linked_to_parent' => $this->costCentersLinkedToParent,
            ],
            'breakdown_by_group' => $this->breakdownByGroup(),
            'orphan_warnings' => $this->orphanWarnings,
            'read_errors' => $this->readErrors,
        ];
    }
}
