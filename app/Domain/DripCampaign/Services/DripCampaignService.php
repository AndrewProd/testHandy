<?php

namespace App\Domain\DripCampaign\Services;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\EnrollmentStatus;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\DripCampaign\Models\DripCampaign;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DripCampaignService
{
    public function listForTenant(int $tenantId): Collection
    {
        return $this->baseQuery($tenantId)
            ->orderBy('id')
            ->get();
    }

    public function findForTenant(int $tenantId, int $id): DripCampaign
    {
        return $this->baseQuery($tenantId)->findOrFail($id);
    }

    public function create(int $tenantId, array $data): DripCampaign
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $campaign = DripCampaign::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? CampaignStatus::Draft,
                ...$this->flowAttributes($data),
            ]);

            $this->replaceSteps($campaign, $data['steps']);

            return $this->findForTenant($tenantId, $campaign->id);
        });
    }

    public function update(DripCampaign $campaign, array $data): DripCampaign
    {
        return DB::transaction(function () use ($campaign, $data) {
            $campaign->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                ...$this->flowAttributes($data),
            ]);

            $this->replaceSteps($campaign, $data['steps']);

            return $this->findForTenant($campaign->tenant_id, $campaign->id);
        });
    }

    public function changeStatus(DripCampaign $campaign, CampaignStatus $status): DripCampaign
    {
        $campaign->update(['status' => $status]);

        return $this->findForTenant($campaign->tenant_id, $campaign->id);
    }

    public function delete(DripCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign->steps()->delete();
            $campaign->delete();
        });
    }

    private function replaceSteps(DripCampaign $campaign, array $steps): void
    {
        $campaign->steps()->delete();

        $entry = $campaign->entry_status?->value ?? LeadStatus::Dormant->value;

        foreach (array_values($steps) as $index => $step) {
            $campaign->steps()->create([
                'position' => $index + 1,
                'status' => $step['status'] ?? $entry,
                'delay_minutes' => $step['delay_minutes'],
                'channel' => $step['channel'],
                'subject' => $step['subject'] ?? null,
                'message' => $step['message'],
                'is_active' => $step['is_active'] ?? true,
            ]);
        }
    }

    private function flowAttributes(array $data): array
    {
        return [
            'entry_status' => $data['entry_status'] ?? LeadStatus::Dormant,
            'stop_on_reply' => $data['stop_on_reply'] ?? true,
            'handoff_intents' => $data['handoff_intents'] ?? DripCampaign::defaultHandoffIntents(),
            'field_intents' => $data['field_intents'] ?? DripCampaign::defaultFieldIntents(),
            'suppress_intents' => $data['suppress_intents'] ?? DripCampaign::defaultSuppressIntents(),
        ];
    }

    private function baseQuery(int $tenantId)
    {
        return DripCampaign::query()
            ->where('tenant_id', $tenantId)
            ->with('steps')
            ->withCount([
                'enrollments as enrolled_count',
                'enrollments as active_count' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value),
                'enrollments as stopped_count' => fn ($query) => $query->where('status', EnrollmentStatus::Stopped->value),
                'enrollments as completed_count' => fn ($query) => $query->where('status', EnrollmentStatus::Completed->value),
            ]);
    }
}
