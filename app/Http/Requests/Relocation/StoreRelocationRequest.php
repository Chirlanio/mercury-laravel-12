<?php

namespace App\Http\Requests\Relocation;

use App\Enums\RelocationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRelocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $priorities = array_column(RelocationPriority::cases(), 'value');

        return [
            'relocation_type_id' => 'required|integer|exists:relocation_types,id',
            'origin_store_id' => 'required|integer|exists:stores,id|different:destination_store_id',
            'destination_store_id' => 'required|integer|exists:stores,id',
            'title' => 'nullable|string|max:200',
            'observations' => 'nullable|string|max:2000',
            'priority' => ['nullable', Rule::in($priorities)],
            'deadline_days' => 'nullable|integer|min:1|max:365',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_reference' => 'required|string|max:100',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.product_color' => 'nullable|string|max:80',
            'items.*.size' => 'nullable|string|max:20',
            'items.*.barcode' => 'nullable|string|max:50',
            'items.*.qty_requested' => 'required|integer|min:1',
            'items.*.observations' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'origin_store_id.different' => 'Loja de destino deve ser diferente da loja de origem.',
            'items.required' => 'Adicione pelo menos um produto ao remanejo.',
            'items.min' => 'Adicione pelo menos um produto ao remanejo.',
            'items.*.product_reference.required' => 'Referência do produto é obrigatória.',
            'items.*.qty_requested.min' => 'Quantidade solicitada deve ser maior que zero.',
        ];
    }
}
