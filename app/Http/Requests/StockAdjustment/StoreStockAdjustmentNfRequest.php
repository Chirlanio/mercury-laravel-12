<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentNfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_adjustment_item_id' => 'nullable|integer|exists:stock_adjustment_items,id',
            'nf_entrada' => 'nullable|string|max:50',
            'nf_saida' => 'nullable|string|max:50',
            'nf_entrada_serie' => 'nullable|string|max:10',
            'nf_saida_serie' => 'nullable|string|max:10',
            'nf_entrada_date' => 'nullable|date',
            'nf_saida_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (empty($this->input('nf_entrada')) && empty($this->input('nf_saida'))) {
                $v->errors()->add('nf_entrada', 'Informe pelo menos uma NF (entrada ou saída).');
            }
        });
    }
}
