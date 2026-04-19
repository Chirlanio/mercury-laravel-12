<?php

namespace App\Http\Requests\CostCenter;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'sometimes|required|string|max:20',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'area_id' => 'sometimes|nullable|integer',
            'parent_id' => 'sometimes|nullable|integer|exists:cost_centers,id',
            'default_accounting_class_id' => 'sometimes|nullable|integer',
            'manager_id' => 'sometimes|nullable|integer|exists:managers,id',
            'is_active' => 'sometimes|boolean',
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
