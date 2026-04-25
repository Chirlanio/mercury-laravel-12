<?php

namespace App\Events;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de TravelExpense bem-sucedida,
 * pelo TravelExpenseTransitionService. A mutação já foi commitada no
 * banco quando este evento dispara.
 *
 * O mesmo evento serve às duas state machines:
 *  - kind = 'expense'         → fromStatus/toStatus são TravelExpenseStatus
 *  - kind = 'accountability'  → fromStatus/toStatus são AccountabilityStatus
 *
 * actor pode ser null apenas em transições automáticas (commands).
 *
 * Consumidores:
 *  - NotifyTravelExpenseStakeholders: cria notificações database+mail
 *    para criador/aprovadores conforme a transição.
 */
class TravelExpenseStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TravelExpense $travelExpense,
        public readonly TravelExpenseStatus|AccountabilityStatus $fromStatus,
        public readonly TravelExpenseStatus|AccountabilityStatus $toStatus,
        public readonly ?User $actor,
        public readonly ?string $note = null,
        public readonly string $kind = 'expense',
    ) {}
}
