<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use App\Models\DreManagementLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDreManagementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:dre_management_lines,code',
            'sort_order' => 'required|integer|min:1',
            'is_subtotal' => 'sometimes|boolean',
            'accumulate_until_sort_order' => 'nullable|integer|min:1',
            'level_1' => 'required|string|max:150',
            'level_2' => 'nullable|string|max:150',
            'level_3' => 'nullable|string|max:150',
            'level_4' => 'nullable|string|max:150',
            'nature' => ['required', Rule::in([
                DreManagementLine::NATURE_REVENUE,
                DreManagementLine::NATURE_EXPENSE,
                DreManagementLine::NATURE_SUBTOTAL,
            ])],
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe uma linha com este código.',
            'level_1.required' => 'O rótulo da linha (ex: "(+) Faturamento Bruto") é obrigatório.',
            'nature.in' => 'Natureza deve ser "revenue", "expense" ou "subtotal".',
        ];
    }
}
