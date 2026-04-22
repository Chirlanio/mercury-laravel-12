<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDreMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'chart_of_account_id' => 'sometimes|required|integer|exists:chart_of_accounts,id',
            'cost_center_id' => 'sometimes|nullable|integer|exists:cost_centers,id',
            'dre_management_line_id' => 'sometimes|required|integer|exists:dre_management_lines,id',
            'effective_from' => 'sometimes|required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
