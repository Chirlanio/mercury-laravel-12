<?php

namespace App\Enums;

/**
 * Motivo categorizado para divergência no recebimento de um item de
 * remanejo. Aplicado quando `qty_received < qty_separated` ou quando
 * há observação especial.
 *
 * Diferente da v1 (que tinha campo livre `observations`), aqui
 * categorizamos para habilitar análise agregada no dashboard de
 * Fase 7 (causa raiz de divergências por loja origem).
 */
enum RelocationItemReason: string
{
    case MISSING = 'MISSING';
    case DAMAGE = 'DAMAGE';
    case WRONG_PRODUCT = 'WRONG_PRODUCT';
    case EXTRA = 'EXTRA';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::MISSING => 'Faltou no fardo',
            self::DAMAGE => 'Recebido com avaria',
            self::WRONG_PRODUCT => 'Produto errado',
            self::EXTRA => 'Recebido a mais',
            self::OTHER => 'Outro motivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MISSING => 'warning',
            self::DAMAGE => 'danger',
            self::WRONG_PRODUCT => 'orange',
            self::EXTRA => 'info',
            self::OTHER => 'gray',
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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
