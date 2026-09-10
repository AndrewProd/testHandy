<?php

namespace Database\Factories;

use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\DripCampaign\Models\DripCampaign;
use App\Domain\DripCampaign\Models\DripCampaignStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class DripCampaignStepFactory extends Factory
{
    protected $model = DripCampaignStep::class;

    public function definition(): array
    {
        return [
            'campaign_id' => DripCampaign::factory(),
            'position' => 1,
            'status' => LeadStatus::Dormant,
            'delay_minutes' => 0,
            'channel' => DeliveryChannel::Email,
            'subject' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }
}
