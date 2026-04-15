<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\PurchaseOrderStatusChanged;
use App\Models\User;
use App\Notifications\PurchaseOrderStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta PurchaseOrderStatusChanged e notifica stakeholders via database
 * notification (sino do frontend).
 *
 * Recipients:
 *  - Users com MANAGE_PURCHASE_ORDERS (visão global)
 *  - Users com APPROVE_PURCHASE_ORDERS da mesma loja da ordem
 *  - Excluindo o actor (quem fez a transição não precisa se notificar)
 *
 * Síncrono (não usa ShouldQueue) — a notificação database é leve e o
 * sino do frontend espera ver na próxima poll.
 *
 * Falhas no envio NÃO devem quebrar o fluxo de transição (já estamos
 * pós-commit do banco).
 */
class NotifyPurchaseOrderStakeholders
{
    public function handle(PurchaseOrderStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new PurchaseOrderStatusChangedNotification(
                order: $event->order,
                fromStatus: $event->fromStatus,
                toStatus: $event->toStatus,
                actor: $event->actor,
                note: $event->note,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify purchase order stakeholders', [
                'order_id' => $event->order->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(PurchaseOrderStatusChanged $event)
    {
        $actorId = $event->actor->id;
        $storeId = $event->order->store_id;

        return User::query()
            ->where('id', '!=', $actorId)
            ->get()
            ->filter(function (User $user) use ($storeId) {
                if ($user->hasPermissionTo(Permission::MANAGE_PURCHASE_ORDERS->value)) {
                    return true;
                }

                if ($user->hasPermissionTo(Permission::APPROVE_PURCHASE_ORDERS->value)
                    && $user->store_id === $storeId) {
                    return true;
                }

                return false;
            })
            ->values();
    }
}
