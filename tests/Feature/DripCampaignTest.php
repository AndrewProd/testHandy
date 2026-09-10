<?php

namespace Tests\Feature;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\DripCampaign\Models\DripCampaign;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use Tests\TestCase;

class DripCampaignTest extends TestCase
{
    public function test_meta_returns_enum_options(): void
    {
        $response = $this->getJson('/api/campaigns/meta');

        $response->assertOk()
            ->assertJsonPath('lead_statuses.0.value', LeadStatus::Dormant->value)
            ->assertJsonPath('channels.0.value', DeliveryChannel::Email->value)
            ->assertJsonPath('campaign_statuses.0.value', CampaignStatus::Draft->value);
    }

    public function test_it_creates_a_campaign_with_steps_in_one_transaction(): void
    {
        $response = $this->postJson('/api/campaigns', $this->payload(), $this->headers());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New lead follow-up')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.step_count', 2)
            ->assertJsonPath('data.entry_status', 'dormant')
            ->assertJsonPath('data.stop_on_reply', true)
            ->assertJsonPath('data.steps.0.status', 'dormant')
            ->assertJsonPath('data.steps.0.channel', 'email')
            ->assertJsonPath('data.steps.0.delay_minutes', 0)
            ->assertJsonPath('data.steps.1.status', 'contacted')
            ->assertJsonPath('data.steps.1.channel', 'sms')
            ->assertJsonPath('data.steps.1.delay_minutes', 2880);

        $this->assertDatabaseHas('drip_campaigns', [
            'name' => 'New lead follow-up',
            'tenant_id' => 42,
            'status' => 'draft',
        ]);
        $this->assertDatabaseCount('drip_campaign_steps', 2);
    }

    public function test_it_rejects_unknown_status_and_channel(): void
    {
        $payload = $this->payload();
        $payload['steps'][0]['status'] = 'brand_new';
        $payload['steps'][0]['channel'] = 'carrier_pigeon';

        $this->postJson('/api/campaigns', $payload, $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['steps.0.status', 'steps.0.channel']);

        $this->assertDatabaseCount('drip_campaigns', 0);
    }

    public function test_email_steps_require_a_subject(): void
    {
        $payload = $this->payload();
        $payload['steps'][0]['subject'] = null;

        $this->postJson('/api/campaigns', $payload, $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['steps.0.subject']);
    }

    public function test_update_replaces_steps(): void
    {
        $campaign = DripCampaign::factory()->create(['tenant_id' => 42]);
        $campaign->steps()->create([
            'position' => 1,
            'status' => LeadStatus::Dormant,
            'delay_minutes' => 0,
            'channel' => DeliveryChannel::Email,
            'subject' => 'Old',
            'message' => 'Old body',
        ]);

        $payload = $this->payload();
        $payload['name'] = 'Renamed';
        $payload['status'] = CampaignStatus::Active->value;
        $payload['entry_status'] = LeadStatus::Dormant->value;
        $payload['stop_on_reply'] = true;
        $payload['handoff_intents'] = ['interested', 'call_back'];
        $payload['field_intents'] = ['request_measurement'];
        $payload['suppress_intents'] = ['unsubscribe'];
        $payload['steps'] = [
            [
                'status' => LeadStatus::Interested->value,
                'channel' => DeliveryChannel::Whatsapp->value,
                'delay_minutes' => 60,
                'subject' => null,
                'message' => 'Call you tomorrow?',
            ],
        ];

        $this->putJson('/api/campaigns/' . $campaign->id, $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.step_count', 1)
            ->assertJsonPath('data.steps.0.channel', 'whatsapp')
            ->assertJsonPath('data.handoff_intents', ['interested', 'call_back'])
            ->assertJsonPath('data.field_intents.0', 'request_measurement');

        $this->assertDatabaseCount('drip_campaign_steps', 1);
        $this->assertDatabaseHas('drip_campaign_steps', [
            'campaign_id' => $campaign->id,
            'status' => 'interested',
            'channel' => 'whatsapp',
            'message' => 'Call you tomorrow?',
        ]);
    }

    public function test_index_is_scoped_to_tenant_and_includes_enrollment_counts(): void
    {
        $ours = DripCampaign::factory()->active()->create(['tenant_id' => 42, 'name' => 'Ours']);
        DripCampaign::factory()->create(['tenant_id' => 43, 'name' => 'Theirs']);

        $client = Client::factory()->create(['tenant_id' => 42]);
        CampaignEnrollment::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'campaign_id' => $ours->id,
            'status' => 'active',
        ]);

        $this->getJson('/api/campaigns', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ours')
            ->assertJsonPath('data.0.enrolled', 1)
            ->assertJsonPath('data.0.active', 1);
    }

    public function test_show_returns_404_for_another_tenant(): void
    {
        $campaign = DripCampaign::factory()->create(['tenant_id' => 42]);

        $this->getJson('/api/campaigns/' . $campaign->id, ['X-Tenant-Id' => '43'])
            ->assertNotFound();
    }

    public function test_pause_and_delete(): void
    {
        $campaign = DripCampaign::factory()->active()->create(['tenant_id' => 42]);
        $campaign->steps()->create([
            'position' => 1,
            'status' => LeadStatus::Dormant,
            'delay_minutes' => 0,
            'channel' => DeliveryChannel::Email,
            'subject' => 'Hello',
            'message' => 'Body',
        ]);

        $this->patchJson('/api/campaigns/' . $campaign->id . '/status', [
            'status' => CampaignStatus::Paused->value,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->deleteJson('/api/campaigns/' . $campaign->id, [], $this->headers())
            ->assertNoContent();

        $this->assertDatabaseMissing('drip_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseCount('drip_campaign_steps', 0);
    }

    private function headers(): array
    {
        return ['X-Tenant-Id' => '42'];
    }

    private function payload(): array
    {
        return [
            'name' => 'New lead follow-up',
            'description' => 'First touch then a wait.',
            'status' => CampaignStatus::Draft->value,
            'steps' => [
                [
                    'status' => LeadStatus::Dormant->value,
                    'channel' => DeliveryChannel::Email->value,
                    'delay_minutes' => 0,
                    'subject' => 'Thanks for the request',
                    'message' => 'Here is our information.',
                ],
                [
                    'status' => LeadStatus::Contacted->value,
                    'channel' => DeliveryChannel::Sms->value,
                    'delay_minutes' => 2880,
                    'subject' => null,
                    'message' => 'Want an exact calculation?',
                ],
            ],
        ];
    }
}
