<?php

namespace App\Http\Requests;

use App\Domain\ReplyIntake\Enums\Intent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReplyTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([
                'open',
                'in_progress',
                'waiting_customer',
                'resolved',
                'dismissed',
            ])],
            'sentiment' => ['sometimes', 'nullable', 'string', Rule::in([
                'interested',
                'question',
                'not_now',
                'unsubscribe',
                'wrong_person',
                'auto_reply',
            ])],
            'intent' => ['sometimes', 'nullable', Rule::enum(Intent::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
