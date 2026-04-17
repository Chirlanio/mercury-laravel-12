<?php

namespace App\Enums;

/**
 * Tipo de estorno (paridade v1 — adms_tps_estornos):
 *
 *  - total: estorna a NF inteira. amount_original = sale_total,
 *    amount_correct = 0, amount_reversal = sale_total.
 *
 *  - partial: estorna parte da NF. Sub-divide em dois modos (ver
 *    ReversalPartialMode):
 *      - by_value: usuário informa amount_correct (valor que deveria
 *        ter sido cobrado). amount_reversal = amount_original - amount_correct.
 *      - by_item: usuário seleciona linhas de movements. amount_reversal
 *        é a soma dos realized_value dos itens selecionados.
 */
enum ReversalType: string
{
    case TOTAL = 'total';
    case PARTIAL = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::TOTAL => 'Total',
            self::PARTIAL => 'Parcial',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TOTAL => 'danger',
            self::PARTIAL => 'warning',
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
}
