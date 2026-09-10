<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type->value,
            'sender_id' => $this->sender_id,
            'direction' => $this->direction->value,
            'channel' => $this->channel,
            'body' => $this->body,
            'ai_generated' => $this->ai_generated,
            'provider' => $this->provider,
            'external_message_id' => $this->external_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
