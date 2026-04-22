<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\DRE\SaleToDreProjector;

/**
 * Mantém `dre_actuals` em sincronia com `Sale`.
 *
 * Regras (playbook prompt 8):
 *   - created: project().
 *   - updated em campos relevantes (store_id, date_sales, total_sales) → re-project.
 *   - deleted: unproject().
 *
 * Falhas silenciosas: o projetor já tem lógica de SKIP quando a conta
 * de receita não resolve (resposta #17 da arquitetura). Exceptions
 * inesperadas são logadas e não propagam para não quebrar o fluxo de
 * venda.
 */
class SaleDreObserver
{
    public function __construct(private readonly SaleToDreProjector $projector)
    {
    }

    public function created(Sale $sale): void
    {
        $this->safeProject($sale);
    }

    public function updated(Sale $sale): void
    {
        if (! $sale->wasChanged(['store_id', 'date_sales', 'total_sales'])) {
            return;
        }

        $this->safeProject($sale);
    }

    public function deleted(Sale $sale): void
    {
        try {
            $this->projector->unproject($sale);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function safeProject(Sale $sale): void
    {
        try {
            $this->projector->project($sale);
        } catch (\Throwable $e) {
            report($e);
            \Log::error('SaleDreObserver: falha ao projetar', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
