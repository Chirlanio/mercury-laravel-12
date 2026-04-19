<?php

namespace App\Services;

use App\Models\BudgetUpload;
use Illuminate\Support\Facades\DB;

/**
 * Agregações de consumo previsto × realizado para um BudgetUpload.
 *
 * "Previsto" vem de budget_items (somas dos 12 meses). "Realizado"
 * vem de order_payments vinculadas a esses items via budget_item_id
 * (FK adicionada na Fase 3 C1).
 *
 * O consumo considera apenas OPs com status != 'backlog' (efetivamente
 * comprometidas) para evitar dupla contagem. Decisão: não filtra por
 * date_payment — o orçamento é anual e a OP está "consumida" uma vez
 * criada em modo diferente de backlog.
 */
class BudgetConsumptionService
{
    /**
     * Retorna estrutura completa de consumo para o budget.
     *
     * @return array{
     *   totals: array{forecast: float, realized: float, available: float, utilization_pct: float},
     *   by_item: array,
     *   by_cost_center: array,
     *   by_accounting_class: array,
     *   by_month: array,
     * }
     */
    public function getConsumption(BudgetUpload $budget): array
    {
        $budget->loadMissing([
            'items.accountingClass:id,code,name',
            'items.managementClass:id,code,name',
            'items.costCenter:id,code,name',
            'items.store:id,code,name',
        ]);

        $items = $budget->items;
        $itemIds = $items->pluck('id')->all();

        // Realizado por item — 1 query agregada
        $realizedByItem = $this->aggregateRealizedByItem($itemIds);

        // Monta estrutura by_item com %
        $byItem = [];
        $forecastTotal = 0.0;
        $realizedTotal = 0.0;

        foreach ($items as $item) {
            $forecast = (float) $item->year_total;
            $realized = (float) ($realizedByItem[$item->id] ?? 0);
            $forecastTotal += $forecast;
            $realizedTotal += $realized;

            $byItem[] = [
                'id' => $item->id,
                'accounting_class' => $item->accountingClass
                    ? ['id' => $item->accountingClass->id, 'code' => $item->accountingClass->code, 'name' => $item->accountingClass->name]
                    : null,
                'management_class' => $item->managementClass
                    ? ['id' => $item->managementClass->id, 'code' => $item->managementClass->code, 'name' => $item->managementClass->name]
                    : null,
                'cost_center' => $item->costCenter
                    ? ['id' => $item->costCenter->id, 'code' => $item->costCenter->code, 'name' => $item->costCenter->name]
                    : null,
                'store' => $item->store
                    ? ['id' => $item->store->id, 'code' => $item->store->code, 'name' => $item->store->name]
                    : null,
                'supplier' => $item->supplier,
                'forecast' => round($forecast, 2),
                'realized' => round($realized, 2),
                'available' => round($forecast - $realized, 2),
                'utilization_pct' => $forecast > 0
                    ? round(($realized / $forecast) * 100, 2)
                    : 0.0,
                'status' => $this->utilizationStatus($forecast, $realized),
            ];
        }

        return [
            'totals' => [
                'forecast' => round($forecastTotal, 2),
                'realized' => round($realizedTotal, 2),
                'available' => round($forecastTotal - $realizedTotal, 2),
                'utilization_pct' => $forecastTotal > 0
                    ? round(($realizedTotal / $forecastTotal) * 100, 2)
                    : 0.0,
            ],
            'by_item' => $byItem,
            'by_cost_center' => $this->aggregateByDimension($byItem, 'cost_center'),
            'by_accounting_class' => $this->aggregateByDimension($byItem, 'accounting_class'),
            'by_month' => $this->aggregateByMonth($budget, $itemIds),
        ];
    }

