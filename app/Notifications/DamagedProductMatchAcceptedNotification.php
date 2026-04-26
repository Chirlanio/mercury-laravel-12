<?php

namespace App\Notifications;

use App\Models\DamagedProductMatch;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DamagedProductMatchAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DamagedProductMatch $match,
        public Transfer $transfer,
        public ?User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'damaged_product_match_accepted',
            'match_id' => $this->match->id,
            'transfer_id' => $this->transfer->id,
            'transfer_status' => $this->transfer->status,
            'transfer_invoice' => $this->transfer->invoice_number,
            'origin_store' => $this->transfer->originStore?->code,
            'destination_store' => $this->transfer->destinationStore?->code,
            'actor_name' => $this->actor?->name,
        ];
    }
}
