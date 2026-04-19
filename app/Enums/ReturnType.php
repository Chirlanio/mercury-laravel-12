<?php

namespace App\Enums;

/**
 * Tipo de devolução (paridade v1 — coluna `type` de adms_returns):
 *
 *  - troca: cliente devolve o produto e recebe outro. O envio do
 *    substituto é registrado como uma nova venda no sistema — Return
 *    cobre apenas o que voltou ao estoque.
 *
 *  - estorno: cliente devolve e recebe reembolso financeiro (PIX,
 *    cartão). O processamento do estorno em si é manual pelo financeiro
 *    (o módulo só registra e notifica).
 *
 *  - credito: cliente recebe crédito para uso futuro na loja, sem
 *    movimentação financeira.
 */
enum ReturnType: string
{
    case TROCA = 'troca';
    case ESTORNO = 'estorno';
    case CREDITO = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::TROCA => 'Troca',
            self::ESTORNO => 'Estorno',
            self::CREDITO => 'Crédito',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TROCA => 'info',
            self::ESTORNO => 'danger',
            self::CREDITO => 'purple',
        };
    }

    /**
     * Indica se o tipo exige valor de reembolso (refund_amount).
     * Apenas estorno e credito exigem — troca não tem valor financeiro.
     */
    public function requiresRefundAmount(): bool
    {
        return in_array($this, [self::ESTORNO, self::CREDITO], true);
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
