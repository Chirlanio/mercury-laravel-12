<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sugestão de itens para um remanejo, baseada em vendas recentes da loja
 * destino.
 *
 * Algoritmo (versão simples — Fase 3):
 *  1. Top N produtos vendidos na loja destino nos últimos `days` dias
 *     (movements code=2, agregado por barcode + ref_size).
 *  2. Para cada produto, sugere a loja origem com maior saldo aproximado
 *     baseado em entradas recentes (code=1 + code=5+E + code=6+E menos
 *     code=2 + code=5+S nos últimos 90d, exceto a loja destino).
 *  3. qty_suggested = ceil( (vendas_no_periodo / days) * coverage_days )
 *     Default coverage_days = 14 (cobertura de 2 semanas no ritmo atual).
 *
 * Refinamentos futuros (Fase 9 backlog): curva ABC, sazonalidade, sub-
 * stituição por produto similar (mesma coleção/categoria).
 *
 * Performance: queries pesadas em `movements` (5M+ rows) — usa índices
 * existentes em (store_code, movement_code, movement_date) e barcode.
 * Em produção, considerar cache por destino + janela de 1h.
 */
class RelocationSuggestionService
{
    /**
     * Constantes da heurística — ajustar se a régua mudar.
     */
    private const COVERAGE_DAYS = 14;          // Cobertura desejada na sugestão
    private const ORIGIN_LOOKBACK_DAYS = 90;   // Janela para estimar saldo na origem
    private const MAX_TOP = 50;                // Limite máximo absoluto

