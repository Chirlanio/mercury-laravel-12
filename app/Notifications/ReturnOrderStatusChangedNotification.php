<?php

namespace App\Notifications;

use App\Enums\ReturnStatus;
use App\Models\ReturnOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação database (sino do frontend) quando uma devolução muda
 * de status. Disparada pelo NotifyReturnOrderStakeholders em resposta
 * ao evento ReturnOrderStatusChanged.
 *
 * Apenas database — sem mail — pra não inundar caixa postal com cada
 * transição. Mail fica reservado pro stale-alert diário.
 */
class ReturnOrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReturnOrder $returnOrder,
        public ReturnStatus $fromStatus,
        public ReturnStatus $toStatus,
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
            'type' => 'return_status_changed',
            'return_order_id' => $this->returnOrder->id,
            'invoice_number' => $this->returnOrder->invoice_number,
            'store_code' => $this->returnOrder->store_code,
            'customer_name' => $this->returnOrder->customer_name,
            'return_type' => $this->returnOrder->type?->value,
            'return_type_label' => $this->returnOrder->type?->label(),
            'amount_items' => (float) $this->returnOrder->amount_items,
            'refund_amount' => $this->returnOrder->refund_amount !== null
                ? (float) $this->returnOrder->refund_amount
                : null,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }
}
