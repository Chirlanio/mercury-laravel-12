<?php

namespace App\Enums;

/**
 * Tipo do upload de orçamento — regra de versionamento difere:
 *  - NOVO: redefinição completa do orçamento. Incrementa major (1.0 → 2.0).
 *  - AJUSTE: correção pontual. Incrementa minor (1.0 → 1.01).
 *
 * Ano diferente do último upload sempre reseta para 1.0 (ambos os casos).
 */
enum BudgetUploadType: string
{
    case NOVO = 'novo';
    case AJUSTE = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::NOVO => 'Novo orçamento',
            self::AJUSTE => 'Ajuste',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NOVO => 'Redefinição completa do orçamento — incrementa a versão principal (1.0 → 2.0)',
            self::AJUSTE => 'Correção pontual — incrementa a sub-versão (1.0 → 1.01)',
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
