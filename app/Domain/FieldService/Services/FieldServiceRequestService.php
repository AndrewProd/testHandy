<?php

namespace App\Domain\FieldService\Services;

use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldVisit;
use App\Models\Client;
use App\Models\ReplyTask;
use App\Support\Outbox;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FieldServiceRequestService
{
    public function __construct(
        private readonly FieldDispatchService $dispatch,
        private readonly Outbox $outbox,
    ) {}

    public function createFromReply(
        ReplyTask $task,
        Client $client,
        FieldServiceSource $source,
        FieldServiceType $type = FieldServiceType::Measurement,
        array $overrides = [],
    ): FieldServiceRequest {
        $existing = FieldServiceRequest::query()
            ->where('reply_task_id', $task->id)
            ->first();

        if ($existing !== null) {
            return $existing->load(['client', 'assignedTeam', 'visits.team']);
        }

        return DB::transaction(function () use ($task, $client, $source, $type, $overrides) {
            $request = FieldServiceRequest::query()->create([
                'tenant_id' => $task->tenant_id,
                'client_id' => $client->id,
                'reply_task_id' => $task->id,
                'type' => $type,
                'status' => FieldServiceRequestStatus::New,
                'source' => $source,
                'address' => $overrides['address'] ?? $client->address,
                'city' => $overrides['city'] ?? $client->city,
                'region' => $overrides['region'] ?? $client->region,
                'postal_code' => $overrides['postal_code'] ?? $client->postal_code,
                'latitude' => $overrides['latitude'] ?? $client->latitude,
                'longitude' => $overrides['longitude'] ?? $client->longitude,
                'requested_window' => $overrides['requested_window'] ?? null,
                'requested_at' => $overrides['requested_at'] ?? null,
                'notes' => $overrides['notes'] ?? null,
            ]);

            $this->applyClientLocation($client, $overrides);

            $this->outbox->emit($task->tenant_id, 'field.service_request.created', [
                'field_service_request_id' => $request->id,
                'reply_task_id' => $task->id,
                'client_id' => $client->id,
                'type' => $type->value,
                'source' => $source->value,
                'event_id' => $task->event_id,
            ], 'fsr:' . $task->event_id);

            return $request->load(['client', 'assignedTeam', 'visits.team']);
        });
    }

    public function schedule(FieldServiceRequest $request, int $teamId, Carbon $at): FieldServiceRequest
    {
        $team = FieldTeam::query()
            ->where('tenant_id', $request->tenant_id)
            ->whereKey($teamId)
            ->where('is_active', true)
            ->firstOrFail();

        $offers = $this->dispatch->offers($request, $at);
        $offer = $offers->firstWhere('team_id', $team->id);

        if ($offer === null) {
            throw new InvalidArgumentException('Team is not available for this visit.');
        }

        return DB::transaction(function () use ($request, $team, $at, $offer) {
            $visit = FieldVisit::query()->create([
                'tenant_id' => $request->tenant_id,
                'field_service_request_id' => $request->id,
                'team_id' => $team->id,
                'scheduled_at' => $at,
                'status' => FieldVisitStatus::Scheduled,
                'distance_km' => $offer['distance_km'],
            ]);

            $request->update([
                'status' => FieldServiceRequestStatus::Scheduled,
                'assigned_team_id' => $team->id,
                'requested_at' => $at,
            ]);

            $this->outbox->emit($request->tenant_id, 'field.visit.scheduled', [
                'field_visit_id' => $visit->id,
                'field_service_request_id' => $request->id,
                'team_id' => $team->id,
                'scheduled_at' => $at->toIso8601String(),
                'reply_task_id' => $request->reply_task_id,
            ], 'visit:' . $request->id . ':' . $visit->id);

            return $request->fresh(['client', 'assignedTeam', 'visits.team']);
        });
    }

    public function listForTenant(int $tenantId): Collection
    {
        return FieldServiceRequest::query()
            ->where('tenant_id', $tenantId)
            ->with(['client', 'assignedTeam', 'latestVisit.team'])
            ->latest('id')
            ->get();
    }

    public function findForTenant(int $tenantId, int $id): FieldServiceRequest
    {
        return FieldServiceRequest::query()
            ->where('tenant_id', $tenantId)
            ->with(['client', 'assignedTeam', 'visits.team', 'replyTask'])
            ->findOrFail($id);
    }

    public function listTeams(int $tenantId): Collection
    {
        return $this->teamQuery($tenantId)->orderBy('id')->get();
    }

    public function findTeam(int $tenantId, int $id): FieldTeam
    {
        return $this->teamQuery($tenantId)->findOrFail($id);
    }

    public function updateTeam(FieldTeam $team, array $data): FieldTeam
    {
        if (isset($data['work_starts_at'])) {
            $data['work_starts_at'] = $this->normalizeTime($data['work_starts_at']);
        }
        if (isset($data['work_ends_at'])) {
            $data['work_ends_at'] = $this->normalizeTime($data['work_ends_at']);
        }

        $team->update($data);

        return $this->findTeam($team->tenant_id, $team->id);
    }

    private function teamQuery(int $tenantId)
    {
        return FieldTeam::query()
            ->where('tenant_id', $tenantId)
            ->with(['members'])
            ->withCount([
                'visits as open_visits_count' => fn ($q) => $q->whereIn('status', [
                    FieldVisitStatus::Scheduled->value,
                    FieldVisitStatus::EnRoute->value,
                ]),
                'requests as open_leads_count' => fn ($q) => $q->whereIn('status', [
                    FieldServiceRequestStatus::New->value,
                    FieldServiceRequestStatus::Offered->value,
                    FieldServiceRequestStatus::Scheduled->value,
                    FieldServiceRequestStatus::InProgress->value,
                ]),
            ]);
    }

    private function normalizeTime(string $value): string
    {
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    public function listVisits(int $tenantId): Collection
    {
        return FieldVisit::query()
            ->where('tenant_id', $tenantId)
            ->with(['team', 'request.client'])
            ->orderBy('scheduled_at')
            ->get();
    }

    private function applyClientLocation(Client $client, array $overrides): void
    {
        $fields = ['address', 'city', 'region', 'postal_code', 'latitude', 'longitude'];
        $dirty = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $overrides) && $overrides[$field] !== null) {
                $dirty[$field] = $overrides[$field];
            }
        }

        if ($dirty !== []) {
            $client->forceFill($dirty)->save();
        }
    }
}
