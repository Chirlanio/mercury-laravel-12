<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:4|unique:stores,code',
            'name' => 'required|string|max:60',
            'cnpj' => 'required|string|min:14|max:18',
            'company_name' => 'required|string|max:120',
            'state_registration' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'network_id' => 'required|integer|exists:networks,id',
            'manager_id' => 'required|integer|exists:employees,id',
            'supervisor_id' => 'required|integer|exists:employees,id',
            'store_order' => 'required|integer|min:1',
            'network_order' => 'required|integer|min:1',
            'status_id' => 'nullable|integer|exists:statuses,id',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nome',
            'cnpj' => 'CNPJ',
            'company_name' => 'razão social',
            'state_registration' => 'inscrição estadual',
            'address' => 'endereço',
            'network_id' => 'rede',
            'manager_id' => 'gerente',
            'supervisor_id' => 'supervisor',
            'store_order' => 'ordem da loja',
            'network_order' => 'ordem na rede',
            'status_id' => 'status',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe uma loja com este código.',
            'cnpj.min' => 'O CNPJ deve ter pelo menos 14 caracteres.',
            'network_id.exists' => 'A rede selecionada não existe.',
            'manager_id.exists' => 'O gerente selecionado não existe.',
            'supervisor_id.exists' => 'O supervisor selecionado não existe.',
        ];
    }
}
