<?php

namespace App\Http\Requests\DamagedProduct;

use Illuminate\Foundation\Http\FormRequest;

class RejectMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'O motivo da rejeição é obrigatório.',
            'reason.min' => 'O motivo deve ter pelo menos 5 caracteres.',
        ];
    }
}
