<?php

namespace App\Domain\FieldService\Services;

use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldVisit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FieldDispatchService
{
    public function offers(FieldServiceRequest $request, ?Carbon $at = null): Collection
    {
        $at = $at ?? $this->proposeTime($request, null);

        $teams = FieldTeam::query()
            ->where('tenant_id', $request->tenant_id)
            ->where('is_active', true)
            ->with(['members' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return $teams
            ->map(fn (FieldTeam $team) => $this->score($request, $team, $at))
            ->filter()
            ->sortBy([
                ['region_match', 'desc'],
                ['distance_km', 'asc'],
                ['remaining_capacity', 'desc'],
            ])
            ->values();
    }

    public function proposeTime(FieldServiceRequest $request, ?FieldTeam $team): Carbon
    {
        $now = now();
        $text = strtolower((string) $request->requested_window);
        $date = $now->copy()->startOfDay();
        $mentionedWeekday = false;

        $weekdays = [
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY,
        ];

        foreach ($weekdays as $name => $iso) {
            if (!str_contains($text, $name)) {
                continue;
            }

            $mentionedWeekday = true;
            $date = $now->dayOfWeek === $iso
                ? $now->copy()->startOfDay()
                : $now->copy()->next($iso)->startOfDay();
            break;
        }

        if (!$mentionedWeekday && $now->hour >= 16) {
            $date = $now->copy()->addDay()->startOfDay();
        }

        while ($date->isWeekend()) {
            $date->addDay();
        }

        $hour = 14;
        if (str_contains($text, 'morning')) {
            $hour = 10;
        } elseif (str_contains($text, 'evening')) {
            $hour = 16;
        } elseif (str_contains($text, 'afternoon')) {
            $hour = 14;
        }

        if ($team !== null) {
            $hour = min(max($hour, $team->workStartHour()), max($team->workEndHour() - 1, $team->workStartHour()));
        }

        $slot = $date->copy()->setTime($hour, 0);

        if ($slot->lessThanOrEqualTo($now)) {
            $slot->addDay();
            while ($slot->isWeekend()) {
                $slot->addDay();
            }
        }

        return $slot;
    }

    private function score(FieldServiceRequest $request, FieldTeam $team, Carbon $at): ?array
    {
        if (!$team->offersService($request->type)) {
            return null;
        }

        $hour = (int) $at->format('G');
        if ($hour < $team->workStartHour() || $hour >= $team->workEndHour()) {
            return null;
        }

        $openLeads = $this->openLeads($team);
        if ($openLeads >= $team->lead_cap) {
            return null;
        }

        $used = $this->usedCapacity($team, $at);
        if ($used >= $team->daily_capacity) {
            return null;
        }

        $skill = $this->matchingSkill($team, $request);
        if ($skill === null) {
            return null;
        }

        $distance = $this->distanceKm(
            $request->latitude,
            $request->longitude,
            $team->latitude,
            $team->longitude,
        );

        $regionMatch = $request->region !== null
            && $request->region !== ''
            && strcasecmp($request->region, $team->region) === 0;

        $proposed = $this->proposeTime($request, $team);

        return [
            'team_id' => $team->id,
            'name' => $team->name,
            'region' => $team->region,
            'region_match' => $regionMatch,
            'distance_km' => $distance,
            'proposed_at' => $proposed->toIso8601String(),
            'availability' => $proposed->format('H:i'),
            'skill' => $skill,
            'used_capacity' => $used,
            'daily_capacity' => $team->daily_capacity,
            'remaining_capacity' => $team->daily_capacity - $used,
            'open_leads' => $openLeads,
            'lead_cap' => $team->lead_cap,
            'work_hours' => substr((string) $team->work_starts_at, 0, 5) . '–' . substr((string) $team->work_ends_at, 0, 5),
            'services' => $team->services,
        ];
    }

    private function matchingSkill(FieldTeam $team, FieldServiceRequest $request): ?string
    {
        $wanted = match ($request->type->value) {
            'measurement' => 'measurement',
            'consultation' => 'consultation',
            'quote_review' => 'quote review',
            'install' => 'install',
            default => $request->type->value,
        };

        foreach ($team->members as $member) {
            if ($member->hasSkill($wanted) || $member->hasSkill('window ' . $wanted)) {
                return collect($member->skills)->first(
                    fn ($skill) => str_contains(strtolower((string) $skill), $wanted),
                ) ?? $wanted;
            }
        }

        return null;
    }

    private function openLeads(FieldTeam $team): int
    {
        return FieldServiceRequest::query()
            ->where('assigned_team_id', $team->id)
            ->whereIn('status', [
                FieldServiceRequestStatus::New->value,
                FieldServiceRequestStatus::Offered->value,
                FieldServiceRequestStatus::Scheduled->value,
                FieldServiceRequestStatus::InProgress->value,
            ])
            ->count();
    }

    private function usedCapacity(FieldTeam $team, Carbon $at): int
    {
        return FieldVisit::query()
            ->where('team_id', $team->id)
            ->whereDate('scheduled_at', $at->toDateString())
            ->whereIn('status', [
                FieldVisitStatus::Scheduled->value,
                FieldVisitStatus::EnRoute->value,
            ])
            ->count();
    }

    public function distanceKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }
}
