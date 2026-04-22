<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class BulkAssignDreMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'account_ids' => 'required|array|min:1|max:500',
            'account_ids.*' => 'integer|distinct|exists:chart_of_accounts,id',
            'cost_center_id' => 'nullable|integer|exists:cost_centers,id',
            'dre_management_line_id' => 'required|integer|exists:dre_management_lines,id',
            'effective_from' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'account_ids.required' => 'Selecione ao menos uma conta para mapear.',
            'account_ids.max' => 'Limite de 500 contas por bulk assign.',
            'dre_management_line_id.required' => 'Selecione a linha gerencial para atribuir.',
            'effective_from.required' => 'Informe a data de vigência inicial.',
        ];
    }
}
