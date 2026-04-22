<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreDreMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'chart_of_account_id' => 'required|integer|exists:chart_of_accounts,id',
            'cost_center_id' => 'nullable|integer|exists:cost_centers,id',
            'dre_management_line_id' => 'required|integer|exists:dre_management_lines,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'chart_of_account_id.required' => 'Selecione a conta contábil.',
            'dre_management_line_id.required' => 'Selecione a linha gerencial.',
            'effective_from.required' => 'Informe a data de vigência inicial.',
            'effective_to.after_or_equal' => 'A vigência final não pode ser anterior à inicial.',
        ];
    }
}
