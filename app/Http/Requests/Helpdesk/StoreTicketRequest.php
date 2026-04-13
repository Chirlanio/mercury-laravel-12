<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => 'required|integer|exists:hd_departments,id',
            'category_id' => 'nullable|integer|exists:hd_categories,id',
            'store_id' => 'nullable|string|exists:stores,code',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => 'nullable|integer|in:1,2,3,4',
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'O departamento é obrigatório.',
            'department_id.exists' => 'Departamento inválido.',
            'category_id.exists' => 'Categoria inválida.',
            'store_id.exists' => 'Loja inválida.',
            'title.required' => 'O título é obrigatório.',
            'title.max' => 'O título não pode exceder 255 caracteres.',
            'description.required' => 'A descrição é obrigatória.',
            'description.max' => 'A descrição não pode exceder 5000 caracteres.',
            'priority.in' => 'Prioridade inválida.',
        ];
    }
}
