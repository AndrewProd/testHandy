<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['work_starts_at', 'work_ends_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && strlen($value) >= 5) {
                $this->merge([$field => substr($value, 0, 5)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'daily_capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'lead_cap' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'work_starts_at' => ['sometimes', 'date_format:H:i'],
            'work_ends_at' => ['sometimes', 'date_format:H:i', 'after:work_starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
