<?php

namespace App\Http\Requests\Relocation;

use App\Enums\RelocationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transições genéricas. Validação de payload-específico (NF em →in_transit,
 * received_items em →completed/partial) acontece dentro do
 * RelocationTransitionService. Aqui só validamos formato base.
 */
class TransitionRelocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_column(RelocationStatus::cases(), 'value');

        return [
            'to_status' => ['required', Rule::in($statuses)],
            'note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => in_array(
                    $this->input('to_status'),
                    [RelocationStatus::CANCELLED->value, RelocationStatus::REJECTED->value],
                    true
                )),
            ],

            // Payload pra in_separation → in_transit
            'invoice_number' => 'nullable|string|max:50',
            'invoice_date' => 'nullable|date',
            'volumes_qty' => 'nullable|integer|min:1|max:9999',

            // Payload pra in_transit → completed/partial
            'receiver_name' => 'nullable|string|max:150',
            'received_items' => 'nullable|array',
            'received_items.*.id' => 'required_with:received_items|integer',
            'received_items.*.qty_received' => 'required_with:received_items|integer|min:0',
            'received_items.*.reason_code' => 'nullable|string|max:30',
            'received_items.*.observations' => 'nullable|string|max:500',

            // Payload pra in_separation (qty_separated por item)
            'separated_items' => 'nullable|array',
            'separated_items.*.id' => 'required_with:separated_items|integer',
            'separated_items.*.qty_separated' => 'required_with:separated_items|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'to_status.required' => 'Informe o status de destino.',
            'to_status.in' => 'Status de destino inválido.',
            'note.required' => 'É obrigatório informar o motivo.',
        ];
    }
}
