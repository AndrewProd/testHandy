<?php

namespace Database\Factories;

use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldVisitFactory extends Factory
{
    protected $model = FieldVisit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 42,
            'field_service_request_id' => FieldServiceRequest::factory(),
            'team_id' => FieldTeam::factory(),
            'scheduled_at' => now()->addDays(2)->setTime(14, 0),
            'status' => FieldVisitStatus::Scheduled,
            'distance_km' => 8.2,
        ];
    }
}
