<?php

namespace App\Observers;

use App\Models\BudgetUpload;
use App\Services\DRE\BudgetToDreProjector;
use Illuminate\Support\Facades\Log;

/**
 * Observer que sincroniza `dre_budgets` com o `is_active` de `BudgetUpload`.
 *
 * Estratégia:
 *   - `created` com is_active=true   → project()
 *   - `updated` flipando is_active   → project() (true) / unproject() (false)
 *   - `deleting` com is_active=true  → unproject()
 *
 * Falhas são logadas (não propagadas) — DRE é derivada; operação de budgets
 * não deve quebrar caso a projeção falhe. O command `dre:rebuild-budgets`
 * (futuro) permite reconciliar.
 */
class BudgetUploadDreObserver
{
    public function __construct(private readonly BudgetToDreProjector $projector)
    {
    }

    public function created(BudgetUpload $upload): void
    {
        if ($upload->is_active) {
            $this->safeProject($upload);
        }
    }

    public function updated(BudgetUpload $upload): void
    {
        if (! $upload->wasChanged('is_active')) {
            return;
        }

        if ($upload->is_active) {
            $this->safeProject($upload);

            return;
        }

        $this->safeUnproject($upload);
    }

    public function deleting(BudgetUpload $upload): void
    {
        if ($upload->is_active) {
            $this->safeUnproject($upload);
        }
    }

    private function safeProject(BudgetUpload $upload): void
    {
        try {
            $this->projector->project($upload);
        } catch (\Throwable $e) {
            report($e);
            Log::error('BudgetUploadDreObserver: project falhou', [
                'budget_upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function safeUnproject(BudgetUpload $upload): void
    {
        try {
            $this->projector->unproject($upload);
        } catch (\Throwable $e) {
            report($e);
            Log::error('BudgetUploadDreObserver: unproject falhou', [
                'budget_upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
