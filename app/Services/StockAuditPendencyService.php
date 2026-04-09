<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\Transfer;
use Illuminate\Support\Facades\Log;

class StockAuditPendencyService
{
    /**
     * Get all pending items across available modules for a store.
     * Returns: [product_reference => [array of pendencies by module]]
     */
    public function getAllPendencies(string $storeCode): array
    {
        $consolidated = [];

        $checkers = [
            'relocations' => fn () => $this->checkRelocations($storeCode),
            'adjustments' => fn () => $this->checkAdjustments($storeCode),
            // Future modules - add when implemented:
            // 'consignments' => fn () => $this->checkConsignments($storeCode),
            // 'returns' => fn () => $this->checkReturns($storeCode),
            // 'reversals' => fn () => $this->checkReversals($storeCode),
            // 'service_orders' => fn () => $this->checkServiceOrders($storeCode),
        ];

        foreach ($checkers as $module => $checker) {
            try {
                $pendencies = $checker();
                foreach ($pendencies as $ref => $data) {
                    if (! isset($consolidated[$ref])) {
                        $consolidated[$ref] = [];
                    }
                    $consolidated[$ref][] = $data;
                }
            } catch (\Exception $e) {
                Log::warning("StockAuditPendency: {$module} check failed", ['error' => $e->getMessage()]);
            }
        }

        return $consolidated;
    }

    /**
     * Check pending relocations (transfers with type=relocation).
     */
    private function checkRelocations(string $storeCode): array
    {
        $map = [];

        try {
            $transfers = Transfer::where('transfer_type', 'relocation')
                ->whereIn('status', ['pending', 'in_transit'])
                ->where(function ($q) use ($storeCode) {
                    $q->whereHas('originStore', fn ($sq) => $sq->where('code', $storeCode))
                        ->orWhereHas('destinationStore', fn ($sq) => $sq->where('code', $storeCode));
                })
                ->with(['originStore', 'destinationStore'])
                ->get();

            foreach ($transfers as $transfer) {
                $key = "transfer_{$transfer->id}";
                $origin = $transfer->originStore->name ?? $transfer->origin_store_id;
                $destination = $transfer->destinationStore->name ?? $transfer->destination_store_id;

                $map[$key] = [
                    'module' => 'Remanejo',
                    'badge_color' => 'info',
                    'badge_icon' => 'TruckIcon',
                    'count' => 1,
                    'total_quantity' => $transfer->products_qty ?? 0,
                    'details' => "#{$transfer->id}: {$origin} → {$destination} | Status: ".(Transfer::STATUS_LABELS[$transfer->status] ?? $transfer->status),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('StockAuditPendency: relocations failed', ['error' => $e->getMessage()]);
        }

        return $map;
    }

    /**
     * Check pending stock adjustments.
     */
    private function checkAdjustments(string $storeCode): array
    {
        $map = [];

        try {
            $adjustments = StockAdjustment::active()
                ->whereIn('status', ['pending', 'under_analysis', 'awaiting_response', 'balance_transfer'])
                ->whereHas('store', fn ($q) => $q->where('code', $storeCode))
                ->with(['items', 'store'])
                ->get();

            foreach ($adjustments as $adjustment) {
                foreach ($adjustment->items as $item) {
                    $ref = $item->reference ?? "adj_{$adjustment->id}";
                    if (! isset($map[$ref])) {
                        $map[$ref] = [
                            'module' => 'Ajuste',
                            'badge_color' => 'primary',
                            'badge_icon' => 'ScaleIcon',
                            'count' => 0,
                            'total_quantity' => 0,
                            'details' => '',
                        ];
                    }
                    $map[$ref]['count']++;
                    $statusLabel = StockAdjustment::STATUS_LABELS[$adjustment->status] ?? $adjustment->status;
                    $map[$ref]['details'] = "Ajuste #{$adjustment->id} | Status: {$statusLabel}";
                }
            }
        } catch (\Exception $e) {
            Log::warning('StockAuditPendency: adjustments failed', ['error' => $e->getMessage()]);
        }

        return $map;
    }
}
