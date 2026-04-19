<?php

namespace App\Http\Requests\ManagementClass;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManagementClassRequest extends FormRequest
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
            'parent_id' => 'sometimes|nullable|integer|exists:management_classes,id',
            'accounting_class_id' => 'sometimes|nullable|integer|exists:accounting_classes,id',
            'cost_center_id' => 'sometimes|nullable|integer|exists:cost_centers,id',
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
            'parent_id.exists' => 'Conta pai não encontrada.',
            'accounting_class_id.exists' => 'Conta contábil vinculada não encontrada.',
            'cost_center_id.exists' => 'Centro de custo vinculado não encontrado.',
        ];
    }
}
