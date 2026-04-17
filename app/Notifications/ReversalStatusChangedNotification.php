<?php

namespace App\Notifications;

use App\Enums\ReversalStatus;
use App\Models\Reversal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação database (sino do frontend) quando um estorno muda de
 * status. Disparada pelo NotifyReversalStakeholders em resposta ao
 * evento ReversalStatusChanged.
 *
 * Apenas database — sem mail — pra não inundar caixa postal com cada
 * transição. Mail reservado pro stale-alert consolidado diário.
 */
class ReversalStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reversal $reversal,
        public ReversalStatus $fromStatus,
        public ReversalStatus $toStatus,
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
            'type' => 'reversal_status_changed',
            'reversal_id' => $this->reversal->id,
            'invoice_number' => $this->reversal->invoice_number,
            'store_code' => $this->reversal->store_code,
            'customer_name' => $this->reversal->customer_name,
            'amount_reversal' => (float) $this->reversal->amount_reversal,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }
}
