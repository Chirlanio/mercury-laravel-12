<?php

namespace App\Events;

use App\Enums\ReversalStatus;
use App\Models\Reversal;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de Reversal bem-sucedida, pelo
 * ReversalTransitionService. A mutação já foi commitada no banco quando
 * este evento dispara.
 *
 * Consumidores:
 *  - NotifyReversalStakeholders: cria notificações database (sino) para
 *    usuários com APPROVE_REVERSALS/PROCESS_REVERSALS conforme o destino.
 *  - OpenHelpdeskTicketForReversal: se o destino for pending_authorization
 *    e o tenant tiver o hook habilitado, abre ticket no Helpdesk Financeiro
 *    e grava o helpdesk_ticket_id no Reversal.
 */
class ReversalStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Reversal $reversal,
        public readonly ReversalStatus $fromStatus,
        public readonly ReversalStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
