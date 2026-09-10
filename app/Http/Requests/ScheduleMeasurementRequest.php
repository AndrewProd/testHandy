<?php

namespace App\Http\Requests;

use App\Domain\FieldService\Enums\FieldServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(FieldServiceType::class)],
            'requested_window' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'team_id' => ['nullable', 'integer', 'exists:field_teams,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
