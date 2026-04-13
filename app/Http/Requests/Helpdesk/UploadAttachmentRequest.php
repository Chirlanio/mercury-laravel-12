<?php

namespace App\Http\Requests\Helpdesk;

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
            'file' => 'required|file|max:10240',
            'interaction_id' => 'nullable|integer|exists:hd_interactions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo para enviar.',
            'file.file' => 'Upload inválido.',
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ];
    }
}
