<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sugestão de itens para um remanejo, baseada em vendas recentes da loja
 * destino + estoque REAL (CIGAM `msl_festoqueatual_`) nas lojas da MESMA REDE.
 *
 * Algoritmo:
 *  1. Top N produtos vendidos na loja destino nos últimos `days` dias
 *     (movements code=2, agregado por barcode + ref_size).
 *  2. Para cada produto, busca origens com `saldo > 0` em `msl_festoqueatual_`,
 *     restritas às lojas da MESMA REDE da loja destino. Loja destino é
 *     excluída.
 *  3. Sugestão da loja origem = a com MAIOR saldo entre os candidatos.
 *  4. qty_suggested = MIN( ceil(daily_avg * COVERAGE_DAYS), saldo_origem )
 *     — nunca sugere mais do que a origem tem.
 *
 * Mudança vs versão anterior (saldo-proxy via movements 90d):
 *  - Saldo agora é real (CIGAM authoritative), não estimativa
 *  - Lojas filtradas por `network_id == destination.network_id`
 *  - Produtos sem nenhuma origem com saldo são SUPRIMIDOS da lista
 *    (não faz sentido sugerir o que ninguém tem)
 *
 * Fallback se CIGAM offline: `CigamStockService` retorna vazio → todas
 * sugestões caem como "Sem origem viável" e a UI deixa o user ciente.
 */
class RelocationSuggestionService
{
    private const COVERAGE_DAYS = 14;          // Cobertura desejada
    private const MAX_TOP = 50;

    /**
     * Curva ABC — limites em % acumulado das vendas:
     *  - até 80%  → A (best-sellers, ~Pareto 20/80)
     *  - até 95%  → B (volume médio, próximos 15%)
     *  - resto    → C (cauda longa, baixo giro)
     */
    private const ABC_THRESHOLD_A = 80.0;
    private const ABC_THRESHOLD_B = 95.0;

    /**
     * Sazonalidade: ratio (vendas atual / vendas mesma janela 1 ano antes)
     * acima deste limiar marca o produto como "subindo". Threshold mínimo
     * de vendas no ano anterior evita falsos positivos com produtos novos.
     */
    private const SEASONALITY_RATIO_THRESHOLD = 1.5;
    private const SEASONALITY_MIN_PRIOR_QTY = 3;

    public function __construct(
        protected CigamStockService $stock,
        protected RelocationCommittedStockService $committedStock,
    ) {}

