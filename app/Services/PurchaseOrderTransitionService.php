<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseOrderStatusChanged;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de ordens de compra. Ponto único de mutação de
 * PurchaseOrder::status. Outros serviços e controllers NUNCA devem setar o
 * campo direto.
 *
 * Transições válidas (definidas em PurchaseOrderStatus::allowedTransitions):
 *   pending → invoiced | partial_invoiced | cancelled
 *   invoiced → delivered | cancelled
 *   partial_invoiced → invoiced | cancelled
 *   cancelled → pending (reabertura)
 *   delivered → [] (terminal)
 *
 * Permissões por transição:
 *  - pending → invoiced/partial_invoiced: exige APPROVE_PURCHASE_ORDERS
 *  - * → cancelled: exige CANCEL_PURCHASE_ORDERS + note
 *  - cancelled → pending: exige CANCEL_PURCHASE_ORDERS (reabertura)
 *  - invoiced/partial_invoiced → delivered: exige RECEIVE_PURCHASE_ORDERS
 *    (normalmente disparado pelo PurchaseOrderReceiptService na Fase 2)
 *
 * Ao transitar para DELIVERED: grava delivered_at = now().
 * Dispara evento PurchaseOrderStatusChanged para notificações (Fase 1 MVP
 * registra o histórico; o listener de notificações vem junto).
 */
class PurchaseOrderTransitionService
{
    public function __construct(
        protected PurchaseOrderPaymentGenerator $paymentGenerator,
    ) {}

    /**
     * @throws ValidationException
     */
    public function transition(
        PurchaseOrder $order,
        PurchaseOrderStatus|string $toStatus,
        User $actor,
        ?string $note = null
    ): PurchaseOrder {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'order' => 'Não é possível transicionar uma ordem excluída.',
            ]);
        }

        $target = $toStatus instanceof PurchaseOrderStatus ? $toStatus : PurchaseOrderStatus::from($toStatus);
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        $this->authorizeTransition($current, $target, $actor);

        if ($target === PurchaseOrderStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($order, $current, $target, $actor, $note) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === PurchaseOrderStatus::DELIVERED) {
                $update['delivered_at'] = now();
            }

            $order->update($update);

            PurchaseOrderStatusHistory::create([
                'purchase_order_id' => $order->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            // Auto-geração de parcelas em order_payments quando a ordem
            // entra em INVOICED com auto_generate_payments=true. Idempotente
            // (PaymentGenerator skipa se já há OPs vinculadas).
            if ($target === PurchaseOrderStatus::INVOICED) {
                $this->paymentGenerator->generateForOrder($order->fresh('items'), $actor);
            }

            PurchaseOrderStatusChanged::dispatch(
                $order->fresh(['supplier', 'store', 'items', 'statusHistory']),
                $current,
                $target,
                $actor,
                $note
            );

            return $order->fresh(['supplier', 'store', 'brand', 'items', 'statusHistory']);
        });
    }

    /**
     * Autoriza a transição com base nas permissions do actor.
     *
     * @throws ValidationException
     */
    protected function authorizeTransition(
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
        User $actor
    ): void {
        // Cancelamento em qualquer direção (incluindo reabertura)
        if ($to === PurchaseOrderStatus::CANCELLED || $from === PurchaseOrderStatus::CANCELLED) {
            if (! $actor->hasPermissionTo(Permission::CANCEL_PURCHASE_ORDERS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar ou reabrir ordens de compra.',
                ]);
            }

            return;
        }

        // Aprovação / faturamento
        if ($from === PurchaseOrderStatus::PENDING
            && in_array($to, [PurchaseOrderStatus::INVOICED, PurchaseOrderStatus::PARTIAL_INVOICED], true)) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_PURCHASE_ORDERS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para faturar ordens de compra.',
                ]);
            }

            return;
        }

        // Recebimento (entrega)
        if ($to === PurchaseOrderStatus::DELIVERED) {
            if (! $actor->hasPermissionTo(Permission::RECEIVE_PURCHASE_ORDERS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para marcar ordens como entregues.',
                ]);
            }

            return;
        }

        // Partial → Invoiced (mesma permission de aprovação)
        if ($from === PurchaseOrderStatus::PARTIAL_INVOICED && $to === PurchaseOrderStatus::INVOICED) {
            if (! $actor->hasPermissionTo(Permission::APPROVE_PURCHASE_ORDERS->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para completar o faturamento.',
                ]);
            }
        }
    }
}
