<?php

namespace App\Http\Requests\DamagedProduct;

use App\Enums\FootSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamagedProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $singleFoot = [FootSide::LEFT->value, FootSide::RIGHT->value];
        $allFoot = array_column(FootSide::cases(), 'value');

        return [
            'store_id' => 'required|integer|exists:stores,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_reference' => 'required|string|max:100',
            'product_name' => 'nullable|string|max:255',
            'product_color' => 'nullable|string|max:80',
            'brand_cigam_code' => 'nullable|string|max:30',
            'product_size' => 'nullable|string|max:20',

            'is_mismatched' => 'sometimes|boolean',
            'is_damaged' => 'sometimes|boolean',

            // Mismatched (condicionais — service também valida regras de negócio)
            'mismatched_foot' => ['nullable', 'required_if:is_mismatched,true', Rule::in($singleFoot)],
            'mismatched_actual_size' => 'nullable|required_if:is_mismatched,true|string|max:20',
            'mismatched_expected_size' => 'nullable|required_if:is_mismatched,true|string|max:20|different:mismatched_actual_size',

            // Damaged (condicionais)
            'damage_type_id' => 'nullable|required_if:is_damaged,true|integer|exists:damage_types,id',
            'damaged_foot' => ['nullable', 'required_if:is_damaged,true', Rule::in($allFoot)],
            'damage_description' => 'nullable|string|max:2000',
            'is_repairable' => 'sometimes|boolean',
            'estimated_repair_cost' => 'nullable|numeric|min:0|max:99999.99',

            'notes' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:today',

            // Fotos múltiplas (max 10 por registro)
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB cada
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'A loja é obrigatória.',
            'store_id.exists' => 'Loja inválida.',
            'product_reference.required' => 'A referência do produto é obrigatória.',
            'mismatched_foot.required_if' => 'Informe qual pé está com tamanho trocado.',
            'mismatched_actual_size.required_if' => 'Informe o tamanho real do pé trocado.',
            'mismatched_expected_size.required_if' => 'Informe o tamanho esperado do pé trocado.',
            'mismatched_expected_size.different' => 'O tamanho esperado deve ser diferente do tamanho real.',
            'damage_type_id.required_if' => 'Informe o tipo de dano.',
            'damaged_foot.required_if' => 'Informe qual pé está avariado.',
            'photos.max' => 'Máximo de 10 fotos por registro.',
            'photos.*.image' => 'Anexo inválido — só aceita imagem.',
            'photos.*.mimes' => 'Aceito JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto deve ter no máximo 5MB.',
        ];
    }
}
