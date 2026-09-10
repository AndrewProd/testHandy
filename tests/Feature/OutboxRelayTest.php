<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use App\Support\Nats\NatsManager;
use Basis\Nats\Stream\Stream;
use Mockery;
use Tests\TestCase;

/**
 * Exercises the relay's claim / publish / retry bookkeeping with NATS mocked
 * out. The end-to-end path against a real broker is driven from `make bus-demo`.
 */
class OutboxRelayTest extends TestCase
{
    public function test_pending_rows_are_published_and_marked(): void
    {
        $this->fakeNats(fn ($stream) => $stream
            ->shouldReceive('publish')
            ->times(2)
            ->andReturn((object) ['stream' => 'OUTBOUND', 'seq' => 1]));

        $this->makeEvent('evt_r1');
        $this->makeEvent('evt_r2');

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(2, OutboxEvent::query()->where('status', OutboxEvent::STATUS_PUBLISHED)->count());
        $this->assertSame(0, OutboxEvent::query()->where('status', OutboxEvent::STATUS_PENDING)->count());
        $this->assertNotNull(OutboxEvent::query()->first()->published_at);
    }

    public function test_a_publish_failure_keeps_the_row_pending_and_backs_off(): void
    {
        $this->fakeNats(fn ($stream) => $stream
            ->shouldReceive('publish')
            ->andThrow(new \RuntimeException('broker down')));

        $row = $this->makeEvent('evt_fail');

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $row->refresh();
        $this->assertSame(OutboxEvent::STATUS_PENDING, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertTrue($row->available_at->isAfter(now()));
        $this->assertStringContainsString('broker down', $row->last_error);
    }

    public function test_row_is_marked_failed_after_max_attempts(): void
    {
        config()->set('nats.relay.max_attempts', 3);

        $this->fakeNats(fn ($stream) => $stream
            ->shouldReceive('publish')
            ->andThrow(new \RuntimeException('still down')));

        $row = $this->makeEvent('evt_dead', ['attempts' => 2]);

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $row->refresh();
        $this->assertSame(OutboxEvent::STATUS_FAILED, $row->status);
        $this->assertSame(3, $row->attempts);
    }

    public function test_rows_scheduled_for_the_future_are_left_alone(): void
    {
        $this->fakeNats(fn ($stream) => $stream->shouldReceive('publish')->never());

        $this->makeEvent('evt_future', ['available_at' => now()->addMinutes(5)]);

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(1, OutboxEvent::query()->where('status', OutboxEvent::STATUS_PENDING)->count());
    }

    private function fakeNats(\Closure $configureStream): void
    {
        $stream = Mockery::mock(Stream::class);
        $configureStream($stream);

        $manager = Mockery::mock(NatsManager::class);
        $manager->shouldReceive('stream')->with('outbound')->andReturn($stream);

        $this->app->instance(NatsManager::class, $manager);
    }

    private function makeEvent(string $dedupeKey, array $overrides = []): OutboxEvent
    {
        return OutboxEvent::create(array_merge([
            'tenant_id'    => 42,
            'subject'      => 'events.reply.processed',
            'dedupe_key'   => $dedupeKey,
            'payload'      => ['event_id' => $dedupeKey, 'sentiment' => 'question'],
            'status'       => OutboxEvent::STATUS_PENDING,
            'attempts'     => 0,
            'available_at' => now()->subSecond(),
        ], $overrides));
    }
}
