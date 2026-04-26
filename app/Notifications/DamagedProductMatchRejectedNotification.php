<?php

namespace App\Notifications;

use App\Models\DamagedProductMatch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DamagedProductMatchRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DamagedProductMatch $match,
        public ?User $actor,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'damaged_product_match_rejected',
            'match_id' => $this->match->id,
            'match_type' => $this->match->match_type->value,
            'product_reference' => $this->match->productA?->product_reference,
            'origin_store' => $this->match->suggestedOriginStore?->code,
            'destination_store' => $this->match->suggestedDestinationStore?->code,
            'reject_reason' => $this->reason,
            'actor_name' => $this->actor?->name,
        ];
    }
}
