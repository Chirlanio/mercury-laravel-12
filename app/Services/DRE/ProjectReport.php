<?php

namespace App\Services\DRE;

/**
 * Relatório de `BudgetToDreProjector::project()`.
 */
class ProjectReport
{
    public int $projected = 0;

    public int $deletedFromPrevious = 0;

    public int $deletedFromSelf = 0;

    public int $skippedSynthetic = 0;

    public int $skippedAccountMissing = 0;

    public int $skippedNonResultGroup = 0;

    public bool $skippedInactive = false;

    public function toArray(): array
    {
        return [
            'projected' => $this->projected,
            'deleted_from_previous' => $this->deletedFromPrevious,
            'deleted_from_self' => $this->deletedFromSelf,
            'skipped_synthetic' => $this->skippedSynthetic,
            'skipped_account_missing' => $this->skippedAccountMissing,
            'skipped_non_result_group' => $this->skippedNonResultGroup,
            'skipped_inactive' => $this->skippedInactive,
        ];
    }
}
