<?php

namespace App\Enums;

enum DamageMatchStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::ACCEPTED => 'Aceito',
            self::REJECTED => 'Rejeitado',
            self::EXPIRED => 'Expirado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::EXPIRED => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED], true);
    }
}
