<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'provider' => $this->provider,
            'model' => $this->model,
            'purpose' => $this->purpose->value,
            'prompt_version' => $this->prompt_version,
            'system_prompt' => $this->system_prompt,
            'user_prompt' => $this->user_prompt,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'total_tokens' => $this->total_tokens,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'structured_output' => $this->structured_output,
            'status' => $this->status->value,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'latency_ms' => $this->latency_ms,
            'cost_usd' => $this->cost_usd,
            'subject' => $this->subject_type
                ? ['type' => class_basename($this->subject_type), 'id' => $this->subject_id]
                : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
        ];
    }
}
