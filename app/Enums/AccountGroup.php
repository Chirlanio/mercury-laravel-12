<?php

namespace App\Enums;

/**
 * Grupo macro do plano contábil do ERP.
 *
 * Derivado do primeiro segmento do `code` (ex: "3.1.1.01.00012" → 3):
 *
 *   1 = Ativo
 *   2 = Passivo
 *   3 = Receitas
 *   4 = Custos e Despesas
 *   5 = Resultado do Exercício
 *
 * Contas do grupo 8 (Centros de Custo) NÃO entram aqui — vão para a
 * tabela `cost_centers`. Contas dos grupos 3, 4 e 5 são "contas de
 * resultado" (aparecem na DRE).
 */
enum AccountGroup: int
{
    case ATIVO = 1;
    case PASSIVO = 2;
    case RECEITAS = 3;
    case CUSTOS_DESPESAS = 4;
    case RESULTADO = 5;

    public function label(): string
    {
        return match ($this) {
            self::ATIVO => 'Ativo',
            self::PASSIVO => 'Passivo',
            self::RECEITAS => 'Receitas',
            self::CUSTOS_DESPESAS => 'Custos e Despesas',
            self::RESULTADO => 'Resultado do Exercício',
        };
    }

    /**
     * true para grupos que aparecem no DRE (3, 4, 5).
     */
    public function isResultGroup(): bool
    {
        return match ($this) {
            self::RECEITAS, self::CUSTOS_DESPESAS, self::RESULTADO => true,
            self::ATIVO, self::PASSIVO => false,
        };
    }

    /**
     * Deriva do primeiro segmento do code. Ex: "3.1.1.01.00012" → RECEITAS.
     * Retorna null quando o segmento não é reconhecido (grupo 8 = CC, ou
     * code inválido).
     */
    public static function fromCode(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        $first = explode('.', $code)[0] ?? '';
        if (! ctype_digit($first)) {
            return null;
        }

        return self::tryFrom((int) $first);
    }
}
