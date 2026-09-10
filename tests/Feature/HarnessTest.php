<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ProcessedEvent;
use App\Models\ReplyTask;
use Tests\TestCase;

class HarnessTest extends TestCase
{
    public function test_harness_status_returns_the_manager_task(): void
    {
        $client = Client::factory()->create(['tenant_id' => 42]);
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'event_id' => 'evt_web_status',
            'status' => 'open',
        ]);
        ProcessedEvent::query()->create([
            'tenant_id' => 42,
            'event_id' => 'evt_web_status',
        ]);

        $this->getJson('/api/inbound/events/evt_web_status', ['X-Tenant-Id' => '42'])
            ->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('outcome', 'reply_task created')
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_processed_unknown_sender_does_not_invent_a_task(): void
    {
        ProcessedEvent::query()->create([
            'tenant_id' => 42,
            'event_id' => 'evt_web_nobody',
        ]);

        $this->getJson('/api/inbound/events/evt_web_nobody', ['X-Tenant-Id' => '42'])
            ->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('outcome', 'no task — client not found for tenant')
            ->assertJsonPath('data', null);
    }

    public function test_publish_requires_a_sender(): void
    {
        $this->postJson('/api/inbound/publish', [
            'tenant_id' => 42,
            'body_plain' => 'hello',
        ], ['X-Tenant-Id' => '42'])->assertUnprocessable();
    }
}
