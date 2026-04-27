<?php

namespace App\Http\Requests\Relocation;

use App\Enums\RelocationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edição de remanejo. Em estados pré-aprovação (draft, requested) permite
 * tudo. A partir de approved só campos não-críticos passam pelo Service —
 * o Service filtra silenciosamente os demais.
 */
class UpdateRelocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $priorities = array_column(RelocationPriority::cases(), 'value');

        return [
            'relocation_type_id' => 'sometimes|integer|exists:relocation_types,id',
            'origin_store_id' => 'sometimes|integer|exists:stores,id|different:destination_store_id',
            'destination_store_id' => 'sometimes|integer|exists:stores,id',
            'title' => 'sometimes|nullable|string|max:200',
            'observations' => 'sometimes|nullable|string|max:2000',
            'priority' => ['sometimes', 'nullable', Rule::in($priorities)],
            'deadline_days' => 'sometimes|nullable|integer|min:1|max:365',
        ];
    }
}
