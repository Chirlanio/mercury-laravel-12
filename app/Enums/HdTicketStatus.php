<?php

namespace App\Enums;

enum HdTicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::IN_PROGRESS => 'Em Andamento',
            self::PENDING => 'Pendente',
            self::RESOLVED => 'Resolvido',
            self::CLOSED => 'Fechado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'info',
            self::IN_PROGRESS => 'warning',
            self::PENDING => 'orange',
            self::RESOLVED => 'success',
            self::CLOSED => 'gray',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Statuses this one can transition to. Reopen (CLOSED → IN_PROGRESS) is allowed here
     * but gated by manager role + mandatory comment in the controller layer.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::PENDING, self::RESOLVED, self::CANCELLED],
            self::PENDING => [self::IN_PROGRESS, self::CANCELLED],
            self::RESOLVED => [self::CLOSED, self::IN_PROGRESS],
            self::CLOSED => [self::IN_PROGRESS],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CLOSED, self::CANCELLED], true);
    }

    /**
     * Statuses considered terminal (no further transitions outside of reopen).
     *
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::CLOSED, self::CANCELLED];
    }

    /**
     * @return array<string, string> [value => label]
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * @return array<string, string> [value => color]
     */
    public static function colors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->color()])
            ->all();
    }

    /**
     * @return array<string, array<int, string>> [status => [valid next statuses as strings]]
     */
    public static function transitionMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [
                $c->value => array_map(fn (self $t) => $t->value, $c->allowedTransitions()),
            ])
            ->all();
    }
}
