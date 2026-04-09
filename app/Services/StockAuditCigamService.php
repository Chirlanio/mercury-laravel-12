<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAuditCigamService
{
    private string $dataSource = 'none';

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function isAvailable(): bool
    {
        try {
            $config = config('database.connections.cigam');
            if (empty($config['host']) || empty($config['database'])) {
                return false;
            }

            DB::connection('cigam')->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Load system stock from CIGAM and populate audit items.
     */
    public function loadSystemStock(int $auditId, string $storeCode, string $auditType): array
    {
        $audit = StockAudit::findOrFail($auditId);

        // Delete existing items
        StockAuditItem::where('audit_id', $auditId)->delete();

        $items = $this->isAvailable()
            ? $this->loadFromCigam($storeCode)
            : $this->loadFromLocal();

        if ($items === null) {
            $items = $this->loadFromLocal();
            $this->dataSource = 'local';
        }

        if (empty($items)) {
            return ['total' => 0, 'source' => $this->dataSource];
        }

        // Filter for specific audit types
        $items = $this->filterByAuditType($items, $audit, $storeCode);

        // Insert items in batch
        $inserted = 0;
        foreach ($items as $item) {
            StockAuditItem::create([
                'audit_id' => $auditId,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_reference' => $item['reference'] ?? '',
                'product_description' => $item['description'] ?? '',
                'product_barcode' => $item['barcode'] ?? '',
                'product_size' => $item['size'] ?? null,
                'system_quantity' => $item['system_quantity'] ?? 0,
                'unit_price' => $item['unit_price'] ?? 0,
                'cost_price' => $item['cost_price'] ?? 0,
            ]);
            $inserted++;
        }

        return ['total' => $inserted, 'source' => $this->dataSource];
    }

    /**
     * Lookup a single product by aux_reference (barcode scan).
     */
    public function getProductStock(string $storeCode, string $auxReference): ?array
    {
        if ($this->isAvailable()) {
            try {
                $result = DB::connection('cigam')
                    ->table('msl_festoqueatual_ as e')
                    ->join('msl_dprodutos_ as p', 'p.codbarra', '=', 'e.cod_barra')
                    ->where('e.refauxiliar', $auxReference)
                    ->where('e.loja', $storeCode)
                    ->select('e.saldo', 'e.cod_barra', 'e.refauxiliar', 'p.referencia', 'p.tamanho', 'p.descricao')
                    ->first();

                if ($result) {
                    return [
                        'saldo' => (float) $result->saldo,
                        'referencia' => $result->referencia ?? '',
                        'tamanho' => $result->tamanho ?? '',
                        'descricao' => $result->descricao ?? '',
                        'cod_barra' => $result->cod_barra ?? '',
                    ];
                }

                // Try by cod_barra fallback
                $result = DB::connection('cigam')
                    ->table('msl_festoqueatual_ as e')
                    ->join('msl_dprodutos_ as p', 'p.codbarra', '=', 'e.cod_barra')
                    ->where('e.cod_barra', $auxReference)
                    ->where('e.loja', $storeCode)
                    ->select('e.saldo', 'e.cod_barra', 'e.refauxiliar', 'p.referencia', 'p.tamanho', 'p.descricao')
                    ->first();

                if ($result) {
                    return [
                        'saldo' => (float) $result->saldo,
                        'referencia' => $result->referencia ?? '',
                        'tamanho' => $result->tamanho ?? '',
                        'descricao' => $result->descricao ?? '',
                        'cod_barra' => $result->cod_barra ?? '',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('StockAuditCigam: lookup failed', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private function loadFromCigam(string $storeCode): ?array
    {
        try {
            $rows = DB::connection('cigam')
                ->table('msl_festoqueatual_ as e')
                ->join('msl_dprodutos_ as p', 'p.codbarra', '=', 'e.cod_barra')
                ->where('e.loja', $storeCode)
                ->where('e.saldo', '!=', 0)
                ->select('e.cod_barra', 'e.saldo', 'e.refauxiliar', 'p.referencia', 'p.tamanho', 'p.descricao')
                ->orderBy('p.referencia')
                ->orderBy('p.tamanho')
                ->get();

            $this->dataSource = 'cigam';
            $items = [];

            // Cache local products by reference
            $references = $rows->pluck('referencia')->unique()->filter()->values()->toArray();
            $products = Product::whereIn('reference', $references)
                ->where('is_active', true)
                ->get()
                ->keyBy('reference');

            // Cache variants by barcode and aux_reference
            $barcodes = $rows->pluck('cod_barra')->filter()->values()->toArray();
            $auxRefs = $rows->pluck('refauxiliar')->filter()->values()->toArray();

            $variantsByBarcode = ProductVariant::whereIn('barcode', $barcodes)
                ->where('is_active', true)
                ->get()
                ->keyBy('barcode');

            $variantsByAux = ProductVariant::whereIn('aux_reference', $auxRefs)
                ->where('is_active', true)
                ->get()
                ->keyBy('aux_reference');

            foreach ($rows as $row) {
                $product = $products[$row->referencia] ?? null;
                $variant = $variantsByBarcode[$row->cod_barra]
                    ?? $variantsByAux[$row->refauxiliar]
                    ?? null;

                $items[] = [
                    'reference' => $row->referencia ?? '',
                    'description' => $row->descricao ?? '',
                    'barcode' => $row->refauxiliar ?? $row->cod_barra ?? '',
                    'size' => $row->tamanho ?? null,
                    'system_quantity' => (float) ($row->saldo ?? 0),
                    'unit_price' => $product ? (float) $product->sale_price : 0,
                    'cost_price' => $product ? (float) $product->cost_price : 0,
                    'product_variant_id' => $variant?->id,
                ];
            }

            return $items;
        } catch (\Exception $e) {
            Log::error('StockAuditCigam: load failed', ['error' => $e->getMessage(), 'store' => $storeCode]);

            return null;
        }
    }

    private function loadFromLocal(): array
    {
        $this->dataSource = 'local';

        $variants = ProductVariant::with('product')
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->get();

        $items = [];
        foreach ($variants as $variant) {
            $product = $variant->product;
            $items[] = [
                'reference' => $product->reference ?? '',
                'description' => $product->description ?? '',
                'barcode' => $variant->aux_reference ?? $variant->barcode ?? '',
                'size' => $variant->size_cigam_code ?? null,
                'system_quantity' => 0, // Unknown from local
                'unit_price' => (float) ($product->sale_price ?? 0),
                'cost_price' => (float) ($product->cost_price ?? 0),
                'product_variant_id' => $variant->id,
            ];
        }

        return $items;
    }

    private function filterByAuditType(array $items, StockAudit $audit, string $storeCode): array
    {
        if ($audit->audit_type === 'aleatoria' && $audit->random_sample_size > 0) {
            $topReferences = $this->getHighMovementReferences($storeCode, $audit->random_sample_size);
            if (! empty($topReferences)) {
                $refSet = array_flip($topReferences);
                $items = array_filter($items, fn ($item) => isset($refSet[$item['reference']]));
                $items = array_values($items);
            }
        }

        if ($audit->audit_type === 'diaria') {
            $todayBarcodes = DB::table('movements')
                ->where('store_code', $storeCode)
                ->whereDate('movement_date', now()->toDateString())
                ->distinct()
                ->pluck('barcode')
                ->toArray();

            if (! empty($todayBarcodes)) {
                $barcodeSet = array_flip($todayBarcodes);
                $items = array_filter($items, fn ($item) => isset($barcodeSet[$item['barcode']]));
                $items = array_values($items);
            }
        }

        return $items;
    }

    /**
     * Get top-N product references by movement volume in last N days.
     */
    public function getHighMovementReferences(string $storeCode, int $sampleSize, int $days = 90): array
    {
        return DB::table('movements')
            ->where('store_code', $storeCode)
            ->where('movement_date', '>=', now()->subDays($days)->toDateString())
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->selectRaw('COALESCE(reference, barcode) as product_reference, SUM(ABS(quantity)) as total_movement')
            ->groupBy('product_reference')
            ->orderByDesc('total_movement')
            ->limit($sampleSize)
            ->pluck('product_reference')
            ->toArray();
    }
}
