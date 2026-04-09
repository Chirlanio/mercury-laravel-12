<?php

namespace App\Services;

use App\Models\StockAudit;
use App\Models\StockAuditAccuracyHistory;
use App\Models\StockAuditLog;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class StockAuditTransitionService
{
    public function __construct(
        private StockAuditCigamService $cigamService,
    ) {}

    public function validateTransition(StockAudit $audit, string $newStatus, array $data = []): array
    {
        $errors = [];

        if (! $audit->canTransitionTo($newStatus)) {
            $fromLabel = StockAudit::STATUS_LABELS[$audit->status] ?? $audit->status;
            $toLabel = StockAudit::STATUS_LABELS[$newStatus] ?? $newStatus;
            $errors[] = "Transicao de '{$fromLabel}' para '{$toLabel}' nao e permitida.";
        }

        // Validate counting → reconciliation: at least round 1 must be finalized
        if ($newStatus === 'reconciliation') {
            if (! $audit->count_1_finalized) {
                $errors[] = 'A primeira rodada de contagem deve ser finalizada antes da conciliacao.';
            }
            if ($audit->requires_second_count && ! $audit->count_2_finalized) {
                $errors[] = 'A segunda rodada de contagem deve ser finalizada.';
            }
            if ($audit->requires_third_count && ! $audit->count_3_finalized) {
                $errors[] = 'A terceira rodada de contagem deve ser finalizada.';
            }
        }

        // Validate reconciliation → finished: all phases must be complete
        if ($newStatus === 'finished') {
            $itemsWithoutResolution = $audit->items()
                ->whereNull('accepted_count')
                ->where('system_quantity', '!=', 0)
                ->count();

            if ($itemsWithoutResolution > 0) {
                $errors[] = "Existem {$itemsWithoutResolution} item(ns) sem contagem aceita.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function executeTransition(StockAudit $audit, string $newStatus, array $data, int $userId): StockAudit
    {
        return DB::transaction(function () use ($audit, $newStatus, $data, $userId) {
            $oldStatus = $audit->status;

            $updateData = [
                'status' => $newStatus,
                'updated_by_user_id' => $userId,
            ];

            // Side effects per transition target
            if ($newStatus === 'counting') {
                $updateData['authorized_by_user_id'] = $userId;
                $updateData['authorized_at'] = now();
                $updateData['started_at'] = now();
            }

            if ($newStatus === 'reconciliation') {
                $updateData['reconciliation_phase'] = 'A';
            }

            if ($newStatus === 'finished') {
                $updateData['finished_at'] = now();
                $this->calculateAccuracy($audit);
            }

            if ($newStatus === 'cancelled') {
                $updateData['cancelled_by_user_id'] = $userId;
                $updateData['cancelled_at'] = now();
                $updateData['cancellation_reason'] = $data['cancellation_reason'] ?? null;
            }

            $audit->update($updateData);
            $audit->refresh();

            // Load system stock when entering counting phase
            if ($newStatus === 'counting') {
                $store = Store::find($audit->store_id);
                if ($store) {
                    $result = $this->cigamService->loadSystemStock($audit->id, $store->code, $audit->audit_type);
                    $audit->update(['total_items_counted' => $result['total']]);
                }
            }

            // Record accuracy history when finished
            if ($newStatus === 'finished') {
                $audit->refresh();
                StockAuditAccuracyHistory::create([
                    'store_id' => $audit->store_id,
                    'audit_id' => $audit->id,
                    'accuracy_percentage' => $audit->accuracy_percentage ?? 0,
                    'total_items' => $audit->total_items_counted,
                    'total_divergences' => $audit->total_divergences,
                    'financial_loss' => $audit->financial_loss,
                    'financial_surplus' => $audit->financial_surplus,
                    'financial_loss_cost' => $audit->financial_loss_cost,
                    'financial_surplus_cost' => $audit->financial_surplus_cost,
                    'audit_type' => $audit->audit_type,
                    'audit_date' => now()->toDateString(),
                ]);
            }

            // Log the transition
            StockAuditLog::create([
                'audit_id' => $audit->id,
                'action_type' => 'transition',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            return $audit;
        });
    }

    private function calculateAccuracy(StockAudit $audit): void
    {
        $items = $audit->items()->whereNotNull('accepted_count')->get();

        $totalItems = $items->count();
        $divergences = $items->where('divergence', '!=', 0)->count();
        $financialLoss = 0;
        $financialSurplus = 0;
        $financialLossCost = 0;
        $financialSurplusCost = 0;

        foreach ($items as $item) {
            $div = (float) $item->accepted_count - (float) $item->system_quantity;
            $divValue = $div * (float) $item->unit_price;
            $divValueCost = $div * (float) $item->cost_price;

            $item->update([
                'divergence' => $div,
                'divergence_value' => $divValue,
                'divergence_value_cost' => $divValueCost,
            ]);

            if ($div < 0) {
                $financialLoss += abs($divValue);
                $financialLossCost += abs($divValueCost);
            } elseif ($div > 0) {
                $financialSurplus += $divValue;
                $financialSurplusCost += $divValueCost;
            }
        }

        $accuracy = $totalItems > 0 ? (($totalItems - $divergences) / $totalItems) * 100 : 0;

        $audit->update([
            'accuracy_percentage' => round($accuracy, 2),
            'total_items_counted' => $totalItems,
            'total_divergences' => $divergences,
            'financial_loss' => round($financialLoss, 2),
            'financial_surplus' => round($financialSurplus, 2),
            'financial_loss_cost' => round($financialLossCost, 2),
            'financial_surplus_cost' => round($financialSurplusCost, 2),
        ]);
    }
}
