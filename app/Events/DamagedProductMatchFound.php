<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Disparado quando a engine cria um novo match (status=pending).
 *
 * Consumidores:
 *  - NotifyDamagedProductMatchFound (DB+mail listener — histórico persistente)
 *  - Broadcast em canal per-store (live ping pra Index reagir em tempo real)
 *
 * Auto-discovery do Laravel 12 registra os listeners — NÃO chamar
 * Event::listen() manualmente (causaria duplicação).
 */
class DamagedProductMatchFound extends BaseEvent
{
    public function __construct(
        public readonly DamagedProductMatch $match,
    ) {}

    /**
     * Broadcast em 2 canais privados (origem + destino sugeridos). Usa
     * stores.id (numérico) — o canal de auth resolve user.store_id (code)
     * pra id via lookup.
     */
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
        return 'damaged-match.found';
    }

    /**
     * Payload mínimo — apenas o suficiente pro toast no frontend. Cliente
     * faz reload de items+statistics se precisar dos detalhes completos.
     */
    public function broadcastWith(): array
    {
        $match = $this->match;
        $match->loadMissing(['suggestedDestinationStore:id,code,name', 'productA:id,product_reference', 'productB:id,product_reference']);

        return [
            'match_id' => $match->id,
            'match_type' => $match->match_type?->value,
            'match_score' => (float) $match->match_score,
            'product_reference' => $match->productA?->product_reference ?? $match->productB?->product_reference,
            'destination_store_code' => $match->suggestedDestinationStore?->code,
            'destination_store_name' => $match->suggestedDestinationStore?->name,
        ];
    }
}
