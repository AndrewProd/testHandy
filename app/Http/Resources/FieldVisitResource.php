<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team?->name),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'status' => $this->status->value,
            'distance_km' => $this->distance_km,
            'client' => $this->whenLoaded('request', fn () => $this->request?->client?->name),
            'type' => $this->whenLoaded('request', fn () => $this->request?->type->value),
            'source' => $this->whenLoaded('request', fn () => $this->request?->source->value),
        ];
    }
}
