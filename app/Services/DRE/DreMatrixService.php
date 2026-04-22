<?php

namespace App\Services\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Services\DRE\Contracts\ClosedPeriodReader;
use App\Support\DreCacheVersion;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra o cálculo da matriz DRE.
 *
 * Estratégia (Opção B do `dre-arquitetura.md §4.2`):
 *   1. Carrega `DreMappingResolver` com os mappings ativos do período.
 *   2. Roda 3 queries agregadas por (account, cc, year, month):
 *        - actuals do período
 *        - budgets do período (versão do filtro)
 *        - actuals do período -1 ano (comparativo)
 *   3. Para cada tupla, resolve a linha gerencial via resolver (precedência
 *      específico > coringa > L99).
 *   4. Soma em uma matriz analítica `line_id → year_month → values`.
 *   5. Mescla valores dos meses fechados via `ClosedPeriodReader`.
 *   6. Aplica subtotais via `DreSubtotalCalculator`.
 *   7. Remove linha fantasma (L99) se `include_unclassified=false`.
 *   8. Anexa metadata + linhas ordenadas.
 *
 * Filtros de `scope`:
 *   - `general`: não agrupa por loja/rede.
 *   - `network`: aplica `network_ids` (resolve stores da rede).
 *   - `store`: aplica `store_ids` diretamente.
 */
class DreMatrixService
{
    public function __construct(
        private readonly ClosedPeriodReader $snapshotReader,
    ) {
    }

    /**
     * Calcula a matriz DRE no filtro informado.
     *
     * @param  array{
     *   start_date: string,
     *   end_date: string,
     *   store_ids?: array<int,int>,
     *   network_ids?: array<int,int>,
     *   budget_version?: ?string,
     *   scope?: string,
     *   include_unclassified?: bool,
     *   compare_previous_year?: bool
     * }  $filter
     * @return array{
     *   lines: array<int,array>,
     *   totals: array{actual:float,budget:float,previous_year:float},
     *   generated_at: string,
     *   filter: array
     * }
     */
    public function matrix(array $filter): array
    {
        $filter = $this->applyDefaults($filter);
        $cacheKey = self::cacheKeyForFilter($filter);

        // TTL 10 minutos — matriz pesada; warm-up diário garante hit na
        // maior parte dos acessos. Invalidações incrementam DreCacheVersion
        // (chaves antigas ficam órfãs e expiram pelo TTL).
        //
        // Store explícito: stancl/tenancy envolve o default com tagging, e
        // o driver `database` de produção não suporta. Usamos `file` (igual
        // `CentralRoleResolver` e `DreCacheVersion`). Em teste (`array`)
        // mantém o default para não quebrar asserções.
        $store = (app()->environment('testing') && config('cache.default') === 'array')
            ? Cache::store()
            : Cache::store('file');

        return $store->remember($cacheKey, 600, fn () => $this->compute($filter));
    }

