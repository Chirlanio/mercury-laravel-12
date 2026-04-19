<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\ReturnStatus;
use App\Events\ReturnOrderStatusChanged;
use App\Models\ReturnOrder;
use App\Models\ReturnOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de devoluções. Ponto único de mutação de
 * ReturnOrder::status. Outros serviços e controllers NUNCA devem setar
 * o campo direto.
 *
 * Transições válidas (ReturnStatus::allowedTransitions):
 *   pending → approved | cancelled
 *   approved → pending | awaiting_product | processing | cancelled
 *   awaiting_product → approved | processing | completed | cancelled
 *   processing → awaiting_product | completed | cancelled
 *   completed → [] (terminal)
 *   cancelled → [] (terminal)
 *
 * Permissões por transição:
 *  - pending → approved: exige APPROVE_RETURNS
 *  - awaiting_product/processing → completed: exige PROCESS_RETURNS
 *  - * → cancelled: exige APPROVE_RETURNS ou CANCEL_RETURNS + note
 *  - voltas (ex: approved → pending): exige APPROVE_RETURNS
 *  - pending → cancelled (criador pode cancelar o próprio antes de aprovar):
 *    CANCEL_RETURNS também é aceito para dar granularidade operacional
 *
 * Ao transitar para:
 *  - approved: grava approved_by_user_id + approved_at
 *  - completed: grava processed_by_user_id + completed_at
 *  - cancelled: grava cancelled_at + cancelled_reason
 */
class ReturnOrderTransitionService
{
    /**
     * @throws ValidationException
     */
    public function transition(
        ReturnOrder $order,
        ReturnStatus|string $toStatus,
        User $actor,
        ?string $note = null
    ): ReturnOrder {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'return' => 'Não é possível transicionar uma devolução excluída.',
            ]);
        }

        $target = $toStatus instanceof ReturnStatus ? $toStatus : ReturnStatus::from($toStatus);
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeTransition($current, $target, $actor);

        if ($target === ReturnStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($order, $current, $target, $actor, $note) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === ReturnStatus::APPROVED) {
                $update['approved_by_user_id'] = $actor->id;
                $update['approved_at'] = now();
            }

            if ($target === ReturnStatus::COMPLETED) {
                $update['processed_by_user_id'] = $actor->id;
                $update['completed_at'] = now();
            }

            if ($target === ReturnStatus::CANCELLED) {
                $update['cancelled_at'] = now();
                $update['cancelled_reason'] = $note;
            }

            $order->update($update);

            ReturnOrderStatusHistory::create([
                'return_order_id' => $order->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $order->fresh(['reason', 'statusHistory', 'items', 'files']);

            // Notifications são disparadas por listener do evento abaixo.
            // Service permanece agnóstico de side-effects.
            ReturnOrderStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeTransition(
        ReturnStatus $from,
        ReturnStatus $to,
        User $actor
    ): void {
        // Cancelamento — aceita APPROVE_RETURNS ou CANCEL_RETURNS
        if ($to === ReturnStatus::CANCELLED) {
            $canCancel = $actor->hasPermissionTo(Permission::APPROVE_RETURNS->value)
                || $actor->hasPermissionTo(Permission::CANCEL_RETURNS->value);

            if (! $canCancel) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar devoluções.',
                ]);
            }

            return;
        }

        // Conclusão (→ completed) exige PROCESS_RETURNS
        if ($to === ReturnStatus::COMPLETED) {
            if (! $actor->hasPermissionTo(Permission::PROCESS_RETURNS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para concluir (finalizar) devoluções.',
                ]);
            }

            return;
        }

        // Aprovação (pending → approved) exige APPROVE_RETURNS
        if ($from === ReturnStatus::PENDING && $to === ReturnStatus::APPROVED) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_RETURNS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para aprovar devoluções.',
                ]);
            }

            return;
        }

        // Movimentações operacionais (approved → awaiting_product/processing,
        // awaiting_product → processing): exige PROCESS_RETURNS
        $processingMoves = [
            [ReturnStatus::APPROVED, ReturnStatus::AWAITING_PRODUCT],
            [ReturnStatus::APPROVED, ReturnStatus::PROCESSING],
            [ReturnStatus::AWAITING_PRODUCT, ReturnStatus::PROCESSING],
        ];

        foreach ($processingMoves as [$f, $t]) {
            if ($from === $f && $to === $t) {
                if (! $actor->hasPermissionTo(Permission::PROCESS_RETURNS->value)) {
                    throw ValidationException::withMessages([
                        'status' => 'Você não tem permissão para movimentar devoluções.',
                    ]);
                }

                return;
            }
        }

        // Voltas (regressões) exigem APPROVE_RETURNS
        if ($this->isBackwardTransition($from, $to)) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_RETURNS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para reverter status de devoluções.',
                ]);
            }

            return;
        }
    }

    protected function isBackwardTransition(ReturnStatus $from, ReturnStatus $to): bool
    {
        $order = [
            ReturnStatus::PENDING->value => 1,
            ReturnStatus::APPROVED->value => 2,
            ReturnStatus::AWAITING_PRODUCT->value => 3,
            ReturnStatus::PROCESSING->value => 4,
        ];

        return isset($order[$from->value], $order[$to->value])
            && $order[$to->value] < $order[$from->value];
    }
}
