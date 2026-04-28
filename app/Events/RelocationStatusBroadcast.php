<?php

namespace App\Events;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Broadcast in real-time pra UI das lojas envolvidas (origem + destino)
 * quando um remanejo muda de status. Disparado pelo listener
 * BroadcastRelocationStatus em resposta ao RelocationStatusChanged.
 *
 * Distinto do evento original (que é interno + auto-discovered): este
 * existe só pro Reverb. Mantém os 2 eventos separados pra que listeners
 * de notification/helpdesk não rodem 2x.
 *
 * Payload mínimo — só o suficiente pro toast no frontend. Cliente faz
 * router.reload({ only: [...] }) se precisar dos detalhes completos.
 */
class RelocationStatusBroadcast extends BaseEvent
{
    public function __construct(
        public readonly Relocation $relocation,
        public readonly RelocationStatus $fromStatus,
        public readonly RelocationStatus $toStatus,
        public readonly ?string $actorName = null,
    ) {}

    /**
     * 2 canais privados (loja origem + loja destino). Auth em
     * routes/channels.php valida que o user pertence a uma das 2 lojas
     * ou tem MANAGE_RELOCATIONS (visão cross-tenant).
     */
    public function broadcastOn(): array
    {
        $channels = [];
        $originId = $this->relocation->origin_store_id;
        $destinationId = $this->relocation->destination_store_id;

        if ($originId) {
            $channels[] = new PrivateChannel("relocations.store.{$originId}");
        }

        if ($destinationId && $destinationId !== $originId) {
            $channels[] = new PrivateChannel("relocations.store.{$destinationId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'relocation.status-changed';
    }

    public function broadcastWith(): array
    {
        $r = $this->relocation;
        $r->loadMissing(['originStore:id,code,name', 'destinationStore:id,code,name']);

        return [
            'relocation_id' => $r->id,
            'relocation_ulid' => $r->ulid,
            'title' => $r->title,
            'origin_store_id' => $r->origin_store_id,
            'origin_store_code' => $r->originStore?->code,
            'destination_store_id' => $r->destination_store_id,
            'destination_store_code' => $r->destinationStore?->code,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actorName,
            'invoice_number' => $r->invoice_number,
        ];
    }
}
