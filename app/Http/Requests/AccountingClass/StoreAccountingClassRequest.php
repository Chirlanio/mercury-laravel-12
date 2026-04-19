<?php

namespace App\Http\Requests\AccountingClass;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|integer|exists:accounting_classes,id',
            'nature' => ['required', Rule::in(array_column(AccountingNature::cases(), 'value'))],
            'dre_group' => ['required', Rule::in(array_column(DreGroup::cases(), 'value'))],
            'accepts_entries' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código é obrigatório.',
            'code.max' => 'O código deve ter no máximo 30 caracteres.',
            'name.required' => 'O nome é obrigatório.',
            'nature.required' => 'Informe a natureza (devedora/credora).',
            'nature.in' => 'Natureza inválida.',
            'dre_group.required' => 'Informe o grupo do DRE.',
            'dre_group.in' => 'Grupo DRE inválido.',
            'parent_id.exists' => 'Conta pai não encontrada.',
        ];
    }
}
