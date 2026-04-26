<?php

namespace App\Enums;

/**
 * Status simples de atendimento na Lista da Vez.
 *
 *   active → finished (terminal)
 *
 * Atendimentos finalizados ficam imutáveis (auditoria/relatórios).
 * Não há cancelamento — uma consultora que entra por engano pode
 * apenas finalizar com outcome "Outro" + notes.
 *
 * Mesmo enum reusado no `turn_list_breaks` para `status`.
 */
enum TurnListAttendanceStatus: string
{
    case ACTIVE = 'active';
    case FINISHED = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Em Andamento',
            self::FINISHED => 'Finalizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'warning',
            self::FINISHED => 'success',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::FINISHED;
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
