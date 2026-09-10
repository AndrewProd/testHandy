<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopilotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['draft', 'improve', 'summarize', 'next_action'])],
            'body' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
