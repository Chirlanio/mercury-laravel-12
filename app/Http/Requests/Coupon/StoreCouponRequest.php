<?php

namespace App\Http\Requests\Coupon;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(CouponType::cases(), 'value');
        $type = $this->input('type');

        $isConsultor = in_array($type, [CouponType::CONSULTOR->value, CouponType::MS_INDICA->value], true);
        $isInfluencer = $type === CouponType::INFLUENCER->value;

        return [
            'type' => ['required', Rule::in($types)],

            // CPF é sempre obrigatório
            'cpf' => 'required|string|min:11|max:20',

            // Consultor/MsIndica: store + employee
            'store_code' => [
                Rule::requiredIf($isConsultor),
                'nullable',
                'string',
                'max:10',
                'exists:stores,code',
            ],
            'employee_id' => [
                Rule::requiredIf($isConsultor),
                'nullable',
                'integer',
                'exists:employees,id',
            ],

            // Influencer: nome + city + social_media_id
            'influencer_name' => [
                Rule::requiredIf($isInfluencer),
                'nullable',
                'string',
                'max:120',
            ],
            'city' => [
                Rule::requiredIf($isInfluencer),
                'nullable',
                'string',
                'max:60',
            ],
            'social_media_id' => [
                Rule::requiredIf($isInfluencer),
                'nullable',
                'integer',
                'exists:social_media,id',
            ],
            // Validação contextual (URL vs @username) é feita no CouponService
            // baseada no link_type da SocialMedia escolhida. Aqui só tamanho.
            'social_media_link' => 'nullable|string|max:250',

            // Opcionais
            'suggested_coupon' => 'nullable|string|max:30',
            'campaign_name' => 'nullable|string|max:80',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:2000',

            // Fluxo: se auto_request=true (padrão) transiciona draft→requested na criação
            'auto_request' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Informe o tipo de cupom (Consultor, Influencer ou MS Indica).',
            'type.in' => 'Tipo de cupom inválido.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.min' => 'CPF inválido (mínimo 11 dígitos).',
            'store_code.required' => 'Loja é obrigatória para este tipo de cupom.',
            'store_code.exists' => 'Loja informada não existe.',
            'employee_id.required' => 'Colaborador é obrigatório para este tipo de cupom.',
            'employee_id.exists' => 'Colaborador não encontrado.',
            'influencer_name.required' => 'Nome do influencer é obrigatório.',
            'city.required' => 'Cidade é obrigatória para influencer.',
            'social_media_id.required' => 'Rede social é obrigatória para influencer.',
            'social_media_id.exists' => 'Rede social não encontrada.',
            'valid_until.after_or_equal' => 'Data final de validade deve ser igual ou posterior à inicial.',
        ];
    }
}
