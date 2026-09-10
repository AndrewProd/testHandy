<?php

namespace App\Http\Requests;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\ReplyIntake\Enums\Intent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveDripCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'entry_status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'stop_on_reply' => ['sometimes', 'boolean'],
            'handoff_intents' => ['sometimes', 'array'],
            'handoff_intents.*' => [Rule::enum(Intent::class)],
            'field_intents' => ['sometimes', 'array'],
            'field_intents.*' => [Rule::enum(Intent::class)],
            'suppress_intents' => ['sometimes', 'array'],
            'suppress_intents.*' => [Rule::enum(Intent::class)],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'steps.*.channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'steps.*.delay_minutes' => ['required', 'integer', 'min:0'],
            'steps.*.subject' => ['nullable', 'string', 'max:255'],
            'steps.*.message' => ['required', 'string'],
            'steps.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('steps', []) as $index => $step) {
                $channel = DeliveryChannel::tryFrom($step['channel'] ?? '');

                if ($channel?->requiresSubject() && blank($step['subject'] ?? null)) {
                    $validator->errors()->add(
                        "steps.$index.subject",
                        'The subject field is required when channel is email.',
                    );
                }
            }
        });
    }

    public function tenantId(): int
    {
        return (int) ($this->header('X-Tenant-Id') ?: 42);
    }
}
