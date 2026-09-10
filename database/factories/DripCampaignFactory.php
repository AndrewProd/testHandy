<?php

namespace Database\Factories;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\DripCampaign\Models\DripCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class DripCampaignFactory extends Factory
{
    protected $model = DripCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 42,
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->sentence(),
            'status' => CampaignStatus::Draft,
            'entry_status' => LeadStatus::Dormant,
            'stop_on_reply' => true,
            'handoff_intents' => DripCampaign::defaultHandoffIntents(),
            'field_intents' => DripCampaign::defaultFieldIntents(),
            'suppress_intents' => DripCampaign::defaultSuppressIntents(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => CampaignStatus::Active]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => CampaignStatus::Paused]);
    }
}
