<?php

namespace App\Http\Requests\ReturnOrder;

use App\Enums\ReturnReasonCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request de edição. Só permite campos não imutáveis — os snapshots
 * da venda (invoice_number, store_code, movement_date, sale_total,
 * type, amount_items) e os timestamps do workflow são fixados.
 */
class UpdateReturnOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories = array_column(ReturnReasonCategory::cases(), 'value');

        return [
            'customer_name' => 'sometimes|required|string|max:250',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'reason_category' => ['sometimes', 'required', Rule::in($categories)],
            'return_reason_id' => 'nullable|integer|exists:return_reasons,id',
            'refund_amount' => 'nullable|numeric|min:0',
            'reverse_tracking_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
