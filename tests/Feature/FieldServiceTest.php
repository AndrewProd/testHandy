<?php

namespace Tests\Feature;

use App\Contracts\SentimentClassifier;
use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Models\FieldTeam;
use App\Domain\FieldService\Models\FieldVisit;
use App\Jobs\ProcessInboundReplyJob;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\OutboxEvent;
use App\Models\ReplyTask;
use Tests\Support\StableClassifier;
use Tests\TestCase;

class FieldServiceTest extends TestCase
{
    public function test_high_confidence_measurement_intent_creates_a_field_request(): void
    {
        $this->useClassifier(StableClassifier::analysis('interested', 'request_measurement', 0.96));
        [$client] = $this->enroll($this->northYork());
        $this->team('GTA North', 'Toronto North', 43.7615, -79.4111);

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'event_id' => 'evt_measure',
            'body_plain' => "I'd like someone to come and measure.",
        ])));

        $task = ReplyTask::query()->sole();
        $fsr = FieldServiceRequest::query()->sole();

        $this->assertSame('interested', $task->sentiment);
        $this->assertSame('request_measurement', $task->intent->value);
        $this->assertEqualsWithDelta(0.96, $task->confidence, 0.001);
        $this->assertSame(FieldServiceType::Measurement, $fsr->type);
        $this->assertSame(FieldServiceSource::AiAuto, $fsr->source);
        $this->assertSame(FieldServiceRequestStatus::New, $fsr->status);
        $this->assertSame($task->id, $fsr->reply_task_id);
        $this->assertSame($client->id, $fsr->client_id);

        $subjects = OutboxEvent::query()->orderBy('id')->pluck('subject')->all();
        $this->assertSame(['field.service_request.created', 'events.reply.processed'], $subjects);
        $this->assertSame($fsr->id, OutboxEvent::query()->where('subject', 'events.reply.processed')->value('payload')['field_service_request_id']);
    }

    public function test_measurement_wording_upgrades_an_interested_reply(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));
        [$client] = $this->enroll($this->northYork());
        $this->team('GTA North', 'Toronto North', 43.7615, -79.4111);

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'body_plain' => 'Sounds good. Can you send someone to measure the windows this week?',
        ])));

        $task = ReplyTask::query()->sole();
        $this->assertSame('request_measurement', $task->intent->value);
        $this->assertGreaterThanOrEqual(0.85, $task->confidence);
        $this->assertSame(1, FieldServiceRequest::query()->count());
    }

    public function test_interested_without_measurement_does_not_create_a_field_request(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));
        [$client] = $this->enroll();

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'event_id' => 'evt_ob_1',
            'body_plain' => 'Yes, please go ahead.',
        ])));

        $this->assertSame(0, FieldServiceRequest::query()->count());
        $this->assertSame(1, OutboxEvent::query()->count());
        $this->assertNull(OutboxEvent::query()->sole()->payload['field_service_request_id']);
    }

    public function test_low_confidence_measurement_stays_on_the_manager(): void
    {
        $this->useClassifier(StableClassifier::analysis('interested', 'request_measurement', 0.4));
        [$client] = $this->enroll($this->northYork());

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'body_plain' => 'Maybe a measure visit later.',
        ])));

        $this->assertSame('request_measurement', ReplyTask::query()->sole()->intent->value);
        $this->assertSame(0, FieldServiceRequest::query()->count());
    }

    public function test_manager_schedules_a_measurement_and_dispatch_picks_the_closest_team(): void
    {
        $client = Client::factory()->create($this->northYork());
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'sentiment' => 'interested',
            'intent' => 'interested',
            'confidence' => 0.91,
            'status' => 'in_progress',
            'body' => 'Sounds good. Can you call me Thursday afternoon? I still have the quote.',
        ]);

        $north = $this->team('GTA North', 'Toronto North', 43.7615, -79.4111);
        $this->team('GTA West', 'Mississauga / Oakville', 43.5890, -79.6441);
        $this->team('Ottawa', 'Ottawa', 45.4215, -75.6972);

        $created = $this->postJson('/api/reply-tasks/' . $task->id . '/schedule-measurement', [
            'requested_window' => 'Thursday afternoon',
            'address' => $client->address,
            'city' => $client->city,
            'region' => $client->region,
        ], $this->headers());

        $created->assertCreated()
            ->assertJsonPath('data.field_service_request.source', 'manager')
            ->assertJsonPath('data.field_service_request.type', 'measurement')
            ->assertJsonPath('data.field_service_request.status', 'new');

        $offers = $created->json('data.offers');
        $this->assertNotEmpty($offers);
        $this->assertSame($north->id, $offers[0]['team_id']);
        $this->assertTrue($offers[0]['region_match']);
        $this->assertSame('Window Measurement', $offers[0]['skill']);

        $fsrId = $created->json('data.field_service_request.id');
        $when = $offers[0]['proposed_at'];

        $this->postJson('/api/field/requests/' . $fsrId . '/schedule', [
            'team_id' => $north->id,
            'scheduled_at' => $when,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.assigned_team_id', $north->id);

        $visit = FieldVisit::query()->sole();
        $this->assertSame($north->id, $visit->team_id);
        $this->assertSame('scheduled', $visit->status->value);
        $this->assertTrue(
            OutboxEvent::query()->where('subject', 'field.visit.scheduled')->exists()
        );
    }

    public function test_manager_cannot_schedule_from_an_unsubscribe_task(): void
    {
        $client = Client::factory()->create($this->northYork());
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'sentiment' => 'unsubscribe',
            'intent' => 'unsubscribe',
        ]);

        $this->postJson('/api/reply-tasks/' . $task->id . '/schedule-measurement', [
            'requested_window' => 'Monday',
        ], $this->headers())->assertUnprocessable();

        $this->assertSame(0, FieldServiceRequest::query()->count());
    }

    public function test_team_settings_update_lead_and_visit_caps(): void
    {
        $team = $this->team('GTA North', 'Toronto North', 43.7615, -79.4111);

        $this->patchJson('/api/field/teams/' . $team->id, [
            'lead_cap' => 12,
            'daily_capacity' => 3,
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.lead_cap', 12)
            ->assertJsonPath('data.daily_capacity', 3)
            ->assertJsonPath('data.work_starts_at', '09:00')
            ->assertJsonPath('data.work_ends_at', '17:00');

        $this->assertDatabaseHas('field_teams', [
            'id' => $team->id,
            'lead_cap' => 12,
            'daily_capacity' => 3,
        ]);
    }

    public function test_dispatch_skips_a_team_at_lead_cap(): void
    {
        $client = Client::factory()->create($this->northYork());
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'sentiment' => 'interested',
            'intent' => 'interested',
        ]);

        $north = $this->team('GTA North', 'Toronto North', 43.7615, -79.4111);
        $north->update(['lead_cap' => 1]);
        $west = $this->team('GTA West', 'Mississauga / Oakville', 43.5890, -79.6441);

        FieldServiceRequest::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'assigned_team_id' => $north->id,
            'status' => FieldServiceRequestStatus::Scheduled,
            'region' => 'Toronto North',
        ]);

        $created = $this->postJson('/api/reply-tasks/' . $task->id . '/schedule-measurement', [
            'requested_window' => 'Thursday afternoon',
            'region' => 'Toronto North',
        ], $this->headers())->assertCreated();

        $ids = collect($created->json('data.offers'))->pluck('team_id')->all();
        $this->assertNotContains($north->id, $ids);
        $this->assertContains($west->id, $ids);
    }

    public function test_reply_task_show_includes_client_address_and_schedule_flag(): void
    {
        $client = Client::factory()->create($this->northYork() + ['name' => 'Dana Walker']);
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'sentiment' => 'interested',
            'intent' => 'interested',
            'confidence' => 0.91,
        ]);

        $this->getJson('/api/reply-tasks/' . $task->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.can_schedule_measurement', true)
            ->assertJsonPath('data.client.region', 'Toronto North')
            ->assertJsonPath('data.intent', 'interested')
            ->assertJsonPath('events.0.type', 'reply.received')
            ->assertJsonPath('events.2.type', 'reply_task.created');
    }

    /**
     * @return array{0: Client, 1: CampaignEnrollment}
     */
    private function enroll(array $clientAttrs = []): array
    {
        $client = Client::factory()->create($clientAttrs);

        $enrollment = CampaignEnrollment::factory()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        return [$client, $enrollment];
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_' . bin2hex(random_bytes(6)),
            'tenant_id' => $client->tenant_id,
            'sender' => $client->email,
            'recipient' => 'campaign+c' . $client->tenant_id . '@mg.ourdomain.com',
            'body_plain' => 'Sounds good. Call me Thursday.',
            'headers' => ['Auto-Submitted' => 'no'],
            'timestamp' => 1756713600,
        ], $overrides);
    }

    private function team(string $name, string $region, float $lat, float $lng): FieldTeam
    {
        $team = FieldTeam::factory()->create([
            'name' => $name,
            'region' => $region,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        $team->members()->create([
            'name' => $name . ' lead',
            'role' => 'lead',
            'skills' => ['Window Measurement', 'Consultation', 'Quote Review'],
            'is_active' => true,
        ]);

        return $team;
    }

    private function northYork(): array
    {
        return [
            'name' => 'Dana Walker',
            'address' => '18 Finch Ave W',
            'city' => 'North York',
            'region' => 'Toronto North',
            'postal_code' => 'M2N 2G9',
            'latitude' => 43.7807,
            'longitude' => -79.4163,
        ];
    }

    private function headers(): array
    {
        return ['X-Tenant-Id' => '42'];
    }

    private function useClassifier(SentimentClassifier $classifier): void
    {
        $this->app->instance(SentimentClassifier::class, $classifier);
    }
}
