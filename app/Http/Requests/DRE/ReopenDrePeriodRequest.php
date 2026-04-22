<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para `PATCH /dre/periods/{period}/reopen`.
 *
 * Justificativa é obrigatória (>=10 chars). O service reforça non-empty
 * como segunda camada — validação aqui garante UX clara antes do service.
 */
class ReopenDrePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_PERIODS->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Justificativa é obrigatória.',
            'reason.min' => 'Justificativa precisa ter ao menos 10 caracteres.',
        ];
    }
}
