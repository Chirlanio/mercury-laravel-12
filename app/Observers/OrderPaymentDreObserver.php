<?php

namespace App\Observers;

use App\Models\OrderPayment;
use App\Services\DRE\OrderPaymentToDreProjector;

/**
 * Mantém `dre_actuals` em sincronia com `OrderPayment`.
 *
 * Regras (playbook prompt 8):
 *   - saved: se transitou para `done` → project(). Se saiu de `done` → unproject().
 *   - saved com status=done e campos relevantes mudaram → re-project (upsert).
 *   - deleting: se estava em `done` → unproject().
 *
 * Campos relevantes: accounting_class_id, cost_center_id, store_id,
 * competence_date, total_value, number_nf, description. Mudança em qualquer
 * um causa re-projeção. Outros campos (observação, payment_prepared, etc.)
 * não afetam a DRE.
 *
 * Falhas silenciosas: exceptions do projetor (ex: conta de grupo inválido)
 * são engolidas e logadas — OrderPayment.save() não deve quebrar por causa
 * de efeito colateral da DRE.
 */
class OrderPaymentDreObserver
{
    public function __construct(private readonly OrderPaymentToDreProjector $projector)
    {
    }

    public function saved(OrderPayment $op): void
    {
        $previousStatus = $op->getOriginal('status');
        $currentStatus = $op->status;
        $isDoneNow = $currentStatus === OrderPayment::STATUS_DONE;
        $wasDoneBefore = $previousStatus === OrderPayment::STATUS_DONE;

        try {
            if ($isDoneNow) {
                $this->projector->project($op);
            } elseif ($wasDoneBefore && ! $isDoneNow) {
                // Saiu de done — remove a projeção.
                $this->projector->unproject($op);
            }
        } catch (\Throwable $e) {
            report($e);
            \Log::error('OrderPaymentDreObserver: falha ao projetar', [
                'op_id' => $op->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleting(OrderPayment $op): void
    {
        if ($op->status === OrderPayment::STATUS_DONE) {
            try {
                $this->projector->unproject($op);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
