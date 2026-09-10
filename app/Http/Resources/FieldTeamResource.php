<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'services' => $this->services ?? [],
            'work_starts_at' => substr((string) $this->work_starts_at, 0, 5),
            'work_ends_at' => substr((string) $this->work_ends_at, 0, 5),
            'daily_capacity' => $this->daily_capacity,
            'lead_cap' => $this->lead_cap,
            'open_leads' => (int) ($this->open_leads_count ?? 0),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'members' => FieldTeamMemberResource::collection($this->whenLoaded('members')),
            'members_count' => (int) ($this->members_count ?? ($this->relationLoaded('members') ? $this->members->count() : 0)),
            'open_visits' => (int) ($this->open_visits_count ?? 0),
        ];
    }
}
