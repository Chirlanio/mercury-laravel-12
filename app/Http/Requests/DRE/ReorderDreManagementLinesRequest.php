<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ReorderDreManagementLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:dre_management_lines,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Informe a lista ordenada de ids.',
            'ids.*.distinct' => 'Não é permitido repetir ids na reordenação.',
        ];
    }
}
