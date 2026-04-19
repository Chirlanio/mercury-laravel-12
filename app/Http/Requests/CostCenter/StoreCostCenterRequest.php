<?php

namespace App\Http\Requests\CostCenter;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'area_id' => 'nullable|integer',
            'parent_id' => 'nullable|integer|exists:cost_centers,id',
            'default_accounting_class_id' => 'nullable|integer',
            'manager_id' => 'nullable|integer|exists:managers,id',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código é obrigatório.',
            'code.max' => 'O código deve ter no máximo 20 caracteres.',
            'name.required' => 'O nome é obrigatório.',
            'parent_id.exists' => 'Centro de custo pai não encontrado.',
            'manager_id.exists' => 'Responsável selecionado não encontrado.',
        ];
    }
}
