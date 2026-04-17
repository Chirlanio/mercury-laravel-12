<?php

namespace App\Http\Requests\Reversal;

use App\Enums\ReversalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_column(ReversalStatus::cases(), 'value');

        return [
            'to_status' => ['required', Rule::in($statuses)],
            'note' => [
                'nullable',
                'string',
                'max:2000',
                // Motivo obrigatório no cancelamento
                Rule::requiredIf(fn () => $this->input('to_status') === ReversalStatus::CANCELLED->value),
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
