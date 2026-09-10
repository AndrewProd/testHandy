<?php

namespace App\Http\Resources;

use App\Domain\ReplyIntake\Enums\Intent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReplyTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $intent = $this->intent;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->client_id,
            'event_id' => $this->event_id,
            'sentiment' => $this->sentiment,
            'intent' => $intent?->value,
            'confidence' => $this->confidence,
            'body' => $this->body,
            'status' => $this->status,
            'assignee_id' => $this->assignee_id,
            'campaign_id' => $this->campaign_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => ConversationMessageResource::collection($this->whenLoaded('messages')),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => sprintf('+1 416-555-%04d', 1000 + $this->client->id),
                'address' => $this->client->address,
                'city' => $this->client->city,
                'region' => $this->client->region,
                'postal_code' => $this->client->postal_code,
                'latitude' => $this->client->latitude,
                'longitude' => $this->client->longitude,
            ]),
            'field_service_request' => $this->when(
                $this->relationLoaded('fieldServiceRequest') && $this->fieldServiceRequest,
                fn () => new FieldServiceRequestResource($this->fieldServiceRequest),
            ),
            'can_schedule_measurement' => $this->fieldServiceRequest === null
                && ($intent === null || $intent->allowsManagerMeasurement()),
            'step' => $this->when(
                $this->relationLoaded('client') && $this->client?->relationLoaded('enrollments'),
                fn () => $this->client->enrollments->first()?->current_step,
            ),
        ];
    }
}
