<?php

namespace App\Http\Requests\DRE;

use App\Enums\Permission;
use App\Models\DreManagementLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDreManagementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value) ?? false;
    }

    public function rules(): array
    {
        /** @var DreManagementLine|null $line */
        $line = $this->route('management_line');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('dre_management_lines', 'code')->ignore($line?->id),
            ],
            'sort_order' => 'sometimes|required|integer|min:1',
            'is_subtotal' => 'sometimes|boolean',
            'accumulate_until_sort_order' => 'nullable|integer|min:1',
            'level_1' => 'sometimes|required|string|max:150',
            'level_2' => 'nullable|string|max:150',
            'level_3' => 'nullable|string|max:150',
            'level_4' => 'nullable|string|max:150',
            'nature' => ['sometimes', 'required', Rule::in([
                DreManagementLine::NATURE_REVENUE,
                DreManagementLine::NATURE_EXPENSE,
                DreManagementLine::NATURE_SUBTOTAL,
            ])],
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
