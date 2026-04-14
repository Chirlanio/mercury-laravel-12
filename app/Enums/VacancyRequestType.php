<?php

namespace App\Enums;

/**
 * Tipos de solicitação de vaga (paridade v1 — adms_request_types):
 *
 *  - substitution (Substituição): substituir um colaborador que saiu.
 *    Exige replaced_employee_id no VacancyService::create.
 *
 *  - headcount_increase (Aumento de Quadro): nova posição além do quadro
 *    atual, sem vínculo com colaborador existente.
 *
 *  - floater (Volante): funcionário coringa/reserva que cobre férias,
 *    faltas e ausências em múltiplas lojas.
 */
enum VacancyRequestType: string
{
    case SUBSTITUTION = 'substitution';
    case HEADCOUNT_INCREASE = 'headcount_increase';
    case FLOATER = 'floater';

    public function label(): string
    {
        return match ($this) {
            self::SUBSTITUTION => 'Substituição',
            self::HEADCOUNT_INCREASE => 'Aumento de Quadro',
            self::FLOATER => 'Volante',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUBSTITUTION => 'orange',
            self::HEADCOUNT_INCREASE => 'success',
            self::FLOATER => 'info',
        };
    }

    public function requiresReplacedEmployee(): bool
    {
        return $this === self::SUBSTITUTION;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
