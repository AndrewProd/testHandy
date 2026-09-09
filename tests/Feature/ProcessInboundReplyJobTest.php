<?php

namespace Tests\Feature;

use App\Contracts\SentimentClassifier;
use App\Jobs\ProcessInboundReplyJob;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ProcessedEvent;
use App\Models\ReplyTask;
use Tests\Support\StableClassifier;
use Tests\TestCase;

class ProcessInboundReplyJobTest extends TestCase
{
    public function test_creates_a_task_and_stops_the_campaign_for_an_interested_reply(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));

        [$client, $enrollment] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'event_id'   => 'evt_interested',
            'body_plain' => 'We would like to go ahead with the quote.',
        ]));

        $task = ReplyTask::query()->sole();

        $this->assertSame($client->id, $task->client_id);
        $this->assertSame($client->tenant_id, $task->tenant_id);
        $this->assertSame('evt_interested', $task->event_id);
        $this->assertSame('interested', $task->sentiment);
        $this->assertSame('open', $task->status);
        $this->assertNull($client->fresh()->suppressed_at);
        $this->assertSame('stopped', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->next_send_at);
        $this->assertTrue(ProcessedEvent::query()->where('event_id', 'evt_interested')->exists());
    }

    public function test_unsubscribe_suppresses_the_client_even_when_the_classifier_fails(): void
    {
        $this->useClassifier(StableClassifier::timeout());

        [$client, $enrollment] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'event_id'   => 'evt_unsub',
            'body_plain' => 'Please take me off your list. I do not want any more emails.',
        ]));

        $task = ReplyTask::query()->sole();

        $this->assertSame('unsubscribe', $task->sentiment);
        $this->assertNotNull($client->fresh()->suppressed_at);
        $this->assertSame('stopped', $enrollment->fresh()->status);
    }

    public function test_wrong_person_suppresses_and_stops_the_campaign(): void
    {
        $this->useClassifier(StableClassifier::label('wrong_person'));

        [$client, $enrollment] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'body_plain' => 'I am not the right contact for this.',
        ]));

        $this->assertSame('wrong_person', ReplyTask::query()->sole()->sentiment);
        $this->assertNotNull($client->fresh()->suppressed_at);
        $this->assertSame('stopped', $enrollment->fresh()->status);
    }

    public function test_auto_reply_headers_skip_the_classifier_and_leave_the_campaign_running(): void
    {
        $this->useClassifier(StableClassifier::timeout());

        [$client, $enrollment] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'body_plain' => 'I am out of the office until next week.',
            'headers'    => [
                'Auto-Submitted'           => 'auto-replied',
                'X-Auto-Response-Suppress' => 'All',
            ],
        ]));

        $task = ReplyTask::query()->sole();

        $this->assertSame('auto_reply', $task->sentiment);
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertNull($client->fresh()->suppressed_at);
    }

    public function test_classifier_timeout_creates_an_unclassified_task_and_stops_the_campaign(): void
    {
        $this->useClassifier(StableClassifier::timeout());

        [$client, $enrollment] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'body_plain' => 'Sounds good, we should talk about the quote.',
        ]));

        $task = ReplyTask::query()->sole();

        $this->assertNull($task->sentiment);
        $this->assertSame('open', $task->status);
        $this->assertSame('stopped', $enrollment->fresh()->status);
        $this->assertNull($client->fresh()->suppressed_at);
    }

    public function test_malformed_classifier_json_is_treated_as_unclassified(): void
    {
        $this->useClassifier(StableClassifier::raw("```json\n{\"sentiment\": \"inter"));

        [$client] = $this->enroll();

        $this->dispatchPayload($this->payload($client));

        $this->assertNull(ReplyTask::query()->sole()->sentiment);
    }

    public function test_label_outside_the_rubric_is_treated_as_unclassified(): void
    {
        $this->useClassifier(StableClassifier::raw('{"sentiment": "negative"}'));

        [$client] = $this->enroll();

        $this->dispatchPayload($this->payload($client));

        $this->assertNull(ReplyTask::query()->sole()->sentiment);
    }

    public function test_prose_classifier_output_is_treated_as_unclassified(): void
    {
        $this->useClassifier(StableClassifier::raw('The customer seems interested in scheduling a call.'));

        [$client] = $this->enroll();

        $this->dispatchPayload($this->payload($client));

        $this->assertNull(ReplyTask::query()->sole()->sentiment);
    }

    public function test_duplicate_event_creates_exactly_one_task(): void
    {
        $this->useClassifier(StableClassifier::label('question'));

        [$client] = $this->enroll();
        $payload = $this->payload($client, ['event_id' => 'evt_dup']);

        $this->dispatchPayload($payload);
        $this->dispatchPayload($payload);

        $this->assertSame(1, ReplyTask::query()->count());
        $this->assertSame(1, ProcessedEvent::query()->count());
    }

    public function test_html_only_body_is_classified(): void
    {
        $this->useClassifier(StableClassifier::label('not_now'));

        [$client] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'body_plain' => '',
            'body_html'  => '<html><body><div>Not this year, our budget is spent.</div></body></html>',
        ]));

        $task = ReplyTask::query()->sole();

        $this->assertSame('not_now', $task->sentiment);
        $this->assertSame('Not this year, our budget is spent.', $task->body);
    }

    public function test_unknown_sender_is_claimed_but_creates_no_task(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));

        $this->dispatchPayload([
            'event_id'   => 'evt_unknown',
            'tenant_id'  => 42,
            'sender'     => 'nobody@example.com',
            'recipient'  => 'campaign+c42@mg.ourdomain.com',
            'body_plain' => 'Hello',
            'headers'    => ['Auto-Submitted' => 'no'],
            'timestamp'  => 1756713600,
        ]);

        $this->assertSame(0, ReplyTask::query()->count());
        $this->assertSame(1, ProcessedEvent::query()->where('event_id', 'evt_unknown')->count());
    }

    public function test_our_own_campaign_address_is_ignored(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));

        [$client] = $this->enroll();

        $this->dispatchPayload($this->payload($client, [
            'sender'     => 'campaign+c42@mg.ourdomain.com',
            'recipient'  => 'campaign+c42@mg.ourdomain.com',
            'body_plain' => 'Hi there, just checking in on the quote we sent over.',
        ]));

        $this->assertSame(0, ReplyTask::query()->count());
        $this->assertSame(1, ProcessedEvent::query()->count());
        $this->assertSame('active', CampaignEnrollment::query()->where('client_id', $client->id)->value('status'));
    }

    public function test_already_suppressed_client_gets_a_task_but_is_not_reactivated(): void
    {
        $this->useClassifier(StableClassifier::label('question'));

        [$client, $enrollment] = $this->enroll(
            ['suppressed_at' => now()->subDays(3)],
            ['status' => 'stopped', 'next_send_at' => null],
        );

        $this->dispatchPayload($this->payload($client, [
            'body_plain' => 'Could you send the brochure again?',
        ]));

        $this->assertSame(1, ReplyTask::query()->count());
        $this->assertNotNull($client->fresh()->suppressed_at);
        $this->assertSame('stopped', $enrollment->fresh()->status);
    }

    public function test_same_email_in_another_tenant_is_not_touched(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));

        [$ours, $ourEnrollment] = $this->enroll([
            'tenant_id' => 42,
            'email'     => 'shared@example.com',
        ]);

        [$theirs, $theirEnrollment] = $this->enroll([
            'tenant_id' => 43,
            'email'     => 'shared@example.com',
        ]);

        $this->dispatchPayload($this->payload($ours, [
            'event_id'   => 'evt_t42',
            'tenant_id'  => 42,
            'body_plain' => 'Yes, we are interested.',
        ]));

        $this->assertSame(1, ReplyTask::query()->count());
        $this->assertSame($ours->id, ReplyTask::query()->sole()->client_id);
        $this->assertSame(42, ReplyTask::query()->sole()->tenant_id);
        $this->assertSame('stopped', $ourEnrollment->fresh()->status);
        $this->assertSame('active', $theirEnrollment->fresh()->status);
        $this->assertNull($theirs->fresh()->suppressed_at);
        $this->assertSame(0, ReplyTask::query()->where('tenant_id', 43)->count());
        $this->assertTrue(Client::query()->whereKey($theirs->id)->where('tenant_id', 43)->exists());
        $this->assertFalse(Client::query()->whereKey($theirs->id)->where('tenant_id', 42)->exists());
    }

    public function test_event_for_unknown_tenant_does_not_attach_to_another_tenants_client(): void
    {
        $this->useClassifier(StableClassifier::label('interested'));

        [$client] = $this->enroll([
            'tenant_id' => 42,
            'email'     => 'd.walker@northshore-homes.ca',
        ]);

        $this->dispatchPayload($this->payload($client, [
            'event_id'   => 'evt_t43',
            'tenant_id'  => 43,
            'sender'     => 'd.walker@northshore-homes.ca',
        ]));

        $this->assertSame(0, ReplyTask::query()->count());
        $this->assertSame('active', CampaignEnrollment::query()->where('client_id', $client->id)->value('status'));
        $this->assertTrue(
            ProcessedEvent::query()->where('tenant_id', 43)->where('event_id', 'evt_t43')->exists()
        );
        $this->assertFalse(
            ProcessedEvent::query()->where('tenant_id', 42)->where('event_id', 'evt_t43')->exists()
        );
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
            'event_id'   => 'evt_' . substr(md5(spl_object_hash($client) . microtime(true)), 0, 12),
            'tenant_id'  => $client->tenant_id,
            'sender'     => $client->email,
            'recipient'  => 'campaign+c' . $client->tenant_id . '@mg.ourdomain.com',
            'body_plain' => 'Sounds good. Call me Thursday.',
            'headers'    => [
                'Message-Id'     => '<test@example.com>',
                'Auto-Submitted' => 'no',
            ],
            'timestamp'  => 1756713600,
        ], $overrides);
    }

    private function useClassifier(SentimentClassifier $classifier): void
    {
        $this->app->instance(SentimentClassifier::class, $classifier);
    }

    private function dispatchPayload(array $payload): void
    {
        dispatch_sync(new ProcessInboundReplyJob($payload));
    }
}
