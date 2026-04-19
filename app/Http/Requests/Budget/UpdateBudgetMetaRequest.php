<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Apenas campos editáveis pós-upload (MVP). Valores e items são
 * imutáveis — para corrigir, faça novo upload com type=ajuste.
 */
class UpdateBudgetMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
