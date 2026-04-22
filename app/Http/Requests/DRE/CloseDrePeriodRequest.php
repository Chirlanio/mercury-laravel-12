<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para `POST /dre/periods`.
 *
 * Valida data do fechamento + notas opcional. A regra de "data > último
 * fechamento" é enforced no service (depende do estado do DB, não vale
 * duplicar como rule estática).
 */
class CloseDrePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_PERIODS->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'closed_up_to_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'closed_up_to_date.required' => 'Data do fechamento é obrigatória.',
            'closed_up_to_date.date_format' => 'Data do fechamento deve estar no formato YYYY-MM-DD.',
        ];
    }
}
