<?php

namespace App\Http\Requests\DamagedProduct;

use App\Enums\FootSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDamagedProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $singleFoot = [FootSide::LEFT->value, FootSide::RIGHT->value];
        $allFoot = array_column(FootSide::cases(), 'value');

        // No update tudo é "sometimes" — só valida se vier no payload.
        return [
            'store_id' => 'sometimes|integer|exists:stores,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_reference' => 'sometimes|string|max:100',
            'product_name' => 'nullable|string|max:255',
            'product_color' => 'nullable|string|max:80',
            'brand_cigam_code' => 'nullable|string|max:30',
            'product_size' => 'nullable|string|max:20',

            'is_mismatched' => 'sometimes|boolean',
            'is_damaged' => 'sometimes|boolean',

            'mismatched_foot' => ['nullable', Rule::in($singleFoot)],
            'mismatched_actual_size' => 'nullable|string|max:20',
            'mismatched_expected_size' => 'nullable|string|max:20|different:mismatched_actual_size',

            'damage_type_id' => 'nullable|integer|exists:damage_types,id',
            'damaged_foot' => ['nullable', Rule::in($allFoot)],
            'damage_description' => 'nullable|string|max:2000',
            'is_repairable' => 'sometimes|boolean',
            'estimated_repair_cost' => 'nullable|numeric|min:0|max:99999.99',

            'notes' => 'nullable|string|max:2000',

            'photos' => 'nullable|array|max:10',
            'photos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'mismatched_expected_size.different' => 'O tamanho esperado deve ser diferente do real.',
            'photos.max' => 'Máximo de 10 fotos por registro.',
            'photos.*.mimes' => 'Aceito JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto deve ter no máximo 5MB.',
        ];
    }
}
