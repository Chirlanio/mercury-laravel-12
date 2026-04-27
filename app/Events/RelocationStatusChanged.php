<?php

namespace App\Events;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de Relocation bem-sucedida, pelo
 * RelocationTransitionService. A mutação já foi commitada no banco quando
 * este evento dispara.
 *
 * Consumidores (Fase 6 — auto-discovered, NÃO registrar Event::listen
 * manualmente em EventServiceProvider; gera duplicação por causa do
 * auto-discovery do Laravel 12):
 *  - NotifyRelocationStakeholders: notificações database+mail para
 *    solicitante, gerentes loja origem/destino e planejamento conforme
 *    o destino.
 *  - OpenHelpdeskTicketForRelocation: se o destino for `rejected` ou
 *    `cancelled` pós-aprovação, abre ticket no Helpdesk Logística e
 *    grava o helpdesk_ticket_id no Relocation (idempotente).
 */
class RelocationStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Relocation $relocation,
        public readonly RelocationStatus $fromStatus,
        public readonly RelocationStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
