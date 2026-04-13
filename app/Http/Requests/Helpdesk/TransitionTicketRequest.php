<?php

namespace App\Http\Requests\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(HdTicket::STATUS_LABELS))],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O novo status é obrigatório.',
            'status.in' => 'Status inválido.',
            'notes.max' => 'As notas não podem exceder 2000 caracteres.',
        ];
    }
}
