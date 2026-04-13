<?php

namespace App\Http\Requests\StockAdjustment;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_status' => ['required', 'string', Rule::in(array_keys(StockAdjustment::STATUS_LABELS))],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'new_status.required' => 'O novo status é obrigatório.',
            'new_status.in' => 'Status inválido.',
            'notes.max' => 'As notas não podem exceder 2000 caracteres.',
        ];
    }
}
