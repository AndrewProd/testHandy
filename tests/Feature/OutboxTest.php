<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundReplyJob;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Contracts\SentimentClassifier;
use App\Models\OutboxEvent;
use Tests\Support\StableClassifier;
use Tests\TestCase;

/**
 * The outbox row is written in the same transaction as the reply task, so the
 * same "exactly one per event" guarantee applies to it.
 */
class OutboxTest extends TestCase
{
    public function test_processing_a_reply_writes_one_pending_outbox_row(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));
        [$client] = $this->enroll();

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'event_id'   => 'evt_ob_1',
            'body_plain' => 'Yes, please go ahead.',
        ])));

        $row = OutboxEvent::query()->sole();

        $this->assertSame('events.reply.processed', $row->subject);
        $this->assertSame(OutboxEvent::STATUS_PENDING, $row->status);
        $this->assertSame('evt_ob_1', $row->dedupe_key);
        $this->assertSame($client->tenant_id, $row->tenant_id);
        $this->assertSame('evt_ob_1', $row->payload['event_id']);
        $this->assertSame('interested', $row->payload['sentiment']);
        $this->assertTrue($row->payload['campaign_stopped']);
        $this->assertFalse($row->payload['client_suppressed']);
        $this->assertNull($row->published_at);
    }

    public function test_unsubscribe_is_reflected_in_the_outbox_payload(): void
    {
        $this->useClassifier(StableClassifier::timeout());
        [$client] = $this->enroll();

        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'event_id'   => 'evt_ob_2',
            'body_plain' => 'Please take me off your list.',
        ])));

        $row = OutboxEvent::query()->sole();

        $this->assertSame('unsubscribe', $row->payload['sentiment']);
        $this->assertTrue($row->payload['client_suppressed']);
        $this->assertTrue($row->payload['campaign_stopped']);
    }

    public function test_duplicate_event_writes_exactly_one_outbox_row(): void
    {
        $this->useClassifier(StableClassifier::label('question'));
        [$client] = $this->enroll();

        $payload = $this->payload($client, ['event_id' => 'evt_ob_dup']);

        dispatch_sync(new ProcessInboundReplyJob($payload));
        dispatch_sync(new ProcessInboundReplyJob($payload));

        $this->assertSame(1, OutboxEvent::query()->count());
    }

    public function test_no_outbox_row_when_no_task_is_created(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));
        [$client] = $this->enroll();

        // sender is our own campaign address -> no client, no task, no event
        dispatch_sync(new ProcessInboundReplyJob($this->payload($client, [
            'event_id' => 'evt_ob_none',
            'sender'   => 'campaign+c42@mg.ourdomain.com',
        ])));

        $this->assertSame(0, OutboxEvent::query()->count());
    }

    /**
     * @return array{0: Client, 1: CampaignEnrollment}
     */
    private function enroll(array $clientAttrs = [], array $enrollmentAttrs = []): array
    {
        $client = Client::factory()->create($clientAttrs);

        $enrollment = CampaignEnrollment::factory()->create(array_merge([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'status'    => 'active',
        ], $enrollmentAttrs));

        return [$client, $enrollment];
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'event_id'   => 'evt_' . bin2hex(random_bytes(6)),
            'tenant_id'  => $client->tenant_id,
            'sender'     => $client->email,
            'recipient'  => 'campaign+c' . $client->tenant_id . '@mg.ourdomain.com',
            'body_plain' => 'Sounds good. Call me Thursday.',
            'headers'    => ['Auto-Submitted' => 'no'],
            'timestamp'  => 1756713600,
        ], $overrides);
    }

    private function useClassifier(SentimentClassifier $classifier): void
    {
        $this->app->instance(SentimentClassifier::class, $classifier);
    }
}
