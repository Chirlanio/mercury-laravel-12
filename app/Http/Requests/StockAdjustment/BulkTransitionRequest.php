<?php

namespace App\Http\Requests\StockAdjustment;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'integer|exists:stock_adjustments,id',
            'new_status' => ['required', 'string', Rule::in(array_keys(StockAdjustment::STATUS_LABELS))],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Selecione pelo menos um ajuste.',
            'ids.max' => 'No máximo 50 ajustes por operação em lote.',
        ];
    }
}
