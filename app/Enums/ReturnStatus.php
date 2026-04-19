<?php

namespace App\Enums;

/**
 * Ciclo de vida de uma solicitação de devolução/troca de e-commerce.
 * Paridade com o enum `status` da v1 (adms_returns), normalizado em
 * inglês para consistência com o resto da v2.
 *
 *   pending (Pendente)
 *       ├──► approved (Aprovado)
 *       │       ├──► pending (voltar)
 *       │       ├──► awaiting_product (Aguardando Produto)
 *       │       │       ├──► approved (voltar)
 *       │       │       ├──► processing (Processando)
 *       │       │       │       ├──► awaiting_product (voltar)
 *       │       │       │       ├──► completed (Completo) ← terminal
 *       │       │       │       └──► cancelled (Cancelado) ← terminal
 *       │       │       ├──► completed
 *       │       │       └──► cancelled
 *       │       ├──► processing
 *       │       └──► cancelled
 *       └──► cancelled
 *
 * Regras adicionais (validadas em ReturnTransitionService):
 *  - pending → approved: exige APPROVE_RETURNS
 *  - awaiting_product/processing → completed: exige PROCESS_RETURNS
 *  - * → cancelled: exige APPROVE_RETURNS + note (motivo do cancelamento)
 *  - voltas (ex: approved → pending): exige APPROVE_RETURNS
 */
enum ReturnStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case AWAITING_PRODUCT = 'awaiting_product';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::APPROVED => 'Aprovado',
            self::AWAITING_PRODUCT => 'Aguardando Produto',
            self::PROCESSING => 'Processando',
            self::COMPLETED => 'Completo',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::AWAITING_PRODUCT => 'orange',
            self::PROCESSING => 'purple',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [
                self::APPROVED,
                self::CANCELLED,
            ],
            self::APPROVED => [
                self::PENDING,
                self::AWAITING_PRODUCT,
                self::PROCESSING,
                self::CANCELLED,
            ],
            self::AWAITING_PRODUCT => [
                self::APPROVED,
                self::PROCESSING,
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::PROCESSING => [
                self::AWAITING_PRODUCT,
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::CANCELLED];
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [
            self::PENDING,
            self::APPROVED,
            self::AWAITING_PRODUCT,
            self::PROCESSING,
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
