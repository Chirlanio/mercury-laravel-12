<?php

namespace App\Services;

use App\Models\OrderPayment;
use App\Models\OrderPaymentAllocation;
use Illuminate\Support\Facades\DB;

class OrderPaymentAllocationService
{
    /**
     * Validate allocations against order total
     */
    public function validate(float $totalValue, array $allocations): array
    {
        $errors = [];

        if (empty($allocations)) {
            $errors[] = 'Pelo menos uma alocação é necessária.';
            return ['valid' => false, 'errors' => $errors];
        }

        $percentageSum = 0;
        $valueSum = 0;

        foreach ($allocations as $index => $allocation) {
            $num = $index + 1;

            if (empty($allocation['cost_center_id'] ?? null)) {
                $errors[] = "Alocação #{$num}: Centro de custo é obrigatório.";
            }

            $percentage = floatval($allocation['percentage'] ?? 0);
            $value = floatval($allocation['value'] ?? 0);

            if ($percentage <= 0) {
                $errors[] = "Alocação #{$num}: Percentual deve ser maior que zero.";
            }

            if ($value <= 0) {
                $errors[] = "Alocação #{$num}: Valor deve ser maior que zero.";
            }

            $percentageSum += $percentage;
            $valueSum += $value;
        }

        if (abs($percentageSum - 100) > 0.01) {
            $errors[] = "Soma dos percentuais deve ser 100%. Atual: " . number_format($percentageSum, 2) . '%.';
        }

        if (abs($valueSum - $totalValue) > 0.01) {
            $errors[] = "Soma dos valores (R$ " . number_format($valueSum, 2, ',', '.') .
                ") deve ser igual ao total da ordem (R$ " . number_format($totalValue, 2, ',', '.') . ").";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Create allocations for an order
     */
    public function create(OrderPayment $order, array $allocations): bool
    {
        foreach ($allocations as $allocation) {
            $order->allocations()->create([
                'cost_center_id' => $allocation['cost_center_id'],
                'store_id' => $allocation['store_id'] ?? null,
                'allocation_percentage' => $allocation['percentage'],
                'allocation_value' => $allocation['value'],
                'notes' => $allocation['notes'] ?? null,
            ]);
        }

        return true;
    }

    /**
     * Update allocations (delete and recreate)
     */
    public function update(OrderPayment $order, array $allocations): bool
    {
        return DB::transaction(function () use ($order, $allocations) {
            $order->allocations()->delete();
            return $this->create($order, $allocations);
        });
    }

    /**
     * Recalculate allocation values when order total changes
     */
    public function recalculate(OrderPayment $order, float $newTotal): bool
    {
        return DB::transaction(function () use ($order, $newTotal) {
            foreach ($order->allocations as $allocation) {
                $allocation->update([
                    'allocation_value' => round($newTotal * ($allocation->allocation_percentage / 100), 2),
                ]);
            }
            return true;
        });
    }

    /**
     * Get allocations for an order
     */
    public function getByOrderId(int $orderId): array
    {
        return OrderPaymentAllocation::where('order_payment_id', $orderId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }
}
