<?php

namespace App\Services;

use App\Models\BudgetUpload;
use Illuminate\Support\Facades\DB;

/**
 * Agregações de consumo previsto × comprometido × realizado para um BudgetUpload.
 *
 * Três grandezas distintas:
 *   - forecast (previsto): soma dos 12 meses dos budget_items
 *   - committed (comprometido): todas as OPs não-deletadas — pipeline
 *     inteira, do backlog ao pago. Representa "o quanto do orçamento
 *     pode ser consumido se nada for cancelado". Alarme mais conservador.
 *   - realized (realizado): apenas OPs com status=done — o que efetivamente
 *     saiu do caixa. Semântica contábil estrita (regime de caixa).
 *
 * committed >= realized sempre. Dashboard exibe ambas — committed é o
 * indicador operacional (saúde do orçamento), realized é a foto contábil.
 *
 * FK em order_payments.budget_item_id adicionada na Fase 3 C1. OPs com
 * budget_item_id=null não entram em nenhuma das contagens.
 */
class BudgetConsumptionService
{
    /**
     * Retorna estrutura completa de consumo para o budget.
     *
     * @return array{
     *   totals: array{forecast: float, committed: float, realized: float, available: float, utilization_pct: float, committed_pct: float},
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

        // 2 queries agregadas — committed (todos não-deletados) + realized (done)
        $committedByItem = $this->aggregateByItem($itemIds, onlyDone: false);
        $realizedByItem = $this->aggregateByItem($itemIds, onlyDone: true);

        $byItem = [];
        $forecastTotal = 0.0;
        $committedTotal = 0.0;
        $realizedTotal = 0.0;

        foreach ($items as $item) {
            $forecast = (float) $item->year_total;
            $committed = (float) ($committedByItem[$item->id] ?? 0);
            $realized = (float) ($realizedByItem[$item->id] ?? 0);
            $forecastTotal += $forecast;
            $committedTotal += $committed;
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
                // Campos de texto — necessários para o editor inline
                // (Melhoria 8). No dashboard eles não aparecem visualmente
                // mas alimentam o modal de edição.
                'justification' => $item->justification,
                'account_description' => $item->account_description,
                'class_description' => $item->class_description,
                // 12 valores mensais — mesmos nomes das colunas do DB,
                // o modal de edição lê diretamente sem conversão.
                'month_01_value' => (float) $item->month_01_value,
                'month_02_value' => (float) $item->month_02_value,
                'month_03_value' => (float) $item->month_03_value,
                'month_04_value' => (float) $item->month_04_value,
                'month_05_value' => (float) $item->month_05_value,
                'month_06_value' => (float) $item->month_06_value,
                'month_07_value' => (float) $item->month_07_value,
                'month_08_value' => (float) $item->month_08_value,
                'month_09_value' => (float) $item->month_09_value,
                'month_10_value' => (float) $item->month_10_value,
                'month_11_value' => (float) $item->month_11_value,
                'month_12_value' => (float) $item->month_12_value,
                'forecast' => round($forecast, 2),
                'committed' => round($committed, 2),
                'realized' => round($realized, 2),
                'available' => round($forecast - $committed, 2),
                'utilization_pct' => $forecast > 0
                    ? round(($committed / $forecast) * 100, 2)
                    : 0.0,
                'realized_pct' => $forecast > 0
                    ? round(($realized / $forecast) * 100, 2)
                    : 0.0,
                'status' => $this->utilizationStatus($forecast, $committed),
            ];
        }

        return [
            'totals' => [
                'forecast' => round($forecastTotal, 2),
                'committed' => round($committedTotal, 2),
                'realized' => round($realizedTotal, 2),
                'available' => round($forecastTotal - $committedTotal, 2),
                'utilization_pct' => $forecastTotal > 0
                    ? round(($committedTotal / $forecastTotal) * 100, 2)
                    : 0.0,
                'realized_pct' => $forecastTotal > 0
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
     * Agrega total por budget_item_id. Sempre exclui OPs deletadas.
     *
     * @param  array<int>  $itemIds
     * @param  bool  $onlyDone  true = apenas status='done' (realized);
     *                          false = todas não-deletadas (committed).
     * @return array<int, float>  [item_id => total]
     */
    protected function aggregateByItem(array $itemIds, bool $onlyDone): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $query = DB::table('order_payments')
            ->whereIn('budget_item_id', $itemIds)
            ->whereNull('deleted_at');

        if ($onlyDone) {
            $query->where('status', 'done');
        }

        return $query
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
                    'committed' => 0.0,
                    'realized' => 0.0,
                    'items_count' => 0,
                ];
            }

            $map[$key]['forecast'] += $row['forecast'];
            $map[$key]['committed'] += $row['committed'];
            $map[$key]['realized'] += $row['realized'];
            $map[$key]['items_count']++;
        }

        $result = array_map(function ($agg) {
            $agg['available'] = round($agg['forecast'] - $agg['committed'], 2);
            $agg['forecast'] = round($agg['forecast'], 2);
            $agg['committed'] = round($agg['committed'], 2);
            $agg['realized'] = round($agg['realized'], 2);
            $agg['utilization_pct'] = $agg['forecast'] > 0
                ? round(($agg['committed'] / $agg['forecast']) * 100, 2)
                : 0.0;
            $agg['realized_pct'] = $agg['forecast'] > 0
                ? round(($agg['realized'] / $agg['forecast']) * 100, 2)
                : 0.0;
            $agg['status'] = $this->utilizationStatus($agg['forecast'], $agg['committed']);

            return $agg;
        }, array_values($map));

        // Ordena por % utilização DESC para destacar os mais consumidos
        usort($result, fn ($a, $b) => $b['utilization_pct'] <=> $a['utilization_pct']);

        return $result;
    }

    /**
     * Agrega previsto × comprometido × realizado por mês (1..12).
     *
     * Previsto: soma das colunas month_NN_value de todos os items.
     * Comprometido: soma de OPs não-deletadas (todos os status), agrupadas
     *               pelo mês do date_payment.
     * Realizado: subset acima restrito a status='done'.
     *
     * @return array<int, array{month: int, forecast: float, committed: float, realized: float}>
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
                'committed' => 0.0,
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
                ->whereNotNull('date_payment')
                ->whereYear('date_payment', $budget->year)
                ->select('date_payment', 'total_value', 'status')
                ->get();

            foreach ($ops as $op) {
                $m = (int) date('n', strtotime((string) $op->date_payment));
                if ($m < 1 || $m > 12 || ! isset($months[$m])) {
                    continue;
                }
                $months[$m]['committed'] += (float) $op->total_value;
                if ($op->status === 'done') {
                    $months[$m]['realized'] += (float) $op->total_value;
                }
            }

            // Arredonda no fim
            for ($m = 1; $m <= 12; $m++) {
                $months[$m]['committed'] = round($months[$m]['committed'], 2);
                $months[$m]['realized'] = round($months[$m]['realized'], 2);
            }
        }

        return array_values($months);
    }

    /**
     * Semáforo de utilização (baseado em committed — visão operacional).
     *   - 'ok': < 70% do previsto
     *   - 'warning': 70% - 99.99%
     *   - 'exceeded': >= 100% (consumo atingiu ou passou do previsto)
     */
    protected function utilizationStatus(float $forecast, float $committed): string
    {
        if ($forecast <= 0) {
            return $committed > 0 ? 'exceeded' : 'ok';
        }

        $pct = ($committed / $forecast) * 100;

        if ($pct >= 100) {
            return 'exceeded';
        }
        if ($pct >= 70) {
            return 'warning';
        }

        return 'ok';
    }
}