    /**
     * Agrega realizado por budget_item_id. Considera apenas OPs não-backlog
     * e não-deletadas.
     *
     * @param  array<int>  $itemIds
     * @return array<int, float>  [item_id => realized_sum]
     */
    protected function aggregateRealizedByItem(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        return DB::table('order_payments')
            ->whereIn('budget_item_id', $itemIds)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'backlog')
            ->selectRaw('budget_item_id, COALESCE(SUM(total_value), 0) as total')
            ->groupBy('budget_item_id')
            ->pluck('total', 'budget_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Agrupa by_item por dimensão (cost_center ou accounting_class).
     *
     * @return array
     */
    protected function aggregateByDimension(array $byItem, string $dimension): array
    {
        $map = [];
        foreach ($byItem as $row) {
            $dim = $row[$dimension] ?? null;
            if (! $dim) {
                continue;
            }
            $key = $dim['id'];

            if (! isset($map[$key])) {
                $map[$key] = [
                    'id' => $dim['id'],
                    'code' => $dim['code'],
                    'name' => $dim['name'],
                    'forecast' => 0.0,
                    'realized' => 0.0,
                    'items_count' => 0,
                ];
            }

            $map[$key]['forecast'] += $row['forecast'];
            $map[$key]['realized'] += $row['realized'];
            $map[$key]['items_count']++;
        }

        $result = array_map(function ($agg) {
            $agg['available'] = round($agg['forecast'] - $agg['realized'], 2);
            $agg['forecast'] = round($agg['forecast'], 2);
            $agg['realized'] = round($agg['realized'], 2);
            $agg['utilization_pct'] = $agg['forecast'] > 0
                ? round(($agg['realized'] / $agg['forecast']) * 100, 2)
                : 0.0;
            $agg['status'] = $this->utilizationStatus($agg['forecast'], $agg['realized']);

            return $agg;
        }, array_values($map));

        // Ordena por % utilização DESC para destacar os mais consumidos
        usort($result, fn ($a, $b) => $b['utilization_pct'] <=> $a['utilization_pct']);

        return $result;
    }

    /**
     * Agrega previsto × realizado por mês (1..12).
     * Previsto: soma das colunas month_NN_value de todos os items.
     * Realizado: soma de OPs vinculadas, agrupadas pelo mês do date_payment.
     *
     * @return array<int, array{month: int, forecast: float, realized: float}>
     */
    protected function aggregateByMonth(BudgetUpload $budget, array $itemIds): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';
            $forecastAgg = DB::table('budget_items')
                ->where('budget_upload_id', $budget->id)
                ->sum($col);
            $months[$m] = [
                'month' => $m,
                'forecast' => round((float) $forecastAgg, 2),
                'realized' => 0.0,
            ];
        }

        if (! empty($itemIds)) {
            // Driver-agnostic: extrai o mês no PHP em vez de no SQL.
            // Trade-off aceito: transporta uma row por OP do banco, mas
            // funciona consistentemente em SQLite (testes) + MySQL/Postgres.
            $ops = DB::table('order_payments')
                ->whereIn('budget_item_id', $itemIds)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'backlog')
                ->whereNotNull('date_payment')
                ->whereYear('date_payment', $budget->year)
                ->select('date_payment', 'total_value')
                ->get();

            foreach ($ops as $op) {
                $m = (int) date('n', strtotime((string) $op->date_payment));
                if ($m >= 1 && $m <= 12 && isset($months[$m])) {
                    $months[$m]['realized'] += (float) $op->total_value;
                }
            }

            // Arredonda no fim
            for ($m = 1; $m <= 12; $m++) {
                $months[$m]['realized'] = round($months[$m]['realized'], 2);
            }
        }

        return array_values($months);
    }

    /**
     * Semáforo de utilização:
     *   - 'ok': < 70% do previsto
     *   - 'warning': 70% - 99.99%
     *   - 'exceeded': >= 100% (consumo atingiu ou passou do previsto)
     */
    protected function utilizationStatus(float $forecast, float $realized): string
    {
        if ($forecast <= 0) {
            return $realized > 0 ? 'exceeded' : 'ok';
        }

        $pct = ($realized / $forecast) * 100;

        if ($pct >= 100) {
            return 'exceeded';
        }
        if ($pct >= 70) {
            return 'warning';
        }

        return 'ok';
    }
}
