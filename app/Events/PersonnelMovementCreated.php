<?php

namespace App\Events;

use App\Models\PersonnelMovement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após a criação de um PersonnelMovement.
 *
 * Consumidores:
 *  - CreateSubstitutionVacancyFromDismissal: cria uma vaga de substituição
 *    automaticamente quando o movimento é do tipo dismissal com open_vacancy=true.
 *
 * Novos listeners podem ser encaixados sem tocar o controller.
 */
class PersonnelMovementCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PersonnelMovement $movement,
    ) {}
}
