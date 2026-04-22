<?php

namespace Tests\Unit\Services\DRE;

use App\Models\DreManagementLine;
use App\Services\DRE\DreSubtotalCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Cobre `DreSubtotalCalculator` — unit puro, sem DB, sem RefreshDatabase.
 * Os objetos `DreManagementLine` são criados em memória sem persistir.
 */
class DreSubtotalCalculatorTest extends TestCase
{
    public function test_simple_subtotal_sums_two_analytical_lines(): void
    {
        $rev = $this->line(id: 1, sort: 10, isSubtotal: false);
        $ded = $this->line(id: 2, sort: 20, isSubtotal: false);
        $subtotal = $this->line(id: 3, sort: 30, isSubtotal: true, accumulate: 20);

        $matrix = [
            1 => ['2026-01' => ['actual' => 100.0, 'budget' => 90.0, 'previous_year' => 80.0]],
            2 => ['2026-01' => ['actual' => -10.0, 'budget' => -8.0, 'previous_year' => -5.0]],
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$rev, $ded, $subtotal]),
        );

        $this->assertSame(90.0, $result[3]['2026-01']['actual']);
        $this->assertSame(82.0, $result[3]['2026-01']['budget']);
        $this->assertSame(75.0, $result[3]['2026-01']['previous_year']);
    }

    public function test_subtotal_does_not_include_other_subtotals(): void
    {
        $rev = $this->line(id: 1, sort: 10, isSubtotal: false);
        $ded = $this->line(id: 2, sort: 20, isSubtotal: false);
        $receitaLiquida = $this->line(id: 3, sort: 30, isSubtotal: true, accumulate: 20); // acumula até 20
        $tributos = $this->line(id: 4, sort: 40, isSubtotal: false);
        $rol = $this->line(id: 5, sort: 50, isSubtotal: true, accumulate: 40);            // acumula até 40

        $matrix = [
            1 => ['2026-01' => ['actual' => 100.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            2 => ['2026-01' => ['actual' => -10.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            4 => ['2026-01' => ['actual' => -5.0, 'budget' => 0.0, 'previous_year' => 0.0]],
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$rev, $ded, $receitaLiquida, $tributos, $rol]),
        );

        // ROL soma rev + ded + tributos (não inclui o subtotal intermediário id=3).
        $this->assertSame(85.0, $result[5]['2026-01']['actual']);
        // Receita Líquida não deve ter sido duplicada.
        $this->assertSame(90.0, $result[3]['2026-01']['actual']);
    }

    public function test_subtotal_ignores_lines_after_accumulate_until(): void
    {
        $a1 = $this->line(id: 1, sort: 10, isSubtotal: false);
        $a2 = $this->line(id: 2, sort: 20, isSubtotal: false);
        $a3 = $this->line(id: 3, sort: 30, isSubtotal: false);
        $subtotal = $this->line(id: 4, sort: 40, isSubtotal: true, accumulate: 20); // só até sort 20

        $matrix = [
            1 => ['2026-01' => ['actual' => 100.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            2 => ['2026-01' => ['actual' => 50.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            3 => ['2026-01' => ['actual' => 999.0, 'budget' => 0.0, 'previous_year' => 0.0]], // não deve entrar
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$a1, $a2, $a3, $subtotal]),
        );

        $this->assertSame(150.0, $result[4]['2026-01']['actual']);
    }

    public function test_analytical_line_without_values_is_zero_in_all_months(): void
    {
        $a1 = $this->line(id: 1, sort: 10, isSubtotal: false);
        $a2 = $this->line(id: 2, sort: 20, isSubtotal: false);

        $matrix = [
            1 => ['2026-01' => ['actual' => 100.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            // id=2 ausente.
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$a1, $a2]),
        );

        $this->assertSame(0.0, $result[2]['2026-01']['actual']);
        $this->assertSame(0.0, $result[2]['2026-01']['budget']);
    }

    public function test_ebitda_at_sort_13_and_lucro_liquido_at_sort_17_do_not_duplicate(): void
    {
        // Cenário do prompt anterior: Headcount (id=3, sort=13, analytical) +
        // EBITDA (id=4, sort=13, subtotal, accumulate=13) +
        // Desp.Fin (id=5, sort=15, analytical) + Lucro Líquido (id=6, sort=17,
        // subtotal, accumulate=16).
        $faturamento = $this->line(id: 1, sort: 1, isSubtotal: false);
        $cmv = $this->line(id: 2, sort: 6, isSubtotal: false);
        $headcount = $this->line(id: 3, sort: 13, isSubtotal: false);
        $ebitda = $this->line(id: 4, sort: 13, isSubtotal: true, accumulate: 13);
        $financeiro = $this->line(id: 5, sort: 15, isSubtotal: false);
        $lucro = $this->line(id: 6, sort: 17, isSubtotal: true, accumulate: 16);

        $matrix = [
            1 => ['2026-01' => ['actual' => 1000.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            2 => ['2026-01' => ['actual' => -300.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            3 => ['2026-01' => ['actual' => -200.0, 'budget' => 0.0, 'previous_year' => 0.0]],
            5 => ['2026-01' => ['actual' => -50.0, 'budget' => 0.0, 'previous_year' => 0.0]],
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$faturamento, $cmv, $headcount, $ebitda, $financeiro, $lucro]),
        );

        // EBITDA acumula 1+6+13 = 1000 - 300 - 200 = 500.
        $this->assertSame(500.0, $result[4]['2026-01']['actual']);

        // Lucro Líquido acumula 1+6+13+15 = 500 - 50 = 450 (NÃO soma EBITDA, ele
        // é subtotal). E também NÃO soma duas vezes Headcount.
        $this->assertSame(450.0, $result[6]['2026-01']['actual']);
    }

    public function test_budget_and_previous_year_follow_same_rule(): void
    {
        $rev = $this->line(id: 1, sort: 10, isSubtotal: false);
        $ded = $this->line(id: 2, sort: 20, isSubtotal: false);
        $subtotal = $this->line(id: 3, sort: 30, isSubtotal: true, accumulate: 20);

        $matrix = [
            1 => ['2026-01' => ['actual' => 1.0, 'budget' => 10.0, 'previous_year' => 100.0]],
            2 => ['2026-01' => ['actual' => 2.0, 'budget' => 20.0, 'previous_year' => 200.0]],
        ];

        $result = (new DreSubtotalCalculator())->calculate(
            $matrix,
            collect([$rev, $ded, $subtotal]),
        );

        $this->assertSame(3.0, $result[3]['2026-01']['actual']);
        $this->assertSame(30.0, $result[3]['2026-01']['budget']);
        $this->assertSame(300.0, $result[3]['2026-01']['previous_year']);
    }

    /**
     * Factory de DreManagementLine em memória (não toca no DB).
     */
    private function line(
        int $id,
        int $sort,
        bool $isSubtotal,
        ?int $accumulate = null,
    ): DreManagementLine {
        $line = new DreManagementLine();
        // Force-fill para setar id em model não persistido.
        $line->forceFill([
            'id' => $id,
            'code' => 'L'.str_pad((string) $sort, 3, '0', STR_PAD_LEFT),
            'sort_order' => $sort,
            'is_subtotal' => $isSubtotal,
            'accumulate_until_sort_order' => $accumulate,
            'nature' => $isSubtotal ? 'subtotal' : 'expense',
            'level_1' => 'Test',
        ]);

        return $line;
    }
}
