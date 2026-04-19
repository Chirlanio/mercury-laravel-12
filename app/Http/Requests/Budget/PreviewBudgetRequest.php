<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida o upload inicial para preview — só o arquivo nessa etapa.
 * Year/scope/upload_type vêm só no confirm (ImportBudgetRequest) para
 * o usuário poder corrigir depois de ver o diagnóstico.
 */
class PreviewBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Anexe a planilha do orçamento.',
            'file.mimes' => 'Formato de arquivo inválido (use XLSX, XLS ou CSV).',
            'file.max' => 'Arquivo excede 10MB.',
        ];
    }
}
