<?php

namespace App\Http\Requests\DamagedProduct;

use Illuminate\Foundation\Http\FormRequest;

class AcceptMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => 'nullable|string|max:50',
        ];
    }
}
