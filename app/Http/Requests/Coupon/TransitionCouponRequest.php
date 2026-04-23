<?php

namespace App\Http\Requests\Coupon;

use App\Enums\CouponStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_column(CouponStatus::cases(), 'value');

        return [
            'to_status' => ['required', Rule::in($statuses)],
            'note' => 'nullable|string|max:500',
            // Obrigatório apenas em transição para `issued`
            'coupon_site' => [
                Rule::requiredIf(fn () => $this->input('to_status') === CouponStatus::ISSUED->value),
                'nullable',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'to_status.required' => 'Status de destino é obrigatório.',
            'to_status.in' => 'Status de destino inválido.',
            'coupon_site.required' => 'Código do cupom é obrigatório para emissão.',
            'coupon_site.regex' => 'Código aceita apenas letras, números, "_" e "-".',
            'note.max' => 'Observação muito longa (máx 500 caracteres).',
        ];
    }
}
