<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => 'required|integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required' => 'Selecione um técnico para atribuir.',
            'technician_id.exists' => 'Técnico inválido.',
        ];
    }
}
