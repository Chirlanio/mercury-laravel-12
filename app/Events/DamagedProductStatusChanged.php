<?php

namespace App\Events;

use App\Enums\DamagedProductStatus;
use App\Models\DamagedProduct;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado pelo DamagedProductTransitionService após transição de status
 * bem-sucedida (mutação já commitada no banco).
 *
 * Consumidores (auto-discovered em app/Listeners/):
 *  - NotifyDamagedProductStakeholders: notifica gerentes da loja envolvida
 *  - OpenHelpdeskTicketOnDamagedProductMatchRejected: opcional, abre ticket
 *    no Helpdesk de Operações quando rejeições recorrentes acontecem
 */
class DamagedProductStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DamagedProduct $product,
        public readonly DamagedProductStatus $fromStatus,
        public readonly DamagedProductStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
