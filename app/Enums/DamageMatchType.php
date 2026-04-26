<?php

namespace App\Enums;

enum DamageMatchType: string
{
    case MISMATCHED_PAIR = 'mismatched_pair';
    case DAMAGED_COMPLEMENT = 'damaged_complement';

    public function label(): string
    {
        return match ($this) {
            self::MISMATCHED_PAIR => 'Par trocado',
            self::DAMAGED_COMPLEMENT => 'Avaria complementar',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MISMATCHED_PAIR => 'Lojas trocam pés desencontrados (loja A com pé esquerdo X / direito Y, loja B com inverso) para formar dois pares corretos',
            self::DAMAGED_COMPLEMENT => 'Lojas combinam pés sãos de pares com avaria oposta (A com pé esquerdo bom + B com pé direito bom) para recuperar um par',
        };
    }
}
