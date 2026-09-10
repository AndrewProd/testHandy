<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendManagerReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:8000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'ai_generated' => ['sometimes', 'boolean'],
            'manager_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
