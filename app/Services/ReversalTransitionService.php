<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\ReversalStatus;
use App\Events\ReversalStatusChanged;
use App\Models\Reversal;
use App\Models\ReversalStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de estornos. Ponto único de mutação de Reversal::status.
 * Outros serviços e controllers NUNCA devem setar o campo direto.
 *
 * Transições válidas (definidas em ReversalStatus::allowedTransitions):
 *   pending_reversal → pending_authorization | reversed | cancelled
 *   pending_authorization → pending_reversal | authorized | reversed | cancelled
 *   authorized → pending_authorization | pending_finance | reversed | cancelled
 *   pending_finance → authorized | reversed | cancelled
 *   reversed → [] (terminal)
 *   cancelled → [] (terminal)
 *
 * Permissões por transição:
 *  - pending_authorization → authorized: exige APPROVE_REVERSALS
 *  - pending_finance → reversed: exige PROCESS_REVERSALS
 *  - pending_authorization → reversed (atalho): exige PROCESS_REVERSALS
 *  - pending_reversal → reversed (atalho): exige PROCESS_REVERSALS
 *  - * → cancelled: exige APPROVE_REVERSALS + note (motivo)
 *  - pending_reversal → pending_authorization: exige CREATE_REVERSALS ou EDIT_REVERSALS
 *  - voltas (ex: authorized → pending_authorization): exige APPROVE_REVERSALS
 *
 * Ao transitar para REVERSED: grava reversed_at = now() + processed_by.
 * Ao transitar para AUTHORIZED: grava authorized_by = actor.
 * Ao transitar para CANCELLED: grava cancelled_at + cancelled_reason.
 */
class ReversalTransitionService
{
    /**
     * @throws ValidationException
     */
    public function transition(
        Reversal $reversal,
        ReversalStatus|string $toStatus,
        User $actor,
        ?string $note = null
    ): Reversal {
        if ($reversal->is_deleted) {
            throw ValidationException::withMessages([
                'reversal' => 'Não é possível transicionar um estorno excluído.',
            ]);
        }

        $target = $toStatus instanceof ReversalStatus ? $toStatus : ReversalStatus::from($toStatus);
        $current = $reversal->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeTransition($current, $target, $actor);

        if ($target === ReversalStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($reversal, $current, $target, $actor, $note) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === ReversalStatus::AUTHORIZED) {
                $update['authorized_by_user_id'] = $actor->id;
            }

            if ($target === ReversalStatus::REVERSED) {
                $update['processed_by_user_id'] = $actor->id;
                $update['reversed_at'] = now();
            }

            if ($target === ReversalStatus::CANCELLED) {
                $update['cancelled_at'] = now();
                $update['cancelled_reason'] = $note;
            }

            $reversal->update($update);

            ReversalStatusHistory::create([
                'reversal_id' => $reversal->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $reversal->fresh(['reason', 'statusHistory', 'items', 'paymentType', 'files']);

            // Notifications + hook Helpdesk sao disparados por listeners do
            // evento abaixo. Mantemos este service agnostico de side-effects.
            ReversalStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    /**
     * Autoriza a transição com base nas permissions do actor.
     *
     * @throws ValidationException
     */
    protected function authorizeTransition(
        ReversalStatus $from,
        ReversalStatus $to,
        User $actor
    ): void {
        // Cancelamento em qualquer direção
        if ($to === ReversalStatus::CANCELLED) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar estornos.',
                ]);
            }

            return;
        }

        // Execução do estorno (chega em reversed por qualquer caminho)
        if ($to === ReversalStatus::REVERSED) {
            if (! $actor->hasPermissionTo(Permission::PROCESS_REVERSALS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para executar (finalizar) estornos.',
                ]);
            }

            return;
        }

        // Autorização
        if ($from === ReversalStatus::PENDING_AUTHORIZATION && $to === ReversalStatus::AUTHORIZED) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para autorizar estornos.',
                ]);
            }

            return;
        }

        // Envio à financeira
        if ($from === ReversalStatus::AUTHORIZED && $to === ReversalStatus::PENDING_FINANCE) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para encaminhar estornos à financeira.',
                ]);
            }

            return;
        }

        // Envio à aprovação
        if ($from === ReversalStatus::PENDING_REVERSAL && $to === ReversalStatus::PENDING_AUTHORIZATION) {
            $allowed = $actor->hasPermissionTo(Permission::CREATE_REVERSALS->value)
                || $actor->hasPermissionTo(Permission::EDIT_REVERSALS->value);

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para solicitar autorização de estorno.',
                ]);
            }

            return;
        }

        // Voltar um passo (authorized → pending_authorization,
        // pending_authorization → pending_reversal, pending_finance → authorized)
        if ($this->isBackwardTransition($from, $to)) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para reverter status de estornos.',
                ]);
            }

            return;
        }
    }

    protected function isBackwardTransition(ReversalStatus $from, ReversalStatus $to): bool
    {
        $order = [
            ReversalStatus::PENDING_REVERSAL->value => 1,
            ReversalStatus::PENDING_AUTHORIZATION->value => 2,
            ReversalStatus::AUTHORIZED->value => 3,
            ReversalStatus::PENDING_FINANCE->value => 4,
        ];

        return isset($order[$from->value], $order[$to->value])
            && $order[$to->value] < $order[$from->value];
    }
}
