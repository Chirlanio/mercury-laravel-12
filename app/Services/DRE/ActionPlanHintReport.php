<?php

namespace App\Services\DRE;

/**
 * Relatório do `ActionPlanHintImporter`.
 *
 * Campos em inglês, comentários em PT. Serializável para o command de
 * artisan e para eventual UI de upload futura.
 */
class ActionPlanHintReport
{
    public bool $fileNotFound = false;

    public int $totalRowsRead = 0;

    public int $uniquePairsFound = 0;

    public int $accountsUpdated = 0;

    public int $accountsSkippedAlreadyHinted = 0;

    public int $accountsNotFound = 0;

    public int $managementClassesNotFound = 0;

    /** @var array<int,string> Códigos de conta não encontrados (amostra até 20). */
    public array $missingAccountCodes = [];

    /** @var array<int,string> Códigos de management_class não encontrados (amostra até 20). */
    public array $missingManagementClassCodes = [];

    public function toArray(): array
    {
        return [
            'file_not_found' => $this->fileNotFound,
            'total_rows_read' => $this->totalRowsRead,
            'unique_pairs_found' => $this->uniquePairsFound,
            'accounts_updated' => $this->accountsUpdated,
            'accounts_skipped_already_hinted' => $this->accountsSkippedAlreadyHinted,
            'accounts_not_found' => $this->accountsNotFound,
            'management_classes_not_found' => $this->managementClassesNotFound,
            'missing_account_codes' => $this->missingAccountCodes,
            'missing_management_class_codes' => $this->missingManagementClassCodes,
        ];
    }
}
