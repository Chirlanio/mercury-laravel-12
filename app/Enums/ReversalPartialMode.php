<?php

namespace App\Enums;

/**
 * Modo de estorno parcial (só aplicável quando ReversalType::PARTIAL):
 *
 *  - by_value: usuário informa valor correto. Estorno = original - correto.
 *    Usado quando o produto foi registrado com valor errado mas a venda
 *    continua válida pelo valor correto.
 *
 *  - by_item: usuário seleciona itens (linhas de movements) da NF para
 *    estornar. Estorno = soma dos realized_value dos itens selecionados.
 *    Gera registros em reversal_items vinculando movement_id.
 */
enum ReversalPartialMode: string
{
    case BY_VALUE = 'by_value';
    case BY_ITEM = 'by_item';

    public function label(): string
    {
        return match ($this) {
            self::BY_VALUE => 'Por Valor',
            self::BY_ITEM => 'Por Produto',
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