    /**
     * Computa a matriz sem cache — chamado pelo `matrix()` via Cache::remember
     * e por consumidores que precisam de valores frescos (ex: preview de
     * reabertura de período).
     */
    public function compute(array $filter): array
    {
        $filter = $this->applyDefaults($filter);
        $from = Carbon::parse($filter['start_date'])->startOfDay();
        $to = Carbon::parse($filter['end_date'])->endOfDay();

        $managementLines = DreManagementLine::query()
            ->notDeleted()
            ->ordered()
            ->get();

        $resolver = DreMappingResolver::loadForPeriod($from, $to);
        $storeIds = $this->resolveStoreIds($filter);

        $analyticalMatrix = [];

        // Agrega actuals do período.
        $this->aggregateInto(
            $analyticalMatrix,
            $this->aggregate(
                table: 'dre_actuals',
                from: $from,
                to: $to,
                storeIds: $storeIds,
                amountField: 'actual',
            ),
            $resolver,
            amountKey: 'actual',
        );

        // Agrega budgets (mesmo período, versão do filtro).
        $budgetQuery = $this->aggregate(
            table: 'dre_budgets',
            from: $from,
            to: $to,
            storeIds: $storeIds,
            amountField: 'budget',
            budgetVersion: $filter['budget_version'],
        );
        $this->aggregateInto($analyticalMatrix, $budgetQuery, $resolver, 'budget');

        // Agrega previous_year (actuals do mesmo intervalo menos 1 ano).
        if ($filter['compare_previous_year']) {
            $pyFrom = $from->copy()->subYear();
            $pyTo = $to->copy()->subYear();
            $pyQuery = $this->aggregate(
                table: 'dre_actuals',
                from: $pyFrom,
                to: $pyTo,
                storeIds: $storeIds,
                amountField: 'previous_year',
                shiftYearMonthBy: +12, // volta pra "current" year-month
            );
            $this->aggregateInto($analyticalMatrix, $pyQuery, $resolver, 'previous_year');
        }

        // Mescla snapshot de períodos fechados (sobrescreve live).
        $this->overlaySnapshot($analyticalMatrix, $filter);

        // Calcula subtotais.
        $fullMatrix = (new DreSubtotalCalculator())->calculate($analyticalMatrix, $managementLines);

        // Monta payload final.
        $rows = [];
        $totals = ['actual' => 0.0, 'budget' => 0.0, 'previous_year' => 0.0];
        $unclassifiedId = DreMappingResolver::unclassifiedLineId();

        foreach ($managementLines as $line) {
            if (! $filter['include_unclassified'] && $line->id === $unclassifiedId) {
                continue;
            }

            $cells = $fullMatrix[$line->id] ?? [];
            $rowTotals = ['actual' => 0.0, 'budget' => 0.0, 'previous_year' => 0.0];
            foreach ($cells as $cell) {
                $rowTotals['actual'] += $cell['actual'];
                $rowTotals['budget'] += $cell['budget'];
                $rowTotals['previous_year'] += $cell['previous_year'];
            }

            $rows[] = [
                'id' => $line->id,
                'code' => $line->code,
                'sort_order' => $line->sort_order,
                'is_subtotal' => (bool) $line->is_subtotal,
                'nature' => $line->nature,
                'level_1' => $line->level_1,
                'months' => $cells,
                'totals' => $rowTotals,
            ];

            // Totais gerais somam só analíticas (+L99 quando incluída) pra
            // não duplicar com subtotais.
            if (! $line->is_subtotal) {
                $totals['actual'] += $rowTotals['actual'];
                $totals['budget'] += $rowTotals['budget'];
                $totals['previous_year'] += $rowTotals['previous_year'];
            }
        }

        return [
            'lines' => $rows,
            'totals' => $totals,
            'generated_at' => now()->toIso8601String(),
            'filter' => $filter,
        ];
    }

