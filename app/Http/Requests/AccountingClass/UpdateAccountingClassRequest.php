<?php

namespace App\Http\Requests\AccountingClass;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'sometimes|required|string|max:30',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'parent_id' => 'sometimes|nullable|integer|exists:accounting_classes,id',
            'nature' => ['sometimes', 'required', Rule::in(array_column(AccountingNature::cases(), 'value'))],
            'dre_group' => ['sometimes', 'required', Rule::in(array_column(DreGroup::cases(), 'value'))],
            'accepts_entries' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código é obrigatório.',
            'code.max' => 'O código deve ter no máximo 30 caracteres.',
            'name.required' => 'O nome é obrigatório.',
            'nature.in' => 'Natureza inválida.',
            'dre_group.in' => 'Grupo DRE inválido.',
            'parent_id.exists' => 'Conta pai não encontrada.',
        ];
    }
}
