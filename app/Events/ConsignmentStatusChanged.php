<?php

namespace App\Events;

use App\Enums\ConsignmentStatus;
use App\Models\Consignment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de Consignação bem-sucedida,
 * pelo ConsignmentTransitionService. A mutação já foi commitada no banco
 * quando este evento dispara.
 *
 * `actor` pode ser null apenas em transições automáticas
 * (consignments:mark-overdue — pending/partial → overdue).
 *
 * Consumidores (auto-discovery Laravel 12 — NÃO registrar manualmente):
 *  - NotifyConsignmentStakeholders: cria notificações database (sino)
 *    para consultor/criador conforme a transição + loja scoped.
 */
class ConsignmentStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Consignment $consignment,
        public readonly ConsignmentStatus $fromStatus,
        public readonly ConsignmentStatus $toStatus,
        public readonly ?User $actor,
        public readonly ?string $note = null,
    ) {
    }
}
