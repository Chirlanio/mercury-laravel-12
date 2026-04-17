<?php

namespace App\Http\Requests\Reversal;

use App\Enums\ReversalPartialMode;
use App\Enums\ReversalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(ReversalType::cases(), 'value');
        $partialModes = array_column(ReversalPartialMode::cases(), 'value');

        return [
            // Lookup da venda
            'invoice_number' => 'required|string|max:50',
            'store_code' => 'nullable|string|max:10|exists:stores,code',
            'movement_date' => 'nullable|date',
            'customer_name' => 'required|string|max:250',
            'employee_id' => 'nullable|integer|exists:employees,id',

            // Tipo / modo
            'type' => ['required', Rule::in($types)],
            'partial_mode' => ['nullable', 'required_if:type,partial', Rule::in($partialModes)],

            // Valor correto (apenas quando partial + by_value)
            'amount_correct' => 'nullable|numeric|min:0|required_if:partial_mode,by_value',

            // Itens (apenas quando partial + by_item)
            'items' => 'nullable|array|required_if:partial_mode,by_item',
            'items.*.movement_id' => 'required|integer|exists:movements,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.product_name' => 'nullable|string|max:255',

            // Classificação
            'reversal_reason_id' => 'required|integer|exists:reversal_reasons,id',
            'expected_refund_date' => 'nullable|date',

            // Pagamento
            'payment_type_id' => 'nullable|integer|exists:payment_types,id',
            'payment_brand' => 'nullable|string|max:50',
            'installments_count' => 'nullable|integer|min:1|max:99',
            'nsu' => 'nullable|string|max:50',
            'authorization_code' => 'nullable|string|max:50',

            // PIX (todos opcionais individualmente; o controller/service fortalece a regra quando payment_type=PIX)
            'pix_key_type' => 'nullable|string|max:30',
            'pix_key' => 'nullable|string|max:255',
            'pix_beneficiary' => 'nullable|string|max:255',
            'pix_bank_id' => 'nullable|integer|exists:banks,id',

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
            'type.required' => 'Informe se o estorno é total ou parcial.',
            'type.in' => 'Tipo de estorno inválido.',
            'partial_mode.required_if' => 'Informe o modo de estorno parcial (por valor ou por produto).',
            'partial_mode.in' => 'Modo de estorno parcial inválido.',
            'amount_correct.required_if' => 'O valor correto é obrigatório em estorno parcial por valor.',
            'amount_correct.numeric' => 'O valor correto deve ser numérico.',
            'items.required_if' => 'Selecione os itens para estorno parcial por produto.',
            'items.*.movement_id.required' => 'Item inválido (movement_id obrigatório).',
            'items.*.movement_id.exists' => 'Item selecionado não existe em movimentações.',
            'reversal_reason_id.required' => 'O motivo do estorno é obrigatório.',
            'reversal_reason_id.exists' => 'Motivo inválido.',
            'files.max' => 'Máximo de 10 anexos por estorno.',
            'files.*.file' => 'Anexo inválido.',
            'files.*.max' => 'Cada anexo deve ter no máximo 10MB.',
        ];
    }
}
