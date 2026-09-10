<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DripCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrolled = (int) ($this->enrolled_count ?? 0);
        $stopped = (int) ($this->stopped_count ?? 0);
        $delays = collect($this->relationLoaded('steps') ? $this->steps : [])
            ->filter(fn ($step) => $step->delay_minutes > 0)
            ->pluck('delay_minutes');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'entry_status' => ($this->entry_status ?? \App\Domain\DripCampaign\Enums\LeadStatus::Dormant)->value,
            'stop_on_reply' => (bool) ($this->stop_on_reply ?? true),
            'handoff_intents' => $this->handoff_intents ?? \App\Domain\DripCampaign\Models\DripCampaign::defaultHandoffIntents(),
            'field_intents' => $this->field_intents ?? \App\Domain\DripCampaign\Models\DripCampaign::defaultFieldIntents(),
            'suppress_intents' => $this->suppress_intents ?? \App\Domain\DripCampaign\Models\DripCampaign::defaultSuppressIntents(),
            'steps' => DripCampaignStepResource::collection($this->whenLoaded('steps')),
            'step_count' => $this->whenLoaded('steps', fn () => $this->steps->count()),
            'enrolled' => $enrolled,
            'active' => (int) ($this->active_count ?? 0),
            'stopped' => $stopped,
            'completed' => (int) ($this->completed_count ?? 0),
            'reply_rate' => $enrolled > 0 ? round($stopped / $enrolled, 4) : 0,
            'cadence_days' => $delays->isEmpty()
                ? 0
                : (int) round($delays->avg() / 1440),
        ];
    }
}