    /**
     * Drill-through: dada uma linha gerencial + filtro, retorna as contas
     * contábeis contribuintes com valores totalizados.
     *
     * @param  array  $filter  mesmo formato do `matrix()`.
     * @return array<int,array{
     *   chart_of_account: array{id:int,code:string,name:string},
     *   cost_center: ?array{id:int,code:string,name:string},
     *   actual: float,
     *   budget: float,
     *   previous_year: float
     * }>
     */
    public function drill(int $managementLineId, array $filter): array
    {
        $filter = $this->applyDefaults($filter);
        $from = Carbon::parse($filter['start_date'])->startOfDay();
        $to = Carbon::parse($filter['end_date'])->endOfDay();
        $storeIds = $this->resolveStoreIds($filter);

        $resolver = DreMappingResolver::loadForPeriod($from, $to);

        // Agrega actuals e budgets por (account, cc) — sem month breakdown.
        $actualRows = $this->aggregateTotals('dre_actuals', $from, $to, $storeIds, null);
        $budgetRows = $this->aggregateTotals('dre_budgets', $from, $to, $storeIds, $filter['budget_version']);

        $pyRows = $filter['compare_previous_year']
            ? $this->aggregateTotals(
                'dre_actuals',
                $from->copy()->subYear(),
                $to->copy()->subYear(),
                $storeIds,
                null,
            )
            : collect();

        // Indexa por (account, cc).
        $contributors = [];

        foreach ($actualRows as $r) {
            $line = $resolver->resolve((int) $r->account_id, $this->nullableInt($r->cc_id), $r->latest_date ?? $from);
            if ($line !== $managementLineId) {
                continue;
            }
            $key = $r->account_id.':'.($r->cc_id ?? 'null');
            $contributors[$key] ??= $this->emptyDrillRow((int) $r->account_id, $this->nullableInt($r->cc_id));
            $contributors[$key]['actual'] += (float) $r->total;
        }

        foreach ($budgetRows as $r) {
            $line = $resolver->resolve((int) $r->account_id, $this->nullableInt($r->cc_id), $r->latest_date ?? $from);
            if ($line !== $managementLineId) {
                continue;
            }
            $key = $r->account_id.':'.($r->cc_id ?? 'null');
            $contributors[$key] ??= $this->emptyDrillRow((int) $r->account_id, $this->nullableInt($r->cc_id));
            $contributors[$key]['budget'] += (float) $r->total;
        }

        foreach ($pyRows as $r) {
            // Resolve pela data ORIGINAL (ano anterior) — mapping pode ser
            // diferente do atual em alguns cenários.
            $line = $resolver->resolve((int) $r->account_id, $this->nullableInt($r->cc_id), $r->latest_date ?? $from->copy()->subYear());
            if ($line !== $managementLineId) {
                continue;
            }
            $key = $r->account_id.':'.($r->cc_id ?? 'null');
            $contributors[$key] ??= $this->emptyDrillRow((int) $r->account_id, $this->nullableInt($r->cc_id));
            $contributors[$key]['previous_year'] += (float) $r->total;
        }

        // Enriquece com nome da conta/CC.
        $accountIds = array_unique(array_map(fn ($c) => $c['_account_id'], $contributors));
        $ccIds = array_filter(array_unique(array_map(fn ($c) => $c['_cc_id'], $contributors)));

        $accountMap = ChartOfAccount::whereIn('id', $accountIds)->get(['id', 'code', 'name'])->keyBy('id');
        $ccMap = CostCenter::whereIn('id', $ccIds)->get(['id', 'code', 'name'])->keyBy('id');

        $result = [];
        foreach ($contributors as $c) {
            $acc = $accountMap->get($c['_account_id']);
            $cc = $c['_cc_id'] !== null ? $ccMap->get($c['_cc_id']) : null;

            $result[] = [
                'chart_of_account' => $acc ? ['id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name] : null,
                'cost_center' => $cc ? ['id' => $cc->id, 'code' => $cc->code, 'name' => $cc->name] : null,
                'actual' => $c['actual'],
                'budget' => $c['budget'],
                'previous_year' => $c['previous_year'],
            ];
        }

        usort($result, fn ($a, $b) => ($a['chart_of_account']['code'] ?? '') <=> ($b['chart_of_account']['code'] ?? ''));

        return $result;
    }

    /**
     * Extrai KPIs principais (Faturamento Líquido, EBITDA, Margem Líquida,
     * Não Classificado). Nomes baseados no seed executivo do prompt #2.
     *
     * @param  array  $filter
     * @return array{
     *   faturamento_liquido: array{actual:float,budget:float,previous_year:float},
     *   ebitda: array{actual:float,budget:float,previous_year:float},
     *   margem_liquida: array{actual:float,budget:float,previous_year:float},
     *   nao_classificado: array{actual:float,budget:float,previous_year:float}
     * }
     */
    public function kpis(array $filter): array
    {
        $matrix = $this->matrix($filter);

        $byCode = collect($matrix['lines'])->keyBy('code');

        $fatLiq = $byCode->get('L03')['totals'] ?? $this->zeros();
        $ebitda = $byCode->get('L14')['totals'] ?? $this->zeros();
        $lucroLiq = $byCode->get('L18')['totals'] ?? $this->zeros();
        $l99 = $byCode->get('L99_UNCLASSIFIED')['totals'] ?? $this->zeros();

        // Margem líquida = Lucro Líquido / Faturamento Líquido (em %), por dimensão.
        $margem = [
            'actual' => $this->safeDivision($lucroLiq['actual'], $fatLiq['actual']) * 100,
            'budget' => $this->safeDivision($lucroLiq['budget'], $fatLiq['budget']) * 100,
            'previous_year' => $this->safeDivision($lucroLiq['previous_year'], $fatLiq['previous_year']) * 100,
        ];

        return [
            'faturamento_liquido' => $fatLiq,
            'ebitda' => $ebitda,
            'margem_liquida' => $margem,
            'nao_classificado' => $l99,
        ];
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    private function applyDefaults(array $filter): array
    {
        return array_merge([
            'store_ids' => [],
            'network_ids' => [],
            'budget_version' => null,
            'scope' => 'general',
            'include_unclassified' => true,
            'compare_previous_year' => true,
        ], $filter);
    }

    /**
     * Resolve a lista efetiva de store_ids a aplicar nos WHEREs conforme scope.
     * - general: [] (sem filtro).
     * - network: pega todas lojas das redes informadas.
     * - store: store_ids direto.
     */
    private function resolveStoreIds(array $filter): array
    {
        if ($filter['scope'] === 'store') {
            return array_values(array_unique($filter['store_ids']));
        }

        if ($filter['scope'] === 'network' && ! empty($filter['network_ids'])) {
            return DB::table('stores')
                ->whereIn('network_id', $filter['network_ids'])
                ->pluck('id')
                ->map(fn ($i) => (int) $i)
                ->all();
        }

        return [];
    }

    /**
     * Agrega por (account, cc, year, month). Retorna Collection de stdClass
     * com keys: account_id, cc_id, year, month, total.
     *
     * @param  ?int  $shiftYearMonthBy  somado a (year*12 + month) para normalizar
     *         previous_year ao mês "atual" do período principal.
     */
    private function aggregate(
        string $table,
        Carbon $from,
        Carbon $to,
        array $storeIds,
        string $amountField,
        ?string $budgetVersion = null,
        int $shiftYearMonthBy = 0,
    ): Collection {
        $driver = DB::connection()->getDriverName();
        $yearExpr = $driver === 'sqlite' ? "CAST(strftime('%Y', entry_date) AS INTEGER)" : 'YEAR(entry_date)';
        $monthExpr = $driver === 'sqlite' ? "CAST(strftime('%m', entry_date) AS INTEGER)" : 'MONTH(entry_date)';

        $q = DB::table($table)
            ->selectRaw("chart_of_account_id as account_id, cost_center_id as cc_id, {$yearExpr} as year, {$monthExpr} as month, SUM(amount) as total")
            ->whereBetween('entry_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('chart_of_account_id', 'cost_center_id', DB::raw($yearExpr), DB::raw($monthExpr));

        if (! empty($storeIds)) {
            $q->whereIn('store_id', $storeIds);
        }

        if ($table === 'dre_budgets' && $budgetVersion !== null) {
            $q->where('budget_version', $budgetVersion);
        }

        $rows = $q->get();

        if ($shiftYearMonthBy !== 0) {
            $rows = $rows->map(function ($r) use ($shiftYearMonthBy) {
                $total = ((int) $r->year) * 12 + ((int) $r->month) + $shiftYearMonthBy;
                $r->year = (int) floor(($total - 1) / 12);
                $r->month = (($total - 1) % 12) + 1;

                return $r;
            });
        }

        return $rows;
    }

    /**
     * Popula `$matrix[$line_id][$ym]['actual'|'budget'|'previous_year']` a
     * partir de linhas agregadas, resolvendo a linha gerencial via resolver.
     */
    private function aggregateInto(array &$matrix, Collection $rows, DreMappingResolver $resolver, string $amountKey): void
    {
        foreach ($rows as $r) {
            $ym = sprintf('%04d-%02d', (int) $r->year, (int) $r->month);
            $lineDate = $ym.'-15'; // meio do mês — suficiente para a vigência do mapping
            $lineId = $resolver->resolve((int) $r->account_id, $this->nullableInt($r->cc_id), $lineDate);

            $matrix[$lineId] ??= [];
            $matrix[$lineId][$ym] ??= ['actual' => 0.0, 'budget' => 0.0, 'previous_year' => 0.0];
            $matrix[$lineId][$ym][$amountKey] += (float) $r->total;
        }
    }

    /**
     * Mescla valores de meses fechados a partir do snapshot. Sobrescreve
     * o que veio da matriz live. Zero efeito com `NullClosedPeriodReader`.
     */
    private function overlaySnapshot(array &$matrix, array $filter): void
    {
        $snapshot = $this->snapshotReader->readSnapshot($filter);
        foreach ($snapshot as $ym => $byLine) {
            foreach ($byLine as $lineId => $values) {
                $matrix[$lineId][$ym] = [
                    'actual' => (float) ($values['actual'] ?? 0.0),
                    'budget' => (float) ($values['budget'] ?? 0.0),
                    'previous_year' => (float) ($values['previous_year'] ?? 0.0),
                ];
            }
        }
    }

    /**
     * Versão do aggregate sem month breakdown — para drill.
     * Retorna por (account_id, cc_id) com total e última entry_date do grupo.
     */
    private function aggregateTotals(string $table, Carbon $from, Carbon $to, array $storeIds, ?string $budgetVersion): Collection
    {
        $q = DB::table($table)
            ->selectRaw('chart_of_account_id as account_id, cost_center_id as cc_id, SUM(amount) as total, MAX(entry_date) as latest_date')
            ->whereBetween('entry_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('chart_of_account_id', 'cost_center_id');

        if (! empty($storeIds)) {
            $q->whereIn('store_id', $storeIds);
        }

        if ($table === 'dre_budgets' && $budgetVersion !== null) {
            $q->where('budget_version', $budgetVersion);
        }

        return $q->get();
    }

    private function nullableInt(mixed $v): ?int
    {
        return $v === null ? null : (int) $v;
    }

    private function emptyDrillRow(int $accountId, ?int $ccId): array
    {
        return [
            '_account_id' => $accountId,
            '_cc_id' => $ccId,
            'actual' => 0.0,
            'budget' => 0.0,
            'previous_year' => 0.0,
        ];
    }

    private function zeros(): array
    {
        return ['actual' => 0.0, 'budget' => 0.0, 'previous_year' => 0.0];
    }

    private function safeDivision(float $numerator, float $denominator): float
    {
        if (abs($denominator) < 0.0001) {
            return 0.0;
        }

        return $numerator / $denominator;
    }

    // -----------------------------------------------------------------
    // Cache helpers (playbook prompt 12)
    // -----------------------------------------------------------------

    /**
     * Normaliza o filter para hashing estável — mesma matriz pedida em duas
     * requests diferentes (ordens de chaves ou arrays distintos) deve gerar
     * a mesma chave. Público para testar invariância.
     *
     * Regras:
     *   - Dates: força `Y-m-d` (dropa horário).
     *   - Arrays de ids: `array_values(array_unique(sort))`.
     *   - Budget version: trim.
     *   - Bools: cast estrito.
     *   - Ordem das chaves: ksort recursivo (só primeiro nível importa aqui).
     */
    public static function normalizeFilterForCache(array $filter): array
    {
        $storeIds = array_values(array_unique(array_map('intval', $filter['store_ids'] ?? [])));
        sort($storeIds);

        $networkIds = array_values(array_unique(array_map('intval', $filter['network_ids'] ?? [])));
        sort($networkIds);

        $startDate = isset($filter['start_date'])
            ? Carbon::parse((string) $filter['start_date'])->format('Y-m-d')
            : null;
        $endDate = isset($filter['end_date'])
            ? Carbon::parse((string) $filter['end_date'])->format('Y-m-d')
            : null;

        $normalized = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'scope' => strtolower((string) ($filter['scope'] ?? 'general')),
            'store_ids' => $storeIds,
            'network_ids' => $networkIds,
            'budget_version' => isset($filter['budget_version'])
                ? trim((string) $filter['budget_version'])
                : null,
            'include_unclassified' => (bool) ($filter['include_unclassified'] ?? true),
            'compare_previous_year' => (bool) ($filter['compare_previous_year'] ?? true),
        ];

        ksort($normalized);

        return $normalized;
    }

    public static function cacheKeyForFilter(array $filter): string
    {
        $normalized = self::normalizeFilterForCache($filter);
        $hash = md5((string) json_encode($normalized));

        return 'dre:matrix:v'.DreCacheVersion::current().':'.$hash;
    }
}