    /**
     * @return array{
     *   destination_store: array{id:int, code:string, name:string},
     *   period: array{days:int, from:string, to:string, coverage_days:int},
     *   suggestions: array<int, array{
     *     barcode: string,
     *     ref_size: string|null,
     *     product_reference: string|null,
     *     product_name: string|null,
     *     product_color: string|null,
     *     size: string|null,
     *     sales_qty: float,
     *     sales_count: int,
     *     daily_average: float,
     *     qty_suggested: int,
     *     suggested_origin: array{id:int, code:string, name:string, estimated_balance:float}|null,
     *     other_origins: array<int, array{id:int, code:string, name:string, estimated_balance:float}>,
     *   }>
     * }
     */
    public function suggestForStore(int $destinationStoreId, int $days = 30, int $top = 20): array
    {
        $top = max(1, min($top, self::MAX_TOP));
        $days = max(7, $days);

        $destStore = Store::query()
            ->whereKey($destinationStoreId)
            ->first(['id', 'code', 'name']);

        if (! $destStore) {
            return [
                'destination_store' => null,
                'period' => $this->periodMeta($days),
                'suggestions' => [],
            ];
        }

        $sales = $this->topSellingItems($destStore->code, $days, $top);

        if ($sales->isEmpty()) {
            return [
                'destination_store' => $destStore->only(['id', 'code', 'name']),
                'period' => $this->periodMeta($days),
                'suggestions' => [],
            ];
        }

        $barcodes = $sales->pluck('barcode')->filter()->unique()->values()->all();
        $balances = $this->estimateOriginBalances($barcodes, $destStore->code);
        $stores = Store::whereIn('code', $balances->pluck('store_code')->unique()->values())
            ->get(['id', 'code', 'name'])
            ->keyBy('code');

        // Index produtos por barcode pra enriquecer
        $products = $this->lookupProducts($barcodes);

        $suggestions = $sales->map(function ($row) use ($balances, $stores, $days, $products) {
            $key = $row->barcode . '|' . ($row->ref_size ?? '');

            // Filtra balances pelo barcode + ref_size
            $candidates = $balances
                ->where('barcode', $row->barcode)
                ->where('ref_size', $row->ref_size)
                ->where('estimated_balance', '>', 0)
                ->sortByDesc('estimated_balance')
                ->values();

            $primary = $candidates->first();
            $others = $candidates->slice(1, 3)->values();

            $product = $this->matchProduct($products, $row);

            $dailyAvg = (float) $row->sales_qty / $days;
            $qtySuggested = max(1, (int) ceil($dailyAvg * self::COVERAGE_DAYS));

            return [
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
                'suggested_origin' => $primary
                    ? $this->formatStoreBalance($primary, $stores)
                    : null,
                'other_origins' => $others->map(fn ($c) => $this->formatStoreBalance($c, $stores))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        return [
            'destination_store' => $destStore->only(['id', 'code', 'name']),
            'period' => $this->periodMeta($days),
            'suggestions' => $suggestions,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * Top N barcodes vendidos na loja destino agregados por barcode + ref_size.
     */
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
     * Estima saldo aproximado por (loja, barcode, ref_size) considerando
     * movimentos de entrada/saída nos últimos 90 dias. Não é estoque
     * absoluto — é uma proxy útil pra rankear "quem provavelmente tem".
     *
     * Códigos aplicados:
     *   +1 (compra), +5+E (transfer in), +6+E (devolução do cliente),
     *   -2 (venda), -5+S (transfer out)
     *
     * Loja destino é EXCLUÍDA pra não sugerir-se a si mesma.
     */
    protected function estimateOriginBalances(array $barcodes, string $excludeStoreCode): Collection
    {
        if (empty($barcodes)) {
            return collect();
        }

        $from = now()->subDays(self::ORIGIN_LOOKBACK_DAYS)->toDateString();
        $to = now()->toDateString();

        return DB::table('movements')
            ->whereIn('barcode', $barcodes)
            ->whereBetween('movement_date', [$from, $to])
            ->where('store_code', '!=', $excludeStoreCode)
            ->selectRaw("
                store_code,
                barcode,
                ref_size,
                SUM(CASE
                    WHEN movement_code = 1 THEN quantity
                    WHEN movement_code = 5 AND entry_exit = 'E' THEN quantity
                    WHEN movement_code = 6 AND entry_exit = 'E' THEN quantity
                    WHEN movement_code = 2 THEN -quantity
                    WHEN movement_code = 5 AND entry_exit = 'S' THEN -quantity
                    ELSE 0
                END) as estimated_balance
            ")
            ->groupBy('store_code', 'barcode', 'ref_size')
            ->get();
    }

    /**
     * Busca produtos pelo barcode/reference. Como `products` não tem coluna
     * barcode direto (só reference), tentamos casar pelo prefixo da
     * reference quando o barcode aparenta ser uma reference válida.
     *
     * Se não encontrar, retorna array vazio — UI usa o barcode como
     * fallback de exibição.
     *
     * @return array<string, array{reference: string, description: string|null, color_name: string|null}>
     */
    protected function lookupProducts(array $barcodes): array
    {
        if (empty($barcodes)) {
            return [];
        }

        // products.reference geralmente tem o mesmo padrão de barcode em
        // alguns casos, ou pode ser prefixo. Tentamos match exato primeiro.
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

    /**
     * Tenta casar uma linha de venda com o catálogo de produtos. Primeiro
     * tenta barcode exato; depois tenta barcode reduzido (prefixo).
     */
    protected function matchProduct(array $products, $salesRow): array
    {
        if (isset($products[$salesRow->barcode])) {
            return $products[$salesRow->barcode];
        }
        if ($salesRow->ref_size && isset($products[$salesRow->ref_size])) {
            return $products[$salesRow->ref_size];
        }

        return [
            'reference' => $salesRow->barcode,
            'description' => null,
            'color_name' => null,
        ];
    }

    protected function formatStoreBalance(object $row, Collection $stores): ?array
    {
        $store = $stores->get($row->store_code);
        if (! $store) {
            return null;
        }

        return [
            'id' => $store->id,
            'code' => $store->code,
            'name' => $store->name,
            'estimated_balance' => round((float) $row->estimated_balance, 2),
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
