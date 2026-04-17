<?php

namespace App\Http\Requests\Reversal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de edição de estorno. Só permite campos não-imutáveis: os
 * snapshots da venda (invoice_number, store_code, sale_total, type,
 * partial_mode) são fixados na criação.
 */
class UpdateReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'sometimes|required|string|max:250',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'reversal_reason_id' => 'sometimes|required|integer|exists:reversal_reasons,id',
            'expected_refund_date' => 'nullable|date',

            'payment_type_id' => 'nullable|integer|exists:payment_types,id',
            'payment_brand' => 'nullable|string|max:50',
            'installments_count' => 'nullable|integer|min:1|max:99',
            'nsu' => 'nullable|string|max:50',
            'authorization_code' => 'nullable|string|max:50',

            'pix_key_type' => 'nullable|string|max:30',
            'pix_key' => 'nullable|string|max:255',
            'pix_beneficiary' => 'nullable|string|max:255',
            'pix_bank_id' => 'nullable|integer|exists:banks,id',

            'notes' => 'nullable|string|max:2000',
        ];
    }
}
