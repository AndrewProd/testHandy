<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'reply_task_id' => $this->reply_task_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'requested_window' => $this->requested_window,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'notes' => $this->notes,
            'assigned_team_id' => $this->assigned_team_id,
            'assigned_team' => $this->whenLoaded('assignedTeam', fn () => $this->assignedTeam?->name),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'visits' => FieldVisitResource::collection($this->whenLoaded('visits')),
            'latest_visit' => $this->whenLoaded('latestVisit', fn () => new FieldVisitResource($this->latestVisit)),
        ];
    }
}
