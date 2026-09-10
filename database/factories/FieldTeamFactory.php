<?php

namespace Database\Factories;

use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Models\FieldTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldTeamFactory extends Factory
{
    protected $model = FieldTeam::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 42,
            'name' => 'GTA North',
            'region' => 'Toronto North',
            'services' => [
                FieldServiceType::Measurement->value,
                FieldServiceType::Consultation->value,
                FieldServiceType::QuoteReview->value,
            ],
            'work_starts_at' => '08:00:00',
            'work_ends_at' => '18:00:00',
            'daily_capacity' => 5,
            'lead_cap' => 20,
            'latitude' => 43.7615,
            'longitude' => -79.4111,
            'is_active' => true,
        ];
    }
}
