<?php

namespace App\Http\Requests\ReturnOrder;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(ReturnType::cases(), 'value');
        $categories = array_column(ReturnReasonCategory::cases(), 'value');

        return [
            // Lookup da venda
            'invoice_number' => 'required|string|max:50',
            'store_code' => 'nullable|string|max:10|exists:stores,code',
            'movement_date' => 'nullable|date',
            'customer_name' => 'required|string|max:250',
            'employee_id' => 'nullable|integer|exists:employees,id',

            // Tipo e categoria
            'type' => ['required', Rule::in($types)],
            'reason_category' => ['required', Rule::in($categories)],
            'return_reason_id' => 'nullable|integer|exists:return_reasons,id',

            // Reembolso (exigido quando type=estorno/credito)
            'refund_amount' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => in_array(
                    $this->input('type'),
                    [ReturnType::ESTORNO->value, ReturnType::CREDITO->value],
                    true
                )),
            ],

            // Itens (obrigatório — ao menos 1)
            'items' => 'required|array|min:1',
            'items.*.movement_id' => 'required|integer|exists:movements,id',
            'items.*.quantity' => 'nullable|numeric|min:0.001',
            'items.*.product_name' => 'nullable|string|max:255',

            // Logística reversa (texto livre)
            'reverse_tracking_code' => 'nullable|string|max:100',

            'notes' => 'nullable|string|max:2000',

            // Anexos múltiplos
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240', // 10MB cada
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.required' => 'O número da NF/cupom é obrigatório.',
            'customer_name.required' => 'O nome do cliente é obrigatório.',
            'type.required' => 'Informe o tipo (troca, estorno ou crédito).',
            'type.in' => 'Tipo de devolução inválido.',
            'reason_category.required' => 'Informe a categoria do motivo.',
            'reason_category.in' => 'Categoria inválida.',
            'refund_amount.required' => 'Valor de reembolso é obrigatório em estorno e crédito.',
            'items.required' => 'Selecione ao menos um item para devolução.',
            'items.min' => 'Selecione ao menos um item.',
            'items.*.movement_id.required' => 'Item inválido (movement_id obrigatório).',
            'items.*.movement_id.exists' => 'Item selecionado não existe em movimentações.',
            'files.max' => 'Máximo de 10 anexos por devolução.',
            'files.*.file' => 'Anexo inválido.',
            'files.*.max' => 'Cada anexo deve ter no máximo 10MB.',
        ];
    }
}
