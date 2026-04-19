<?php

namespace App\Enums;

/**
 * Natureza contábil da conta — define o lado natural do saldo.
 *
 * - DEBIT: saldo natural devedor. Aumenta com débitos, diminui com créditos.
 *   Ex: Despesas, Custos, Ativos, Impostos.
 * - CREDIT: saldo natural credor. Aumenta com créditos, diminui com débitos.
 *   Ex: Receitas, Passivos, Patrimônio Líquido.
 *
 * No DRE: receitas são CREDIT; deduções, custos e despesas são DEBIT.
 */
enum AccountingNature: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::DEBIT => 'Devedora',
            self::CREDIT => 'Credora',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::DEBIT => 'D',
            self::CREDIT => 'C',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEBIT => 'red',
            self::CREDIT => 'green',
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
