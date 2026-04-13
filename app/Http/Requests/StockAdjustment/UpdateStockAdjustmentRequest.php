<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:employees,id',
            'client_name' => 'nullable|string|max:150',
            'observation' => 'nullable|string|max:2000',

            'items' => 'required|array|min:1|max:200',
            'items.*.reference' => 'required|string|max:100',
            'items.*.size' => 'nullable|string|max:50',
            'items.*.direction' => 'required|in:increase,decrease',
            'items.*.quantity' => 'required|integer|min:1|max:999999',
            'items.*.current_stock' => 'nullable|integer',
            'items.*.reason_id' => 'nullable|integer|exists:stock_adjustment_reasons,id',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.is_adjustment' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Informe pelo menos um item.',
            'items.*.reference.required' => 'A referência do produto é obrigatória.',
            'items.*.direction.required' => 'Informe se o item é de inclusão ou remoção de saldo.',
            'items.*.quantity.required' => 'A quantidade é obrigatória.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
