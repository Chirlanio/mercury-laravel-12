<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Disparado após aceite de um match — Transfer já criada e ambos os
 * produtos transicionados para transfer_requested.
 *
 * Broadcast em ambos os canais de loja (origem + destino) para que as 2
 * partes vejam a mudança em tempo real.
 */
class DamagedProductMatchAccepted extends BaseEvent
{
    public function __construct(
        public readonly DamagedProductMatch $match,
        public readonly Transfer $transfer,
        public readonly User $actor,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        $originId = $this->match->suggested_origin_store_id;
        $destinationId = $this->match->suggested_destination_store_id;

        if ($originId) {
            $channels[] = new PrivateChannel("damaged-products.store.{$originId}");
        }

        if ($destinationId && $destinationId !== $originId) {
            $channels[] = new PrivateChannel("damaged-products.store.{$destinationId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'damaged-match.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'transfer_id' => $this->transfer->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
