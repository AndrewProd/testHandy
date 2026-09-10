<?php

namespace Database\Factories;

use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldTeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldTeamMemberFactory extends Factory
{
    protected $model = FieldTeamMember::class;

    public function definition(): array
    {
        return [
            'team_id' => FieldTeam::factory(),
            'name' => $this->faker->name(),
            'role' => 'technician',
            'skills' => ['Window Measurement', 'Consultation'],
            'is_active' => true,
        ];
    }
}
