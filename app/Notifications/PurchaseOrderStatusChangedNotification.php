<?php

namespace App\Notifications;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação database (sino do frontend) quando uma ordem de compra
 * muda de status. Disparada pelo NotifyPurchaseOrderStakeholders listener
 * em resposta ao evento PurchaseOrderStatusChanged.
 *
 * Apenas database — sem mail — pra não inundar caixa postal com cada
 * transição de cada ordem. Mail fica reservado pro late alert (consolidado
 * diariamente).
 */
class PurchaseOrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseOrder $order,
        public PurchaseOrderStatus $fromStatus,
        public PurchaseOrderStatus $toStatus,
        public ?User $actor,
        public ?string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'purchase_order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'short_description' => $this->order->short_description,
            'store_id' => $this->order->store_id,
            'supplier_name' => $this->order->supplier?->nome_fantasia,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }
}
