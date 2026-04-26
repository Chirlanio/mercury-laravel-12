<?php

namespace App\Enums;

enum DamagedProductStatus: string
{
    case OPEN = 'open';
    case MATCHED = 'matched';
    case TRANSFER_REQUESTED = 'transfer_requested';
    case RESOLVED = 'resolved';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Em aberto',
            self::MATCHED => 'Match encontrado',
            self::TRANSFER_REQUESTED => 'Transferência solicitada',
            self::RESOLVED => 'Resolvido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'gray',
            self::MATCHED => 'info',
            self::TRANSFER_REQUESTED => 'warning',
            self::RESOLVED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RESOLVED, self::CANCELLED], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::MATCHED, self::RESOLVED, self::CANCELLED], true),
            self::MATCHED => in_array($target, [self::OPEN, self::TRANSFER_REQUESTED, self::RESOLVED, self::CANCELLED], true),
            self::TRANSFER_REQUESTED => in_array($target, [self::MATCHED, self::RESOLVED, self::CANCELLED], true),
            self::RESOLVED, self::CANCELLED => false,
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn ($c) => $c->value, self::cases()),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }
}
