<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleFieldVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:field_teams,id'],
            'scheduled_at' => ['required', 'date'],
        ];
    }
}