    /**
     * @param  int|null  $originStoreId  Quando informado, restringe as origens
     *   sugeridas a essa loja específica (e zera `other_origins`). Útil quando
     *   o usuário já decidiu de qual loja quer transferir e só quer ver os
     *   produtos daquela origem que vendem bem no destino.
     *
     * @return array{
     *   destination_store: array{id:int, code:string, name:string, network_id:?int, network_name:?string}|null,
     *   origin_store: array{id:int, code:string, name:string}|null,
     *   period: array{days:int, from:string, to:string, coverage_days:int},
     *   cigam_available: bool,
     *   cigam_unavailable_reason: ?string,
     *   suggestions: array<int, array<string, mixed>>,
     *   suppressed_no_stock: int,
     * }
     */
    public function suggestForStore(int $destinationStoreId, int $days = 30, int $top = 20, ?int $originStoreId = null): array
    {
        $top = max(1, min($top, self::MAX_TOP));
        $days = max(7, $days);

        $destStore = Store::query()
            ->leftJoin('networks as n', 'n.id', '=', 'stores.network_id')
            ->where('stores.id', $destinationStoreId)
            ->select('stores.id', 'stores.code', 'stores.name', 'stores.network_id', 'n.nome as network_name')
            ->first();

        if (! $destStore) {
            return $this->emptyResult($days);
        }

        // Resolve loja origem (se informada) e valida que está na mesma rede.
        $originStore = null;
        if ($originStoreId) {
            $originStore = Store::query()
                ->where('id', $originStoreId)
                ->where('network_id', $destStore->network_id)
                ->where('id', '!=', $destStore->id)
                ->select('id', 'code', 'name')
                ->first();
            // Se origem inválida (rede diferente, mesma loja, inexistente),
            // ignoramos silenciosamente — comportamento = "toda a rede".
        }

        // Pool de origens: única loja se origem fixa; toda a rede senão.
        $networkStoreCodes = $originStore
            ? [$originStore->code]
            : Store::query()
                ->where('network_id', $destStore->network_id)
                ->where('id', '!=', $destStore->id)
                ->pluck('code')
                ->toArray();

        $sales = $this->topSellingItems($destStore->code, $days, $top);

        if ($sales->isEmpty()) {
            return [
                'destination_store' => $this->formatStore($destStore),
                'origin_store' => $originStore ? [
                    'id' => $originStore->id,
                    'code' => $originStore->code,
                    'name' => $originStore->name,
                ] : null,
                'period' => $this->periodMeta($days),
                'cigam_available' => $this->stock->isAvailable(),
                'cigam_unavailable_reason' => $this->stock->getUnavailableReason(),
                'suggestions' => [],
                'suppressed_no_stock' => 0,
            ];
        }

        $barcodes = $sales->pluck('barcode')->filter()->unique()->values()->all();

        // Curva ABC — calcula em memória (cheap) sobre o resultado já paginado
        $abcByBarcode = $this->classifyAbc($sales);

        // Sazonalidade — query batch comparando com mesma janela 1 ano antes
        $seasonalityByKey = $this->detectSeasonality($destStore->code, $sales, $days);

        // Estoque real CIGAM, filtrado pela rede do destino (ou loja única
        // se o usuário fixou origem).
        $stocks = $this->stock->availableForBarcodes(
            $barcodes,
            excludeStoreCode: $destStore->code,
            onlyStoreCodes: $networkStoreCodes,
        );

        // Desconta saldo já comprometido em outros remanejos abertos da mesma
        // origem — sem isso, o mesmo barcode poderia ser sugerido pra N
        // destinos diferentes a partir da mesma loja, ultrapassando o saldo
        // físico. Lojas com saldo efetivo <= 0 são descartadas.
        $networkStoresIndex = Store::whereIn('code', $networkStoreCodes)
            ->get(['id', 'code'])
            ->keyBy('code');
        $networkStoreIds = $networkStoresIndex->pluck('id')->all();
        $committed = $this->committedStock->committedByStoreAndBarcode($networkStoreIds, $barcodes);

        $stocks = $stocks->map(function ($s) use ($committed, $networkStoresIndex) {
            $store = $networkStoresIndex->get($s->store_code);
            if (! $store) return $s;
            $clone = clone $s;
            $committedQty = $committed[$store->id.'|'.$clone->cod_barra] ?? 0;
            if (! empty($clone->refauxiliar) && $clone->refauxiliar !== $clone->cod_barra) {
                $committedQty += $committed[$store->id.'|'.$clone->refauxiliar] ?? 0;
            }
            $clone->saldo = max(0, (int) $clone->saldo - $committedQty);
            return $clone;
        })->filter(fn ($s) => (int) $s->saldo > 0)->values();

        // Index de lojas (id, name) por code — pra enriquecer o output
        $storesByCode = Store::whereIn('code', $stocks->pluck('store_code')->unique()->all())
            ->get(['id', 'code', 'name'])
            ->keyBy('code');

        $products = $this->lookupProducts($barcodes);

        $suggestions = [];
        $suppressed = 0;

        foreach ($sales as $row) {
            // Stocks deste barcode (ou seu refauxiliar associado)
            $candidates = $stocks
                ->where('cod_barra', $row->barcode)
                ->merge($stocks->where('refauxiliar', $row->barcode))
                ->unique(fn ($s) => $s->store_code)
                ->sortByDesc('saldo')
                ->values();

            if ($candidates->isEmpty()) {
                $suppressed++;
                continue; // Nenhuma loja da rede tem saldo — pula
            }

            $primary = $candidates->first();
            $others = $candidates->slice(1, 3)->values();

            $product = $this->matchProduct($products, $row);

            $dailyAvg = (float) $row->sales_qty / $days;
            $idealQty = max(1, (int) ceil($dailyAvg * self::COVERAGE_DAYS));
            // Nunca sugerir mais do que a origem tem
            $qtySuggested = min($idealQty, (int) $primary->saldo);

            $abc = $abcByBarcode[$row->barcode] ?? ['curve' => 'C', 'cumulative_pct' => 100.0];
            $seasonKey = $row->barcode.'|'.($row->ref_size ?? '');
            $seasonality = $seasonalityByKey[$seasonKey] ?? null;

            $suggestions[] = [
                'barcode' => $row->barcode,
                'ref_size' => $row->ref_size,
                'product_reference' => $product['reference'] ?? null,
                'product_name' => $product['description'] ?? null,
                'product_color' => $product['color_name'] ?? null,
                'size' => $product['size_label'] ?? $row->ref_size,
                'sales_qty' => (float) $row->sales_qty,
                'sales_count' => (int) $row->sales_count,
                'daily_average' => round($dailyAvg, 2),
                'qty_suggested' => $qtySuggested,
                'curve' => $abc['curve'],
                'curve_label' => 'Curva '.$abc['curve'],
                'cumulative_pct' => $abc['cumulative_pct'],
                'is_seasonal' => $seasonality['is_seasonal'] ?? false,
                'seasonality_ratio' => $seasonality['ratio'] ?? null,
                'prior_year_qty' => $seasonality['prior_qty'] ?? 0,
                'suggested_origin' => $this->formatOrigin($primary, $storesByCode),
                'other_origins' => $others
                    ->map(fn ($c) => $this->formatOrigin($c, $storesByCode))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        // Ordena: A primeiro, depois B, depois C; dentro da curva por sales_qty desc
        usort($suggestions, function ($a, $b) {
            $curveOrder = ['A' => 1, 'B' => 2, 'C' => 3];
            $cmp = ($curveOrder[$a['curve']] ?? 9) <=> ($curveOrder[$b['curve']] ?? 9);
            if ($cmp !== 0) return $cmp;
            return $b['sales_qty'] <=> $a['sales_qty'];
        });

        return [
            'destination_store' => $this->formatStore($destStore),
            'origin_store' => $originStore ? [
                'id' => $originStore->id,
                'code' => $originStore->code,
                'name' => $originStore->name,
            ] : null,
            'period' => $this->periodMeta($days),
            'cigam_available' => $this->stock->isAvailable(),
            'cigam_unavailable_reason' => $this->stock->getUnavailableReason(),
            'suggestions' => $suggestions,
            'suppressed_no_stock' => $suppressed,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Classifica produtos em curvas A/B/C baseado no Pareto:
     *  - A: produtos cujo acumulado de vendas está dentro de 80%
     *  - B: até 95%
     *  - C: até 100%
     *
     * Indexa por `barcode` pra lookup rápido no loop principal.
     *
     * @return array<string, array{curve: string, cumulative_pct: float}>
     */
    protected function classifyAbc(Collection $sales): array
    {
        $totalQty = $sales->sum('sales_qty');
        if ($totalQty <= 0) return [];

        // Já vem ordenado por sales_qty DESC do topSellingItems()
        $cumulative = 0.0;
        $result = [];
        foreach ($sales as $row) {
            $cumulative += (float) $row->sales_qty;
            $pct = round(($cumulative / $totalQty) * 100, 2);

            $curve = $pct <= self::ABC_THRESHOLD_A
                ? 'A'
                : ($pct <= self::ABC_THRESHOLD_B ? 'B' : 'C');

            $result[$row->barcode] = [
                'curve' => $curve,
                'cumulative_pct' => $pct,
            ];
        }
        return $result;
    }

    /**
     * Detecta tendência sazonal comparando vendas do período atual com a
     * MESMA janela exatamente 1 ano antes. Marca como sazonal quando o
     * ratio supera SEASONALITY_RATIO_THRESHOLD E o ano anterior tem pelo
     * menos SEASONALITY_MIN_PRIOR_QTY vendas (filtra produtos novos com
     * 0 ou 1 unidade no ano anterior, que dariam ratio infinito).
     *
     * Performance: 1 query batch agregando todos os barcodes de uma vez.
     *
     * @param  Collection $sales  Vendas atuais (com barcode + ref_size + sales_qty)
     * @return array<string, array{is_seasonal: bool, ratio: float|null, prior_qty: int}>
     *         indexado por "barcode|ref_size"
     */
    protected function detectSeasonality(string $storeCode, Collection $sales, int $days): array
    {
        if ($sales->isEmpty()) return [];

        // Janela do ano anterior (mesmos `days` dias, recuo de 1 ano)
        $priorFrom = now()->subYear()->subDays($days)->toDateString();
        $priorTo = now()->subYear()->toDateString();

        $barcodes = $sales->pluck('barcode')->filter()->unique()->values()->all();
        if (empty($barcodes)) return [];

        $priorRows = DB::table('movements')
            ->where('store_code', $storeCode)
            ->where('movement_code', 2)
            ->whereIn('barcode', $barcodes)
            ->whereBetween('movement_date', [$priorFrom, $priorTo])
            ->selectRaw('barcode, ref_size, SUM(quantity) as prior_qty')
            ->groupBy('barcode', 'ref_size')
            ->get()
            ->keyBy(fn ($r) => $r->barcode.'|'.($r->ref_size ?? ''));

        $result = [];
        foreach ($sales as $row) {
            $key = $row->barcode.'|'.($row->ref_size ?? '');
            $priorQty = (float) ($priorRows->get($key)?->prior_qty ?? 0);

            $ratio = null;
            $isSeasonal = false;

            if ($priorQty >= self::SEASONALITY_MIN_PRIOR_QTY) {
                $ratio = round((float) $row->sales_qty / $priorQty, 2);
                $isSeasonal = $ratio >= self::SEASONALITY_RATIO_THRESHOLD;
            }

            $result[$key] = [
                'is_seasonal' => $isSeasonal,
                'ratio' => $ratio,
                'prior_qty' => (int) $priorQty,
            ];
        }
        return $result;
    }

    protected function emptyResult(int $days): array
    {
        return [
            'destination_store' => null,
            'origin_store' => null,
            'period' => $this->periodMeta($days),
            'cigam_available' => $this->stock->isAvailable(),
            'cigam_unavailable_reason' => $this->stock->getUnavailableReason(),
            'suggestions' => [],
            'suppressed_no_stock' => 0,
        ];
    }

    protected function topSellingItems(string $storeCode, int $days, int $top): Collection
    {
        $from = now()->subDays($days)->toDateString();
        $to = now()->toDateString();

        return DB::table('movements')
            ->where('store_code', $storeCode)
            ->where('movement_code', 2)
            ->whereBetween('movement_date', [$from, $to])
            ->whereNotNull('barcode')
            ->selectRaw('barcode, ref_size, SUM(quantity) as sales_qty, COUNT(*) as sales_count')
            ->groupBy('barcode', 'ref_size')
            ->orderByDesc('sales_qty')
            ->limit($top)
            ->get();
    }

    /**
     * Indexado por código que aparece nas vendas (barcode ou aux_reference).
     * `products.reference` é o SKU do produto-pai e não bate com o barcode da venda;
     * a relação correta passa por `product_variants`. JOIN adicional com
     * `product_sizes` traduz o `size_cigam_code` da variante para o nome
     * legível (ex: "35", "P", "U") — sem isso o front mostraria a chave
     * composta bruta `{reference}{size_cigam_code}` que vem do CIGAM.
     *
     * @return array<string, array{reference: string, description: string|null, color_name: string|null, size_label: string|null}>
     */
    protected function lookupProducts(array $barcodes): array
    {
        if (empty($barcodes)) return [];

        $rows = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_colors', 'product_colors.cigam_code', '=', 'products.color_cigam_code')
            ->leftJoin('product_sizes', 'product_sizes.cigam_code', '=', 'product_variants.size_cigam_code')
            ->where(function ($q) use ($barcodes) {
                $q->whereIn('product_variants.barcode', $barcodes)
                    ->orWhereIn('product_variants.aux_reference', $barcodes);
            })
            ->get([
                'product_variants.barcode',
                'product_variants.aux_reference',
                'product_variants.size_cigam_code',
                'products.reference',
                'products.description',
                'product_colors.name as color_name',
                'product_sizes.name as size_label',
            ]);

        $byKey = [];
        foreach ($rows as $r) {
            $entry = [
                'reference' => $r->reference,
                'description' => $r->description,
                'color_name' => $r->color_name,
                'size_label' => $r->size_label ?? $r->size_cigam_code,
            ];
            if ($r->barcode) $byKey[$r->barcode] = $entry;
            if ($r->aux_reference) $byKey[$r->aux_reference] = $entry;
        }
        return $byKey;
    }

    protected function matchProduct(array $products, $salesRow): array
    {
        if (isset($products[$salesRow->barcode])) return $products[$salesRow->barcode];
        if ($salesRow->ref_size && isset($products[$salesRow->ref_size])) return $products[$salesRow->ref_size];
        return ['reference' => $salesRow->barcode, 'description' => null, 'color_name' => null, 'size_label' => null];
    }

    protected function formatOrigin(object $row, Collection $storesByCode): ?array
    {
        $store = $storesByCode->get($row->store_code);
        if (! $store) return null;

        return [
            'id' => $store->id,
            'code' => $store->code,
            'name' => $store->name,
            'stock' => (int) $row->saldo,
        ];
    }

    protected function formatStore(object $row): array
    {
        return [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'network_id' => $row->network_id,
            'network_name' => $row->network_name,
        ];
    }

    protected function periodMeta(int $days): array
    {
        return [
            'days' => $days,
            'from' => now()->subDays($days)->toDateString(),
            'to' => now()->toDateString(),
            'coverage_days' => self::COVERAGE_DAYS,
        ];
    }
}
