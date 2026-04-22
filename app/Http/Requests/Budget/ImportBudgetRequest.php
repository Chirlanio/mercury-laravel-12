<?php

namespace App\Http\Requests\Budget;

use App\Enums\BudgetUploadType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirma o import após o usuário ter feito o mapping de reconciliação.
 *
 * Valores do mapping ({'accounting_class' => {'CODE-X' => 42}}) são
 * números inteiros que referenciam FKs existentes — a UI os obteve
 * das suggestions do preview ou do próprio cadastro.
 */
class ImportBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(BudgetUploadType::cases(), 'value');

        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'year' => 'required|integer|min:2000|max:2100',
            'scope_label' => 'required|string|min:2|max:100',
            'area_department_id' => 'required|integer|exists:management_classes,id',
            'upload_type' => ['required', Rule::in($types)],
            'notes' => 'nullable|string|max:2000',

            // Mapping do usuário: chaves são os codes da planilha, valores IDs
            'mapping' => 'nullable|array',
            'mapping.accounting_class' => 'nullable|array',
            'mapping.accounting_class.*' => 'integer|exists:chart_of_accounts,id',
            'mapping.management_class' => 'nullable|array',
            'mapping.management_class.*' => 'integer|exists:management_classes,id',
            'mapping.cost_center' => 'nullable|array',
            'mapping.cost_center.*' => 'integer|exists:cost_centers,id',
            'mapping.store' => 'nullable|array',
            'mapping.store.*' => 'integer|exists:stores,id',
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'O ano é obrigatório.',
            'scope_label.required' => 'Informe o escopo.',
            'area_department_id.required' => 'Selecione a Área (departamento) do orçamento.',
            'area_department_id.exists' => 'Área selecionada não existe.',
            'upload_type.required' => 'Informe se é novo ou ajuste.',
            'upload_type.in' => 'Tipo inválido.',
            'mapping.accounting_class.*.exists' => 'Conta contábil mapeada não encontrada.',
            'mapping.management_class.*.exists' => 'Conta gerencial mapeada não encontrada.',
            'mapping.cost_center.*.exists' => 'Centro de custo mapeado não encontrado.',
            'mapping.store.*.exists' => 'Loja mapeada não encontrada.',
        ];
    }
}
