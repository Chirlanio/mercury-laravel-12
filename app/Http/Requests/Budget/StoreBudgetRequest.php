<?php

namespace App\Http\Requests\Budget;

use App\Enums\BudgetUploadType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create de um BudgetUpload na Fase 1 MVP:
 *  - header: year + scope_label + upload_type + notes + file
 *  - items[]: array de linhas com FKs já resolvidas (AC/MC/CC + store opcional)
 *    + 12 valores mensais (decimal BR já convertido para float/string numérico)
 *
 * Upload XLSX + parse com preview/fuzzy é escopo da Fase 2 — aqui o
 * arquivo é apenas armazenado para auditoria/re-download.
 */
class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(BudgetUploadType::cases(), 'value');

        return [
            'year' => 'required|integer|min:2000|max:2100',
            'scope_label' => 'required|string|min:2|max:100',
            // Fase 5: Área (departamento gerencial sintético). Obrigatório
            // em uploads novos; uploads legacy sem área são backfilled.
            'area_department_id' => 'required|integer|exists:management_classes,id',
            'upload_type' => ['required', Rule::in($types)],
            'notes' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB
            'items' => 'required|array|min:1',
            'items.*.accounting_class_id' => 'required|integer|exists:accounting_classes,id',
            'items.*.management_class_id' => 'required|integer|exists:management_classes,id',
            'items.*.cost_center_id' => 'required|integer|exists:cost_centers,id',
            'items.*.store_id' => 'nullable|integer|exists:stores,id',
            'items.*.supplier' => 'nullable|string|max:255',
            'items.*.justification' => 'nullable|string|max:2000',
            'items.*.account_description' => 'nullable|string|max:255',
            'items.*.class_description' => 'nullable|string|max:255',
            'items.*.month_01_value' => 'nullable|numeric|min:0',
            'items.*.month_02_value' => 'nullable|numeric|min:0',
            'items.*.month_03_value' => 'nullable|numeric|min:0',
            'items.*.month_04_value' => 'nullable|numeric|min:0',
            'items.*.month_05_value' => 'nullable|numeric|min:0',
            'items.*.month_06_value' => 'nullable|numeric|min:0',
            'items.*.month_07_value' => 'nullable|numeric|min:0',
            'items.*.month_08_value' => 'nullable|numeric|min:0',
            'items.*.month_09_value' => 'nullable|numeric|min:0',
            'items.*.month_10_value' => 'nullable|numeric|min:0',
            'items.*.month_11_value' => 'nullable|numeric|min:0',
            'items.*.month_12_value' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'O ano é obrigatório.',
            'year.min' => 'Ano inválido.',
            'year.max' => 'Ano inválido.',
            'scope_label.required' => 'Informe o escopo (ex: Administrativo, TI, Geral).',
            'scope_label.min' => 'Escopo deve ter ao menos 2 caracteres.',
            'area_department_id.required' => 'Selecione a Área (departamento) do orçamento.',
            'area_department_id.exists' => 'Área selecionada não existe.',
            'upload_type.required' => 'Informe se é um orçamento novo ou um ajuste.',
            'upload_type.in' => 'Tipo de upload inválido.',
            'file.required' => 'Anexe a planilha original do orçamento.',
            'file.mimes' => 'Formato de arquivo inválido (use XLSX, XLS ou CSV).',
            'file.max' => 'Arquivo excede 10MB.',
            'items.required' => 'O orçamento deve ter ao menos 1 linha.',
            'items.min' => 'O orçamento deve ter ao menos 1 linha.',
            'items.*.accounting_class_id.required' => 'Toda linha precisa de conta contábil.',
            'items.*.accounting_class_id.exists' => 'Conta contábil informada não existe.',
            'items.*.management_class_id.required' => 'Toda linha precisa de conta gerencial.',
            'items.*.management_class_id.exists' => 'Conta gerencial informada não existe.',
            'items.*.cost_center_id.required' => 'Toda linha precisa de centro de custo.',
            'items.*.cost_center_id.exists' => 'Centro de custo informado não existe.',
            'items.*.store_id.exists' => 'Loja informada não existe.',
        ];
    }
}
