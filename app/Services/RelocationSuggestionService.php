<?php

namespace App\Services;

use App\Models\Product;
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

    public function __construct(
        protected CigamStockService $stock,
    ) {}

    /**
     * @return array{
     *   destination_store: array{id:int, code:string, name:string, network_id:?int, network_name:?string}|null,
     *   period: array{days:int, from:string, to:string, coverage_days:int},
     *   cigam_available: bool,
     *   cigam_unavailable_reason: ?string,
     *   suggestions: array<int, array<string, mixed>>,
     *   suppressed_no_stock: int,
     * }
     */
    public function suggestForStore(int $destinationStoreId, int $days = 30, int $top = 20): array
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

        // Lojas da mesma rede (excluindo a destino) — pool de origens válidas
        $networkStoreCodes = Store::query()
            ->where('network_id', $destStore->network_id)
            ->where('id', '!=', $destStore->id)
            ->pluck('code')
            ->toArray();

        $sales = $this->topSellingItems($destStore->code, $days, $top);

        if ($sales->isEmpty()) {
            return [
                'destination_store' => $this->formatStore($destStore),
                'period' => $this->periodMeta($days),
                'cigam_available' => $this->stock->isAvailable(),
                'cigam_unavailable_reason' => $this->stock->getUnavailableReason(),
                'suggestions' => [],
                'suppressed_no_stock' => 0,
            ];
        }

        $barcodes = $sales->pluck('barcode')->filter()->unique()->values()->all();

        // Estoque real CIGAM, filtrado pela rede do destino
        $stocks = $this->stock->availableForBarcodes(
            $barcodes,
            excludeStoreCode: $destStore->code,
            onlyStoreCodes: $networkStoreCodes,
        );

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

            $suggestions[] = [
                'barcode' => $row->barcode,
                'ref_size' => $row->ref_size,
                'product_reference' => $product['reference'] ?? null,
                'product_name' => $product['description'] ?? null,
                'product_color' => $product['color_name'] ?? null,
                'size' => $row->ref_size,
                'sales_qty' => (float) $row->sales_qty,
                'sales_count' => (int) $row->sales_count,
                'daily_average' => round($dailyAvg, 2),
                'qty_suggested' => $qtySuggested,
                'suggested_origin' => $this->formatOrigin($primary, $storesByCode),
                'other_origins' => $others
                    ->map(fn ($c) => $this->formatOrigin($c, $storesByCode))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        return [
            'destination_store' => $this->formatStore($destStore),
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

    protected function emptyResult(int $days): array
    {
        return [
            'destination_store' => null,
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
     * @return array<string, array{reference: string, description: string|null, color_name: string|null}>
     */
    protected function lookupProducts(array $barcodes): array
    {
        if (empty($barcodes)) return [];

        $rows = Product::query()
            ->leftJoin('product_colors', 'product_colors.cigam_code', '=', 'products.color_cigam_code')
            ->whereIn('products.reference', $barcodes)
            ->get(['products.reference', 'products.description', 'product_colors.name as color_name']);

        $byRef = [];
        foreach ($rows as $r) {
            $byRef[$r->reference] = [
                'reference' => $r->reference,
                'description' => $r->description,
                'color_name' => $r->color_name,
            ];
        }
        return $byRef;
    }

    protected function matchProduct(array $products, $salesRow): array
    {
        if (isset($products[$salesRow->barcode])) return $products[$salesRow->barcode];
        if ($salesRow->ref_size && isset($products[$salesRow->ref_size])) return $products[$salesRow->ref_size];
        return ['reference' => $salesRow->barcode, 'description' => null, 'color_name' => null];
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
