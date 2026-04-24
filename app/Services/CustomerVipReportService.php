<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios de faturamento dos clientes VIP (comparativo ano-a-ano).
 *
 * Regra de faturamento idêntica à do classificador:
 *   movement_code = 2 soma, movement_code = 6 AND entry_exit = 'E' subtrai.
 *
 * A comparação é sempre "mesmo período" para manter YoY honesto:
 *   - mode='ytd' (default) — janeiro até hoje do ano atual VS janeiro até
 *     mesma data do ano anterior. Usado na listagem e no card executivo.
 *   - mode='full_year' — ano inteiro VS ano inteiro. Útil quando o usuário
 *     seleciona um ano fechado (ex: 2024 vs 2023 completo).
 */
class CustomerVipReportService
{
    /**
     * YoY mês a mês para um cliente.
     *
     * @return array{
     *     current: array{year:int, total:float, orders:int, monthly: array<int,float>, period_end: string},
     *     previous: array{year:int, total:float, orders:int, monthly: array<int,float>, period_end: string},
     *     delta: array{absolute: float, pct: float|null},
     *     mode: string
     * }
     */
    public function yearOverYear(Customer $customer, int $year, string $mode = 'ytd'): array
    {
        $cpf = $customer->cpf;
        if (! $cpf) {
            return $this->emptyPayload($year, $mode);
        }

        [$currentStart, $currentEnd, $previousStart, $previousEnd] = $this->resolveWindows($year, $mode);

        $current = $this->computePeriod($cpf, $currentStart, $currentEnd);
        $previous = $this->computePeriod($cpf, $previousStart, $previousEnd);

        $absolute = round($current['total'] - $previous['total'], 2);
        $pct = $previous['total'] > 0
            ? round(($absolute / $previous['total']) * 100, 2)
            : null;

        return [
            'current' => $current + ['year' => $year, 'period_end' => $currentEnd],
            'previous' => $previous + ['year' => $year - 1, 'period_end' => $previousEnd],
            'delta' => ['absolute' => $absolute, 'pct' => $pct],
            'mode' => $mode,
        ];
    }

    /**
     * Total simples do faturamento do cliente num período arbitrário.
     * Útil pra fragments de UI e validação cruzada.
     */
    public function revenueInRange(Customer $customer, string $start, string $end): float
    {
        if (! $customer->cpf) {
            return 0.0;
        }

        return (float) $this->sumNetRevenue($customer->cpf, $start, $end);
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /**
     * @return array{year:int, total:float, orders:int, monthly: array<int,float>}
     */
    private function computePeriod(string $cpf, string $start, string $end): array
    {
        $rows = DB::table('movements')
            ->select([
                DB::raw('MONTH(movement_date) as month'),
                DB::raw(
                    'SUM(CASE WHEN movement_code = 2 THEN net_value '
                    ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -net_value "
                    .'ELSE 0 END) as revenue'
                ),
                DB::raw('COUNT(DISTINCT invoice_number) as orders'),
            ])
            ->where('cpf_customer', $cpf)
            ->whereBetween('movement_date', [$start, $end])
            ->where(function ($q) {
                $q->where('movement_code', 2)
                    ->orWhere(function ($qq) {
                        $qq->where('movement_code', 6)->where('entry_exit', 'E');
                    });
            })
            ->groupBy(DB::raw('MONTH(movement_date)'))
            ->get();

        $monthly = array_fill(1, 12, 0.0);
        $total = 0.0;
        $orders = 0;
        foreach ($rows as $r) {
            $month = (int) $r->month;
            $revenue = (float) $r->revenue;
            $monthly[$month] = round($revenue, 2);
            $total += $revenue;
            $orders += (int) $r->orders;
        }

        return [
            'total' => round($total, 2),
            'orders' => $orders,
            'monthly' => $monthly,
        ];
    }

    /**
     * Resolve janelas [currentStart, currentEnd, previousStart, previousEnd].
     */
    private function resolveWindows(int $year, string $mode): array
    {
        if ($mode === 'full_year') {
            return [
                sprintf('%d-01-01', $year),
                sprintf('%d-12-31', $year),
                sprintf('%d-01-01', $year - 1),
                sprintf('%d-12-31', $year - 1),
            ];
        }

        // ytd: se year < ano atual, comparar ano inteiro (fechado); se year ==
        // ano atual, comparar jan..hoje VS jan..mesma-data-ano-anterior.
        $today = now();
        $currentYear = (int) $today->format('Y');

        if ($year < $currentYear) {
            return [
                sprintf('%d-01-01', $year),
                sprintf('%d-12-31', $year),
                sprintf('%d-01-01', $year - 1),
                sprintf('%d-12-31', $year - 1),
            ];
        }

        $cutoff = $today->format('m-d');

        return [
            sprintf('%d-01-01', $year),
            sprintf('%d-%s', $year, $cutoff),
            sprintf('%d-01-01', $year - 1),
            sprintf('%d-%s', $year - 1, $cutoff),
        ];
    }

    private function sumNetRevenue(string $cpf, string $start, string $end): float
    {
        $value = DB::table('movements')
            ->where('cpf_customer', $cpf)
            ->whereBetween('movement_date', [$start, $end])
            ->where(function ($q) {
                $q->where('movement_code', 2)
                    ->orWhere(function ($qq) {
                        $qq->where('movement_code', 6)->where('entry_exit', 'E');
                    });
            })
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN movement_code = 2 THEN net_value '
                ."WHEN movement_code = 6 AND entry_exit = 'E' THEN -net_value "
                .'ELSE 0 END), 0) as total'
            )
            ->value('total');

        return (float) $value;
    }

    private function emptyPayload(int $year, string $mode): array
    {
        $empty = [
            'total' => 0.0,
            'orders' => 0,
            'monthly' => array_fill(1, 12, 0.0),
        ];

        return [
            'current' => $empty + ['year' => $year, 'period_end' => sprintf('%d-12-31', $year)],
            'previous' => $empty + ['year' => $year - 1, 'period_end' => sprintf('%d-12-31', $year - 1)],
            'delta' => ['absolute' => 0.0, 'pct' => null],
            'mode' => $mode,
        ];
    }
}
