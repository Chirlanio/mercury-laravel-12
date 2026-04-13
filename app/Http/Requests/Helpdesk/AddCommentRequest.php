<?php

namespace App\Http\Requests\Helpdesk;

use App\Models\HdTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'O comentário é obrigatório.',
            'comment.max' => 'O comentário não pode exceder 5000 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var HdTicket|null $ticket */
            $ticket = $this->route('ticket');
            if ($ticket instanceof HdTicket && $ticket->isTerminal()) {
                $validator->errors()->add(
                    'comment',
                    'Não é possível comentar em um chamado '.HdTicket::STATUS_LABELS[$ticket->status].'.'
                );
            }
        });
    }
}
