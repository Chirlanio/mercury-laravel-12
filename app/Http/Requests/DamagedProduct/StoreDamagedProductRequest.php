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
        $allFoot = array_column(FootSide::cases(), 'value');

        return [
            'store_id' => 'required|integer|exists:stores,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_reference' => 'required|string|max:100',
            'product_name' => 'nullable|string|max:255',
            'product_color' => 'nullable|string|max:80',          // armazena NOME
            'brand_cigam_code' => 'nullable|string|max:30',       // mantido pro matching
            'brand_name' => 'nullable|string|max:100',            // snapshot exibido na UI

            'is_mismatched' => 'sometimes|boolean',
            'is_damaged' => 'sometimes|boolean',

            // Mismatched: 2 cliques (1 em cada linha de pé)
            'mismatched_left_size' => 'nullable|required_if:is_mismatched,true|string|max:20',
            'mismatched_right_size' => 'nullable|required_if:is_mismatched,true|string|max:20|different:mismatched_left_size',

            // Damaged
            'damage_type_id' => 'nullable|required_if:is_damaged,true|integer|exists:damage_types,id',
            'damaged_foot' => ['nullable', 'required_if:is_damaged,true', Rule::in($allFoot)],
            'damaged_size' => 'nullable|string|max:20',
            'damage_description' => 'nullable|string|max:2000',
            'is_repairable' => 'sometimes|boolean',
            'estimated_repair_cost' => 'nullable|numeric|min:0|max:99999.99',

            'notes' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date|after:today',

            // Fotos: obrigatórias quando avaria (pra documentar o dano).
            // Pares trocados isolados não exigem foto.
            'photos' => 'nullable|required_if:is_damaged,true|array|min:1|max:5',
            'photos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'A loja é obrigatória.',
            'store_id.exists' => 'Loja inválida.',
            'product_reference.required' => 'A referência do produto é obrigatória.',
            'mismatched_left_size.required_if' => 'Selecione o tamanho do pé esquerdo.',
            'mismatched_right_size.required_if' => 'Selecione o tamanho do pé direito.',
            'mismatched_right_size.different' => 'O tamanho do pé direito deve ser diferente do esquerdo.',
            'damage_type_id.required_if' => 'Informe o tipo de dano.',
            'damaged_foot.required_if' => 'Informe qual pé está avariado.',
            'photos.required_if' => 'Anexe ao menos uma foto do dano para documentar a avaria.',
            'photos.min' => 'Anexe ao menos uma foto do dano.',
            'photos.max' => 'Máximo de 5 fotos por registro.',
            'photos.*.image' => 'Anexo inválido — só aceita imagem.',
            'photos.*.mimes' => 'Aceito JPG, PNG ou WebP.',
            'photos.*.max' => 'Cada foto deve ter no máximo 5MB.',
        ];
    }
}
