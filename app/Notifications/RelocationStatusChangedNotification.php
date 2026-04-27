<?php

namespace App\Notifications;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification database (sino do frontend) quando um remanejo muda de
 * status. Disparada pelo NotifyRelocationStakeholders em resposta ao
 * evento RelocationStatusChanged.
 *
 * Apenas database — sem mail — pra não inundar caixa postal com cada
 * transição. Mail reservado pro overdue-alert consolidado diário.
 */
class RelocationStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Relocation $relocation,
        public RelocationStatus $fromStatus,
        public RelocationStatus $toStatus,
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
            'type' => 'relocation_status_changed',
            'relocation_id' => $this->relocation->id,
            'relocation_ulid' => $this->relocation->ulid,
            'title' => $this->relocation->title,
            'origin_store_code' => $this->relocation->originStore?->code,
            'destination_store_code' => $this->relocation->destinationStore?->code,
            'invoice_number' => $this->relocation->invoice_number,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'priority' => $this->relocation->priority?->value,
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }
}
