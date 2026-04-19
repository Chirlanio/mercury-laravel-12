<?php

namespace App\Http\Requests\ReturnOrder;

use App\Enums\ReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionReturnOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_column(ReturnStatus::cases(), 'value');

        return [
            'to_status' => ['required', Rule::in($statuses)],
            'note' => [
                'nullable',
                'string',
                'max:2000',
                // Motivo obrigatório no cancelamento
                Rule::requiredIf(fn () => $this->input('to_status') === ReturnStatus::CANCELLED->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'to_status.required' => 'Informe o status de destino.',
            'to_status.in' => 'Status de destino inválido.',
            'note.required' => 'É obrigatório informar o motivo do cancelamento.',
        ];
    }
}
