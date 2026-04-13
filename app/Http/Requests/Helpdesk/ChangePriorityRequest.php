<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;

class ChangePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => 'required|integer|in:1,2,3,4',
        ];
    }

    public function messages(): array
    {
        return [
            'priority.required' => 'A prioridade é obrigatória.',
            'priority.in' => 'Prioridade inválida. Use 1 (Baixa), 2 (Média), 3 (Alta) ou 4 (Urgente).',
        ];
    }
}
