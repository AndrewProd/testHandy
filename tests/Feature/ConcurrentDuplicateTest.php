<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundReplyJob;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ProcessedEvent;
use App\Models\ReplyTask;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentDuplicateTest extends TestCase
{
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh');

        parent::tearDown();
    }

    public function test_two_workers_processing_the_same_event_create_one_task(): void
    {
        $client = Client::factory()->create(['tenant_id' => 42]);

        CampaignEnrollment::factory()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'status'    => 'active',
        ]);

        $payload = [
            'event_id'   => 'evt_race',
            'tenant_id'  => $client->tenant_id,
            'sender'     => $client->email,
            'recipient'  => 'campaign+c42@mg.ourdomain.com',
            'body_plain' => 'Sounds good. Can you call me Thursday afternoon?',
            'headers'    => [
                'Message-Id'     => '<race@example.com>',
                'Auto-Submitted' => 'no',
            ],
            'timestamp'  => 1756713600,
        ];

        $gate = tempnam(sys_get_temp_dir(), 'reply_gate_');
        unlink($gate);

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $cmd  = [PHP_BINARY, base_path('tests/bin/run_job.php'), $gate];

        $first  = new Process($cmd, base_path(), null, $json, 15);
        $second = new Process($cmd, base_path(), null, $json, 15);

        $first->start();
        $second->start();

        $deadline = microtime(true) + 5;
        while (
            ($first->getStatus() !== Process::STATUS_STARTED || $second->getStatus() !== Process::STATUS_STARTED)
            && microtime(true) < $deadline
        ) {
            usleep(1000);
        }

        touch($gate);

        $first->wait();
        $second->wait();

        @unlink($gate);

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput() . $first->getOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput() . $second->getOutput());
        $this->assertSame(1, ReplyTask::query()->where('event_id', 'evt_race')->count());
        $this->assertSame(1, ProcessedEvent::query()->where('event_id', 'evt_race')->count());
    }

    public function test_sequential_dispatch_of_the_same_job_instance_is_also_idempotent(): void
    {
        $client = Client::factory()->create(['tenant_id' => 42]);

        CampaignEnrollment::factory()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
        ]);

        $job = new ProcessInboundReplyJob([
            'event_id'   => 'evt_seq',
            'tenant_id'  => 42,
            'sender'     => $client->email,
            'recipient'  => 'campaign+c42@mg.ourdomain.com',
            'body_plain' => 'Please take me off your list.',
            'headers'    => ['Auto-Submitted' => 'no'],
            'timestamp'  => 1756713600,
        ]);

        dispatch_sync($job);
        dispatch_sync($job);

        $this->assertSame(1, ReplyTask::query()->count());
        $this->assertSame(1, ProcessedEvent::query()->count());
    }
}
