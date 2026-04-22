<?php

namespace App\Services\DRE;

use App\Models\DreManagementLine;
use Illuminate\Support\Collection;

/**
 * Aplica subtotais sobre uma matriz já agregada por linha analítica.
 *
 * Regra (`docs/dre-arquitetura.md §5`): para cada `DreManagementLine` com
 * `is_subtotal=true`, o valor em cada mês é a soma de todas as linhas
 * **analíticas** (`is_subtotal=false`) com `sort_order <= accumulate_until_sort_order`.
 *
 * Equivale ao DAX original do Power BI:
 *   `FILTER(ALL(D_Contabil), D_Contabil[Ordem] <= ordem_atual)`
 * com a diferença de que subtotais não somam outros subtotais — só
 * as analíticas. Isso permite EBITDA (sort=13) acumular até 13 incluindo
 * Headcount (analytical, sort=13) sem duplicar com Lucro Líquido
 * (subtotal, sort=17, acumula até 16).
 *
 * Entrada: matriz analítica keyed por `management_line_id` → `year_month` →
 *   `['actual' => float, 'budget' => float, 'previous_year' => float]`
 *
 * Saída: matriz completa com todas as linhas preenchidas (analíticas copiadas
 * + subtotais computados).
 */
class DreSubtotalCalculator
{
    /**
     * @param  array<int, array<string, array{actual: float, budget: float, previous_year: float}>>  $analyticalMatrix
     * @param  Collection<int, DreManagementLine>  $managementLines
     * @return array<int, array<string, array{actual: float, budget: float, previous_year: float}>>
     */
    public function calculate(array $analyticalMatrix, Collection $managementLines): array
    {
        $result = [];

        // Ordena as linhas por sort_order + is_subtotal (analíticas antes).
        $ordered = $managementLines
            ->sortBy([
                fn ($a, $b) => $a->sort_order <=> $b->sort_order,
                fn ($a, $b) => ((int) $a->is_subtotal) <=> ((int) $b->is_subtotal),
            ])
            ->values();

        // Descobre o conjunto de year_months presentes na matriz original —
        // subtotais só aparecem em meses onde alguém contribuiu.
        $allMonths = $this->collectMonths($analyticalMatrix);

        foreach ($ordered as $line) {
            if (! $line->is_subtotal) {
                // Analítica: copia valor da matriz (ou zero quando não veio).
                $result[$line->id] = $this->fillMonths(
                    $analyticalMatrix[$line->id] ?? [],
                    $allMonths,
                );
                continue;
            }

            // Subtotal — soma analíticas com sort_order <= accumulate_until.
            $accumulateUntil = $line->accumulate_until_sort_order ?? $line->sort_order;
            $contributorIds = $ordered
                ->filter(fn (DreManagementLine $l) => ! $l->is_subtotal && $l->sort_order <= $accumulateUntil)
                ->pluck('id')
                ->all();

            $result[$line->id] = $this->sumLines($analyticalMatrix, $contributorIds, $allMonths);
        }

        return $result;
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    /**
     * @param  array<int, array<string, array{actual: float, budget: float, previous_year: float}>>  $matrix
     * @return array<int, string>  year_months únicos, ordenados.
     */
    private function collectMonths(array $matrix): array
    {
        $months = [];
        foreach ($matrix as $byLine) {
            foreach (array_keys($byLine) as $ym) {
                $months[$ym] = true;
            }
        }
        ksort($months);

        return array_keys($months);
    }

    /**
     * @param  array<string, array{actual: float, budget: float, previous_year: float}>  $lineValues
     * @param  array<int, string>  $allMonths
     * @return array<string, array{actual: float, budget: float, previous_year: float}>
     */
    private function fillMonths(array $lineValues, array $allMonths): array
    {
        $result = [];
        foreach ($allMonths as $ym) {
            $result[$ym] = $lineValues[$ym] ?? ['actual' => 0.0, 'budget' => 0.0, 'previous_year' => 0.0];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, array{actual: float, budget: float, previous_year: float}>>  $matrix
     * @param  array<int, int>  $contributorIds
     * @param  array<int, string>  $allMonths
     * @return array<string, array{actual: float, budget: float, previous_year: float}>
     */
    private function sumLines(array $matrix, array $contributorIds, array $allMonths): array
    {
        $result = [];
        foreach ($allMonths as $ym) {
            $actual = 0.0;
            $budget = 0.0;
            $previousYear = 0.0;
            foreach ($contributorIds as $id) {
                $cell = $matrix[$id][$ym] ?? null;
                if ($cell === null) {
                    continue;
                }
                $actual += (float) $cell['actual'];
                $budget += (float) $cell['budget'];
                $previousYear += (float) $cell['previous_year'];
            }
            $result[$ym] = [
                'actual' => $actual,
                'budget' => $budget,
                'previous_year' => $previousYear,
            ];
        }

        return $result;
    }
}
