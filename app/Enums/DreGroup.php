<?php

namespace App\Enums;

/**
 * Grupos agregadores do DRE (Demonstração do Resultado do Exercício) —
 * padrão BR simplificado baseado em CPC/Pronunciamentos Contábeis.
 *
 * Cada AccountingClass pertence a exatamente um grupo. A DRE é calculada
 * agregando lançamentos por grupo, na ordem em que aparecem aqui:
 *
 *   Receita Bruta
 *   (-) Deduções
 *   = Receita Líquida
 *   (-) CMV
 *   = Lucro Bruto
 *   (-) Despesas Comerciais
 *   (-) Despesas Administrativas
 *   (-) Despesas Gerais
 *   (+) Outras Receitas Operacionais
 *   (-) Outras Despesas Operacionais
 *   = Lucro Operacional (EBIT)
 *   (+) Receitas Financeiras
 *   (-) Despesas Financeiras
 *   = Resultado Antes dos Impostos
 *   (-) Impostos sobre o Lucro
 *   = Lucro Líquido
 *
 * `naturalNature()` devolve a natureza contábil esperada do grupo — útil
 * para validar coerência no cadastro e para sinalizar quando uma conta foge
 * da natureza natural (ex: desconto obtido é redutor de despesa financeira).
 */
enum DreGroup: string
{
    case RECEITA_BRUTA = 'receita_bruta';
    case DEDUCOES = 'deducoes';
    case CMV = 'cmv';
    case DESPESAS_COMERCIAIS = 'despesas_comerciais';
    case DESPESAS_ADMINISTRATIVAS = 'despesas_administrativas';
    case DESPESAS_GERAIS = 'despesas_gerais';
    case OUTRAS_RECEITAS_OP = 'outras_receitas_op';
    case OUTRAS_DESPESAS_OP = 'outras_despesas_op';
    case RECEITAS_FINANCEIRAS = 'receitas_financeiras';
    case DESPESAS_FINANCEIRAS = 'despesas_financeiras';
    case IMPOSTOS_SOBRE_LUCRO = 'impostos_sobre_lucro';

    public function label(): string
    {
        return match ($this) {
            self::RECEITA_BRUTA => 'Receita Bruta',
            self::DEDUCOES => 'Deduções da Receita',
            self::CMV => 'Custo das Mercadorias / Serviços',
            self::DESPESAS_COMERCIAIS => 'Despesas Comerciais',
            self::DESPESAS_ADMINISTRATIVAS => 'Despesas Administrativas',
            self::DESPESAS_GERAIS => 'Despesas Gerais',
            self::OUTRAS_RECEITAS_OP => 'Outras Receitas Operacionais',
            self::OUTRAS_DESPESAS_OP => 'Outras Despesas Operacionais',
            self::RECEITAS_FINANCEIRAS => 'Receitas Financeiras',
            self::DESPESAS_FINANCEIRAS => 'Despesas Financeiras',
            self::IMPOSTOS_SOBRE_LUCRO => 'Impostos sobre o Lucro',
        };
    }

    /**
     * Ordem canônica no DRE (1..N). Usada para ordenar seções do relatório.
     */
    public function dreOrder(): int
    {
        return match ($this) {
            self::RECEITA_BRUTA => 1,
            self::DEDUCOES => 2,
            self::CMV => 3,
            self::DESPESAS_COMERCIAIS => 4,
            self::DESPESAS_ADMINISTRATIVAS => 5,
            self::DESPESAS_GERAIS => 6,
            self::OUTRAS_RECEITAS_OP => 7,
            self::OUTRAS_DESPESAS_OP => 8,
            self::RECEITAS_FINANCEIRAS => 9,
            self::DESPESAS_FINANCEIRAS => 10,
            self::IMPOSTOS_SOBRE_LUCRO => 11,
        };
    }

    /**
     * Natureza contábil natural do grupo. Contas desse grupo *tipicamente*
     * (mas não obrigatoriamente) seguem esta natureza.
     */
    public function naturalNature(): AccountingNature
    {
        return match ($this) {
            self::RECEITA_BRUTA,
            self::OUTRAS_RECEITAS_OP,
            self::RECEITAS_FINANCEIRAS => AccountingNature::CREDIT,

            self::DEDUCOES,
            self::CMV,
            self::DESPESAS_COMERCIAIS,
            self::DESPESAS_ADMINISTRATIVAS,
            self::DESPESAS_GERAIS,
            self::OUTRAS_DESPESAS_OP,
            self::DESPESAS_FINANCEIRAS,
            self::IMPOSTOS_SOBRE_LUCRO => AccountingNature::DEBIT,
        };
    }

    /**
     * Indica se o grupo aumenta o resultado (soma) ou reduz (subtrai).
     */
    public function increasesResult(): bool
    {
        return $this->naturalNature() === AccountingNature::CREDIT;
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEITA_BRUTA,
            self::OUTRAS_RECEITAS_OP,
            self::RECEITAS_FINANCEIRAS => 'green',

            self::DEDUCOES,
            self::IMPOSTOS_SOBRE_LUCRO => 'yellow',

            self::CMV => 'orange',

            self::DESPESAS_COMERCIAIS,
            self::DESPESAS_ADMINISTRATIVAS,
            self::DESPESAS_GERAIS,
            self::OUTRAS_DESPESAS_OP,
            self::DESPESAS_FINANCEIRAS => 'red',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn ($c) => $c->value, self::cases()),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }

    public static function labels(): array
    {
        return array_combine(
            array_map(fn ($c) => $c->value, self::cases()),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }
}
