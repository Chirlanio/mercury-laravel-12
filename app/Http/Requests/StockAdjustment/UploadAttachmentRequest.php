<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,xls,xlsx,csv,txt,doc,docx',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo.',
            'file.max' => 'O arquivo não pode exceder 10MB.',
            'file.mimes' => 'Tipo de arquivo não permitido.',
        ];
    }
}
