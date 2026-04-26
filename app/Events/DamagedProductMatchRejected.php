<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após rejeição de um match. Reason obrigatório.
 *
 * Consumidor opcional: OpenHelpdeskTicketOnDamagedProductMatchRejected —
 * abre ticket no Helpdesk Operações se o tenant tem o hook habilitado.
 */
class DamagedProductMatchRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DamagedProductMatch $match,
        public readonly User $actor,
        public readonly string $reason,
    ) {}
}
