<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\StockAuditLog;

class StockAuditCountingService
{
    /**
     * Register a barcode scan, incrementing the count for the active round.
     */
    public function registerScan(StockAudit $audit, string $auxReference, int $round, float $quantity = 1, ?int $areaId = null, int $userId = 0): array
    {
        if ($audit->status !== 'counting') {
            return ['error' => true, 'message' => 'Auditoria nao esta em fase de contagem.'];
        }

        $roundField = "count_{$round}";
        $finalizedField = "count_{$round}_finalized";

        if ($audit->$finalizedField) {
            return ['error' => true, 'message' => "Rodada {$round} ja foi finalizada."];
        }

        // Look up product by aux_reference
        $productData = $this->lookupProduct($auxReference);
        if (! $productData) {
            return ['error' => true, 'message' => "Produto nao encontrado para o codigo: {$auxReference}"];
        }

        // Find or create item in audit
        $item = StockAuditItem::where('audit_id', $audit->id)
            ->where('product_barcode', $auxReference)
            ->first();

        if (! $item) {
            // Check by variant ID if available
            if ($productData['variant_id']) {
                $item = StockAuditItem::where('audit_id', $audit->id)
                    ->where('product_variant_id', $productData['variant_id'])
                    ->first();
            }
        }

        if (! $item) {
            // Create new item (product scanned but not in system stock snapshot)
            $item = StockAuditItem::create([
                'audit_id' => $audit->id,
                'area_id' => $areaId,
                'product_variant_id' => $productData['variant_id'],
                'product_reference' => $productData['reference'],
                'product_description' => $productData['description'],
                'product_barcode' => $auxReference,
                'product_size' => $productData['size'],
                'system_quantity' => 0,
                'unit_price' => $productData['unit_price'],
                'cost_price' => $productData['cost_price'],
            ]);
        }

        // Increment count for the round
        $currentCount = (float) ($item->$roundField ?? 0);
        $newCount = $currentCount + $quantity;

        $item->update([
            $roundField => $newCount,
            "{$roundField}_by_user_id" => $userId,
            "{$roundField}_at" => now(),
            'area_id' => $areaId ?? $item->area_id,
        ]);

        return [
            'error' => false,
            'item' => $item->fresh(),
            'product' => $productData,
            'new_count' => $newCount,
        ];
    }

    /**
     * Look up a product by aux_reference (EAN barcode).
     */
    public function lookupProduct(string $auxReference): ?array
    {
        $variant = ProductVariant::with('product', 'size')
            ->where('aux_reference', $auxReference)
            ->where('is_active', true)
            ->first();

        if (! $variant) {
            // Try by barcode field as fallback
            $variant = ProductVariant::with('product', 'size')
                ->where('barcode', $auxReference)
                ->where('is_active', true)
                ->first();
        }

        if (! $variant || ! $variant->product) {
            return null;
        }

        $product = $variant->product;

        return [
            'variant_id' => $variant->id,
            'reference' => $product->reference ?? '',
            'description' => $product->description ?? '',
            'barcode' => $variant->aux_reference ?? $variant->barcode ?? '',
            'size' => $variant->size?->name ?? $variant->size_cigam_code ?? null,
            'unit_price' => (float) ($product->sale_price ?? 0),
            'cost_price' => (float) ($product->cost_price ?? 0),
        ];
    }

    /**
     * Finalize a count round, locking further changes.
     */
    public function finalizeRound(StockAudit $audit, int $round, int $userId): array
    {
        $finalizedField = "count_{$round}_finalized";

        if ($audit->$finalizedField) {
            return ['error' => true, 'message' => "Rodada {$round} ja esta finalizada."];
        }

        $roundField = "count_{$round}";
        $countedItems = $audit->items()->whereNotNull($roundField)->count();

        if ($countedItems === 0) {
            return ['error' => true, 'message' => "Nenhum item foi contado na rodada {$round}."];
        }

        $audit->update([$finalizedField => true]);

        StockAuditLog::create([
            'audit_id' => $audit->id,
            'action_type' => 'finalize_round',
            'old_status' => $audit->status,
            'new_status' => $audit->status,
            'changed_by_user_id' => $userId,
            'notes' => "Rodada {$round} finalizada com {$countedItems} itens contados.",
        ]);

        return [
            'error' => false,
            'message' => "Rodada {$round} finalizada com sucesso.",
            'counted_items' => $countedItems,
        ];
    }

    /**
     * Clear counts for a specific round and optionally area.
     */
    public function clearRound(StockAudit $audit, int $round, ?int $areaId, int $userId): array
    {
        $finalizedField = "count_{$round}_finalized";

        if ($audit->$finalizedField) {
            return ['error' => true, 'message' => "Rodada {$round} ja esta finalizada e nao pode ser limpa."];
        }

        $roundField = "count_{$round}";
        $query = $audit->items()->whereNotNull($roundField);

        if ($areaId) {
            $query->where('area_id', $areaId);
        }

        $affected = $query->update([
            $roundField => null,
            "{$roundField}_by_user_id" => null,
            "{$roundField}_at" => null,
        ]);

        StockAuditLog::create([
            'audit_id' => $audit->id,
            'action_type' => 'clear_round',
            'old_status' => $audit->status,
            'new_status' => $audit->status,
            'changed_by_user_id' => $userId,
            'notes' => "Rodada {$round} limpa: {$affected} itens.".($areaId ? " Area ID: {$areaId}" : ''),
        ]);

        return ['error' => false, 'affected' => $affected];
    }

    /**
     * Get a counting summary for the audit.
     */
    public function getCountingSummary(StockAudit $audit): array
    {
        $items = $audit->items;
        $totalItems = $items->count();

        return [
            'total_items' => $totalItems,
            'round_1' => [
                'counted' => $items->whereNotNull('count_1')->count(),
                'finalized' => $audit->count_1_finalized,
            ],
            'round_2' => [
                'counted' => $items->whereNotNull('count_2')->count(),
                'finalized' => $audit->count_2_finalized,
                'required' => $audit->requires_second_count,
            ],
            'round_3' => [
                'counted' => $items->whereNotNull('count_3')->count(),
                'finalized' => $audit->count_3_finalized,
                'required' => $audit->requires_third_count,
            ],
        ];
    }
}
