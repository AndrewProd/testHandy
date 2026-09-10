<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DripCampaignStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'status' => $this->status->value,
            'delay_minutes' => $this->delay_minutes,
            'channel' => $this->channel->value,
            'subject' => $this->subject,
            'message' => $this->message,
            'is_active' => $this->is_active,
        ];
    }
}
