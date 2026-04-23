<?php

namespace App\Enums;

/**
 * Ciclo de vida de um cupom de desconto.
 *
 *   draft (Rascunho, opcional — salvo sem notificar e-commerce)
 *       └──► requested (Solicitado, e-mail disparado)
 *               ├──► issued (Emitido, coupon_site preenchido pelo e-commerce)
 *               │       ├──► active (Ativo — publicado na plataforma)
 *               │       │       ├──► expired (Expirado — valid_until vencido, via command)
 *               │       │       └──► cancelled (Cancelado)
 *               │       ├──► expired
 *               │       └──► cancelled
 *               └──► cancelled
 *       └──► cancelled
 *
 * Regras adicionais (validadas em CouponTransitionService):
 *  - requested → issued: exige ISSUE_COUPON_CODE + preenchimento do campo coupon_site
 *  - * → cancelled: exige EDIT_COUPONS ou MANAGE_COUPONS + motivo
 *  - expired é sempre automático via command `coupons:expire-stale` (daily)
 *  - terminal: expired, cancelled (não voltam)
 */
enum CouponStatus: string
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case ISSUED = 'issued';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::REQUESTED => 'Solicitado',
            self::ISSUED => 'Emitido',
            self::ACTIVE => 'Ativo',
            self::EXPIRED => 'Expirado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::REQUESTED => 'warning',
            self::ISSUED => 'info',
            self::ACTIVE => 'success',
            self::EXPIRED => 'gray',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [
                self::REQUESTED,
                self::CANCELLED,
            ],
            self::REQUESTED => [
                self::ISSUED,
                self::CANCELLED,
            ],
            self::ISSUED => [
                self::ACTIVE,
                self::EXPIRED,
                self::CANCELLED,
            ],
            self::ACTIVE => [
                self::EXPIRED,
                self::CANCELLED,
            ],
            self::EXPIRED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::EXPIRED, self::CANCELLED], true);
    }

    /**
     * Indica se o cupom é considerado "ativo" para fins de unicidade
     * (scope que bloqueia cadastro duplicado por CPF+tipo+store).
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [
            self::DRAFT,
            self::REQUESTED,
            self::ISSUED,
            self::ACTIVE,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::EXPIRED, self::CANCELLED];
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
