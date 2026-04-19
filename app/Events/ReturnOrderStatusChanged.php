<?php

namespace App\Events;

use App\Enums\ReturnStatus;
use App\Models\ReturnOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de ReturnOrder bem-sucedida,
 * pelo ReturnOrderTransitionService. A mutação já foi commitada no
 * banco quando este evento dispara.
 *
 * Consumidores:
 *  - NotifyReturnOrderStakeholders: cria notificações database (sino)
 *    para criador/aprovadores conforme a transição.
 */
class ReturnOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReturnOrder $returnOrder,
        public readonly ReturnStatus $fromStatus,
        public readonly ReturnStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
