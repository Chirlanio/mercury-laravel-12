<?php

namespace App\Enums;

/**
 * Prioridade de atendimento de um remanejo. Ordenação visual + filtro,
 * sem efeito na state machine (não bloqueia transições).
 *
 * Paridade com v1 (adms_relocations.priority varchar 'Baixa'/'Normal'/'Alta')
 * acrescentando `urgent` para casos críticos de ruptura imediata.
 */
enum RelocationPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baixa',
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::NORMAL => 'info',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }

    /**
     * Peso numérico para ordenação (maior = mais urgente). Útil em
     * `ORDER BY priority_weight DESC` quando o filtro pede ordenação por
     * prioridade na listagem.
     */
    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
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
