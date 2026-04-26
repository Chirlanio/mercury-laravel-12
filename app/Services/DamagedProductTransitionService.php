<?php

namespace App\Services;

use App\Enums\DamagedProductStatus;
use App\Enums\Permission;
use App\Events\DamagedProductStatusChanged;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\DamagedProductStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de produtos avariados. Ponto único de mutação de status.
 * Outros services e controllers NUNCA devem setar o campo direto.
 *
 * Transições válidas (DamagedProductStatus::canTransitionTo):
 *   open                → matched | resolved | cancelled
 *   matched             → open | transfer_requested | resolved | cancelled
 *   transfer_requested  → matched | resolved | cancelled
 *   resolved            → []  (terminal)
 *   cancelled           → []  (terminal)
 *
 * Permissões por transição:
 *  - * → cancelled: exige DELETE_DAMAGED_PRODUCTS + note (motivo)
 *  - * → resolved/transfer_requested: exige APPROVE_DAMAGED_PRODUCT_MATCHES
 *  - matched → open (rejeição): exige APPROVE_DAMAGED_PRODUCT_MATCHES
 *  - open → matched: setado pela engine (ela usa actor=system)
 *  - MANAGE_DAMAGED_PRODUCTS bypassa todos os checks acima
 *
 * Side effects são despachados via DamagedProductStatusChanged event —
 * listeners auto-discovered notificam stakeholders, gravam audit log etc.
 * Não chame Event::listen manualmente (gotcha Laravel 12 — duplica handler).
 */
class DamagedProductTransitionService
{
    /**
     * @throws ValidationException
     */
    public function transition(
        DamagedProduct $product,
        DamagedProductStatus|string $toStatus,
        User $actor,
        ?string $note = null,
        ?DamagedProductMatch $triggeredByMatch = null,
    ): DamagedProduct {
        $target = $toStatus instanceof DamagedProductStatus
            ? $toStatus
            : DamagedProductStatus::from($toStatus);

        $current = $product->status;

        if ($current === null) {
            throw ValidationException::withMessages([
                'status' => 'Produto avariado sem status — registro corrompido.',
            ]);
        }

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeTransition($current, $target, $actor);

        if ($target === DamagedProductStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'Informe o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($product, $current, $target, $actor, $note, $triggeredByMatch) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === DamagedProductStatus::CANCELLED) {
                $update['cancelled_at'] = now();
                $update['cancelled_by_user_id'] = $actor->id;
                $update['cancel_reason'] = $note;
            }

            if ($target === DamagedProductStatus::RESOLVED) {
                $update['resolved_at'] = now();
            }

            $product->update($update);

            DamagedProductStatusHistory::create([
                'damaged_product_id' => $product->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'note' => $note,
                'triggered_by_match_id' => $triggeredByMatch?->id,
                'actor_user_id' => $actor->id,
            ]);

            $fresh = $product->fresh(['store', 'damageType', 'photos', 'createdBy']);

            DamagedProductStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeTransition(
        DamagedProductStatus $from,
        DamagedProductStatus $to,
        User $actor,
    ): void {
        // MANAGE bypassa todos os checks (admin/support).
        if ($actor->hasPermissionTo(Permission::MANAGE_DAMAGED_PRODUCTS->value)) {
            return;
        }

        if ($to === DamagedProductStatus::CANCELLED) {
            if (! $actor->hasPermissionTo(Permission::DELETE_DAMAGED_PRODUCTS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar produtos avariados.',
                ]);
            }

            return;
        }

        // Resolução manual (sem passar pelo fluxo de match aceito) e
        // qualquer transição envolvendo aceite/rejeição de matches.
        if (
            $to === DamagedProductStatus::RESOLVED
            || $to === DamagedProductStatus::TRANSFER_REQUESTED
            || ($from === DamagedProductStatus::MATCHED && $to === DamagedProductStatus::OPEN)
        ) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para aprovar/resolver produtos avariados.',
                ]);
            }

            return;
        }

        // Demais transições (volta de transfer_requested → matched) também
        // exigem APPROVE — só admin/support podem reverter ações de match.
        if (! $actor->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value)) {
            throw ValidationException::withMessages([
                'status' => 'Você não tem permissão para alterar status de produtos avariados.',
            ]);
        }
    }
}
