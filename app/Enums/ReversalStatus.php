<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma solicitação de estorno. Replica a state machine da v1
 * (adms_sits_estornos) com 6 estados:
 *
 *   pending_reversal (Aguardando Estorno)
 *       ├──► pending_authorization (Aguardando Autorização)
 *       │         ├──► pending_reversal (voltar)
 *       │         ├──► authorized (Autorizado)
 *       │         │        ├──► pending_authorization (voltar)
 *       │         │        ├──► pending_finance (Aguardando Financeira)
 *       │         │        │        ├──► authorized (voltar)
 *       │         │        │        ├──► reversed (Estornado) ← terminal
 *       │         │        │        └──► cancelled (Cancelado) ← terminal
 *       │         │        ├──► reversed
 *       │         │        └──► cancelled
 *       │         ├──► reversed
 *       │         └──► cancelled
 *       ├──► reversed
 *       └──► cancelled
 *
 * Regras adicionais (validadas em ReversalTransitionService):
 *  - pending_reversal → pending_authorization exige CREATE_REVERSALS
 *  - pending_authorization → authorized exige APPROVE_REVERSALS
 *  - pending_finance → reversed exige PROCESS_REVERSALS
 *  - * → cancelled exige note (motivo do cancelamento) + APPROVE_REVERSALS
 */
enum ReversalStatus: string
{
    case PENDING_REVERSAL = 'pending_reversal';
    case PENDING_AUTHORIZATION = 'pending_authorization';
    case AUTHORIZED = 'authorized';
    case PENDING_FINANCE = 'pending_finance';
    case REVERSED = 'reversed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVERSAL => 'Aguardando Estorno',
            self::PENDING_AUTHORIZATION => 'Aguardando Autorização',
            self::AUTHORIZED => 'Autorizado',
            self::PENDING_FINANCE => 'Aguardando Financeira',
            self::REVERSED => 'Estornado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_REVERSAL => 'warning',
            self::PENDING_AUTHORIZATION => 'info',
            self::AUTHORIZED => 'purple',
            self::PENDING_FINANCE => 'orange',
            self::REVERSED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING_REVERSAL => [
                self::PENDING_AUTHORIZATION,
                self::REVERSED,
                self::CANCELLED,
            ],
            self::PENDING_AUTHORIZATION => [
                self::PENDING_REVERSAL,
                self::AUTHORIZED,
                self::REVERSED,
                self::CANCELLED,
            ],
            self::AUTHORIZED => [
                self::PENDING_AUTHORIZATION,
                self::PENDING_FINANCE,
                self::REVERSED,
                self::CANCELLED,
            ],
            self::PENDING_FINANCE => [
                self::AUTHORIZED,
                self::REVERSED,
                self::CANCELLED,
            ],
            self::REVERSED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::REVERSED, self::CANCELLED], true);
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::REVERSED, self::CANCELLED];
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [
            self::PENDING_REVERSAL,
            self::PENDING_AUTHORIZATION,
            self::AUTHORIZED,
            self::PENDING_FINANCE,
        ];
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

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->color()])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
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
