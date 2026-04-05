<?php

namespace App\Services;

use App\Models\OrderPayment;
use App\Models\OrderPaymentStatusHistory;
use Illuminate\Support\Facades\DB;

class OrderPaymentTransitionService
{
    /**
     * Required fields per transition (from_to)
     */
    private const REQUIRED_FIELDS = [
        'backlog_doing' => ['number_nf', 'launch_number'],
        'doing_waiting' => ['launch_number'],
        'waiting_done' => ['date_paid'],
    ];

    /**
     * Conditional fields based on payment type
     */
    private const CONDITIONAL_FIELDS = [
        'doing_waiting' => [
            'default' => ['bank_name', 'agency', 'checking_account'],
            'pix' => ['pix_key_type', 'pix_key'],
            'boleto' => [],
        ],
    ];

    private const PIX_TYPES = ['PIX'];
    private const BOLETO_TYPES = ['Boleto'];

    /**
     * Validate a status transition
     */
    public function validateTransition(
        OrderPayment $order,
        string $newStatus,
        array $data
    ): array {
        $errors = [];

        if (!$order->canTransitionTo($newStatus)) {
            $errors[] = "Transição de '{$order->status_label}' para '" .
                (OrderPayment::STATUS_LABELS[$newStatus] ?? $newStatus) . "' não é permitida.";
            return ['valid' => false, 'errors' => $errors];
        }

        $transitionKey = "{$order->status}_{$newStatus}";

        // Check required fields
        $requiredFields = self::REQUIRED_FIELDS[$transitionKey] ?? [];
        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? $order->$field;
            if (empty($value)) {
                $errors[] = "O campo '{$this->fieldLabel($field)}' é obrigatório para esta transição.";
            }
        }

        // Check conditional fields based on payment type
        $conditionalConfig = self::CONDITIONAL_FIELDS[$transitionKey] ?? null;
        if ($conditionalConfig) {
            $paymentType = $order->payment_type;
            $conditionalFields = $this->resolveConditionalFields($conditionalConfig, $paymentType);

            foreach ($conditionalFields as $field) {
                $value = $data[$field] ?? $order->$field;
                if (empty($value)) {
                    $errors[] = "O campo '{$this->fieldLabel($field)}' é obrigatório para pagamento tipo '{$paymentType}'.";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Execute a status transition
     */
    public function executeTransition(
        OrderPayment $order,
        string $newStatus,
        array $additionalFields,
        int $userId,
        ?string $notes = null
    ): bool {
        return DB::transaction(function () use ($order, $newStatus, $additionalFields, $userId, $notes) {
            $oldStatus = $order->status;

            $updateData = array_merge($additionalFields, [
                'status' => $newStatus,
                'updated_by_user_id' => $userId,
            ]);

            if ($newStatus === OrderPayment::STATUS_DONE && !empty($additionalFields['date_paid'])) {
                $updateData['date_paid'] = $additionalFields['date_paid'];
            }

            $order->update($updateData);

            $this->recordStatusHistory($order->id, $oldStatus, $newStatus, $userId, $notes);

            return true;
        });
    }

    /**
     * Record a status change in history
     */
    public function recordStatusHistory(
        int $orderId,
        ?string $oldStatus,
        string $newStatus,
        int $userId,
        ?string $notes = null
    ): void {
        OrderPaymentStatusHistory::create([
            'order_payment_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Get allowed transitions for a given status
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return OrderPayment::VALID_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Resolve which conditional fields apply based on payment type
     */
    private function resolveConditionalFields(array $config, ?string $paymentType): array
    {
        if ($paymentType && in_array($paymentType, self::PIX_TYPES)) {
            return $config['pix'] ?? [];
        }

        if ($paymentType && in_array($paymentType, self::BOLETO_TYPES)) {
            return $config['boleto'] ?? [];
        }

        return $config['default'] ?? [];
    }

    /**
     * Human-readable field labels
     */
    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'number_nf' => 'Número NF',
            'launch_number' => 'Número Lançamento',
            'date_paid' => 'Data de Pagamento',
            'bank_name' => 'Banco',
            'agency' => 'Agência',
            'checking_account' => 'Conta Corrente',
            'pix_key_type' => 'Tipo Chave PIX',
            'pix_key' => 'Chave PIX',
            default => $field,
        };
    }
}
