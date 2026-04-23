<?php

namespace App\Http\Requests\Coupon;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(CouponType::cases(), 'value');

        return [
            // Tipo pode mudar em estados iniciais (service valida no updating)
            'type' => ['nullable', Rule::in($types)],
            'cpf' => 'nullable|string|min:11|max:20',
            'store_code' => 'nullable|string|max:10|exists:stores,code',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'influencer_name' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:60',
            'social_media_id' => 'nullable|integer|exists:social_media,id',
            // Validação contextual no CouponService (via SocialMedia::validateLink)
            'social_media_link' => 'nullable|string|max:250',
            'suggested_coupon' => 'nullable|string|max:30',
            'campaign_name' => 'nullable|string|max:80',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipo de cupom inválido.',
            'cpf.min' => 'CPF inválido (mínimo 11 dígitos).',
            'store_code.exists' => 'Loja informada não existe.',
            'employee_id.exists' => 'Colaborador não encontrado.',
            'social_media_id.exists' => 'Rede social não encontrada.',
            'valid_until.after_or_equal' => 'Data final de validade deve ser igual ou posterior à inicial.',
        ];
    }
}
