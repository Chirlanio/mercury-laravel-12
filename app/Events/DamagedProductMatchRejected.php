<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Disparado após rejeição de um match. Reason obrigatório.
 *
 * Broadcast em ambos os canais de loja (origem + destino).
 *
 * Consumidor opcional futuro: OpenHelpdeskTicketOnDamagedProductMatchRejected
 * (Item 2 do backlog — não implementado).
 */
class DamagedProductMatchRejected extends BaseEvent
{
    public function __construct(
        public readonly DamagedProductMatch $match,
        public readonly User $actor,
        public readonly string $reason,
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
        return 'damaged-match.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'actor_name' => $this->actor->name,
            'reason' => $this->reason,
        ];
    }
}
