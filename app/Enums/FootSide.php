<?php

namespace App\Enums;

enum FootSide: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case BOTH = 'both';
    case NA = 'na';

    public function label(): string
    {
        return match ($this) {
            self::LEFT => 'Pé esquerdo',
            self::RIGHT => 'Pé direito',
            self::BOTH => 'Ambos os pés',
            self::NA => 'Não se aplica',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::LEFT => 'Esq.',
            self::RIGHT => 'Dir.',
            self::BOTH => 'Ambos',
            self::NA => 'N/A',
        };
    }

    public function opposite(): ?self
    {
        return match ($this) {
            self::LEFT => self::RIGHT,
            self::RIGHT => self::LEFT,
            default => null,
        };
    }

    public function isSingleFoot(): bool
    {
        return in_array($this, [self::LEFT, self::RIGHT], true);
    }
}
