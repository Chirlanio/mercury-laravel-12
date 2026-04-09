<?php

namespace App\Services;

use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\StockAuditLog;
use App\Models\StockAuditStoreJustification;

class StockAuditReconciliationService
{
    /**
     * Phase A: Auto-resolve items where counts match.
     * count_1 == count_2 → accepted_count = count_1, resolution_type = auto
     * count_1 != count_2 && count_3 exists → accepted_count = count_3
     * Only count_1 (no second count) → accepted_count = count_1
     */
    public function autoResolvePhaseA(StockAudit $audit): array
    {
        $items = $audit->items()->get();
        $autoResolved = 0;
        $needsManual = 0;
        $thirdCountNeeded = 0;

        foreach ($items as $item) {
            if ($item->accepted_count !== null) {
                continue; // Already resolved
            }

            // Single count only
            if (! $audit->requires_second_count || $item->count_2 === null) {
                if ($item->count_1 !== null) {
                    $item->update([
                        'accepted_count' => $item->count_1,
                        'resolution_type' => 'auto',
                    ]);
                    $autoResolved++;
                }

                continue;
            }

            // Two counts match
            if ((float) $item->count_1 === (float) $item->count_2) {
                $item->update([
                    'accepted_count' => $item->count_1,
                    'resolution_type' => 'auto',
                ]);
                $autoResolved++;

                continue;
            }

            // Two counts differ, third count exists
            if ($item->count_3 !== null) {
                $item->update([
                    'accepted_count' => $item->count_3,
                    'resolution_type' => 'auto',
                ]);
                $autoResolved++;

                continue;
            }

            // Two counts differ, no third count - needs manual resolution
            if ($audit->requires_third_count) {
                $thirdCountNeeded++;
            } else {
                $needsManual++;
            }
        }

        $audit->update(['reconciliation_phase' => 'A']);

        return [
            'auto_resolved' => $autoResolved,
            'needs_manual' => $needsManual,
            'third_count_needed' => $thirdCountNeeded,
        ];
    }

    /**
     * Phase A: Manually resolve an item.
     */
    public function manualResolve(StockAuditItem $item, float $acceptedCount, ?string $note, int $userId): StockAuditItem
    {
        $item->update([
            'accepted_count' => $acceptedCount,
            'resolution_type' => 'manual',
            'observation' => $note,
        ]);

        StockAuditLog::create([
            'audit_id' => $item->audit_id,
            'action_type' => 'manual_resolve',
            'changed_by_user_id' => $userId,
            'notes' => "Item #{$item->id} resolvido manualmente: {$acceptedCount}",
        ]);

        return $item->fresh();
    }

    /**
     * Phase B: Calculate divergences between accepted_count and system_quantity.
     */
    public function calculateDivergences(StockAudit $audit): array
    {
        $items = $audit->items()->whereNotNull('accepted_count')->get();

        $totalDivergences = 0;
        $losses = 0;
        $surpluses = 0;

        foreach ($items as $item) {
            $divergence = (float) $item->accepted_count - (float) $item->system_quantity;
            $divValue = $divergence * (float) $item->unit_price;
            $divValueCost = $divergence * (float) $item->cost_price;

            $item->update([
                'divergence' => round($divergence, 2),
                'divergence_value' => round($divValue, 2),
                'divergence_value_cost' => round($divValueCost, 2),
            ]);

            if ($divergence != 0) {
                $totalDivergences++;
                if ($divergence < 0) {
                    $losses += abs($divValue);
                } else {
                    $surpluses += $divValue;
                }
            }
        }

        $audit->update(['reconciliation_phase' => 'B']);

        return [
            'total_items' => $items->count(),
            'total_divergences' => $totalDivergences,
            'financial_loss' => round($losses, 2),
            'financial_surplus' => round($surpluses, 2),
        ];
    }

    /**
     * Phase B: Justify an item (auditor justification).
     */
    public function justifyItem(StockAuditItem $item, string $note, int $userId): StockAuditItem
    {
        $item->update([
            'is_justified' => true,
            'justification_note' => $note,
            'justified_by_user_id' => $userId,
            'justified_at' => now(),
        ]);

        return $item->fresh();
    }

    /**
     * Phase C: Submit store justification for a divergent item.
     */
    public function submitStoreJustification(StockAuditItem $item, array $data, int $userId): StockAuditStoreJustification
    {
        $justification = StockAuditStoreJustification::create([
            'audit_id' => $item->audit_id,
            'item_id' => $item->id,
            'justification_text' => $data['justification_text'],
            'found_quantity' => $data['found_quantity'] ?? null,
            'submitted_by_user_id' => $userId,
            'submitted_at' => now(),
        ]);

        $item->audit->update(['reconciliation_phase' => 'C']);

        return $justification;
    }

    /**
     * Phase C: Review a store justification (accept/reject).
     */
    public function reviewJustification(StockAuditStoreJustification $justification, string $status, ?string $note, int $userId): StockAuditStoreJustification
    {
        $justification->update([
            'review_status' => $status,
            'reviewed_by_user_id' => $userId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        // If accepted and found_quantity provided, update the item
        if ($status === 'accepted' && $justification->found_quantity !== null) {
            $justification->item->update([
                'store_justified' => true,
                'store_justified_quantity' => $justification->found_quantity,
            ]);
        } elseif ($status === 'accepted') {
            $justification->item->update(['store_justified' => true]);
        }

        StockAuditLog::create([
            'audit_id' => $justification->audit_id,
            'action_type' => "justification_{$status}",
            'changed_by_user_id' => $userId,
            'notes' => "Justificativa #{$justification->id} {$status}".($note ? ": {$note}" : ''),
        ]);

        return $justification->fresh();
    }
}
