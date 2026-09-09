<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundReplyJob;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ProcessedEvent;
use App\Models\ReplyTask;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

class InboundFixtureReplayTest extends TestCase
{
    public function test_replay_of_captured_production_events(): void
    {
        $this->seed(DemoSeeder::class);

        $events = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/inbound_events.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($events as $event) {
            dispatch_sync(new ProcessInboundReplyJob($event));
        }

        $tasks     = ReplyTask::query()->orderBy('id')->get()->keyBy('event_id');
        $processed = ProcessedEvent::query()->get();

        $this->assertCount(12, $events);
        $this->assertCount(11, $processed, 'the duplicated event id must be claimed once');
        $this->assertCount(8, $tasks);

        $this->assertTrue($tasks->has('evt_01HZ8A0001'));
        $this->assertTrue($tasks->has('evt_01HZ8A0002'));
        $this->assertTrue($tasks->has('evt_01HZ8A0003'));
        $this->assertTrue($tasks->has('evt_01HZ8A0004'));
        $this->assertTrue($tasks->has('evt_01HZ8A0005'));
        $this->assertTrue($tasks->has('evt_01HZ8A0006'));
        $this->assertTrue($tasks->has('evt_01HZ8A0008'));
        $this->assertTrue($tasks->has('evt_01HZ8A0012'));

        $this->assertFalse($tasks->has('evt_01HZ8A0007'), 'bounce for an unknown mailbox must not create a task');
        $this->assertFalse($tasks->has('evt_01HZ8A0009'), 'echo of our own campaign mail must not create a task');
        $this->assertFalse($tasks->has('evt_01HZ8A0011'), 'tenant 43 must not attach to tenant 42\'s client');

        $this->assertSame(1, ReplyTask::query()->where('event_id', 'evt_01HZ8A0001')->count());
        $this->assertSame(42, $tasks['evt_01HZ8A0001']->tenant_id);

        $this->assertSame('unsubscribe', $tasks['evt_01HZ8A0003']->sentiment);
        $this->assertSame('auto_reply', $tasks['evt_01HZ8A0004']->sentiment);
        $this->assertSame('auto_reply', $tasks['evt_01HZ8A0005']->sentiment);
        $this->assertSame('auto_reply', $tasks['evt_01HZ8A0006']->sentiment);

        foreach ($tasks as $task) {
            $this->assertContains(
                $task->sentiment,
                ['interested', 'question', 'not_now', 'unsubscribe', 'wrong_person', 'auto_reply', null],
                'task ' . $task->event_id . ' has an unexpected sentiment',
            );
            $this->assertSame(42, $task->tenant_id);
            $this->assertSame('open', $task->status);
        }

        $this->assertSame(
            'Not this year, our budget is spent. Try us again in the spring.',
            $tasks['evt_01HZ8A0008']->body,
        );

        $rita  = Client::query()->where('email', 'r.osei@maplecourt.ca')->firstOrFail();
        $kwame = Client::query()->where('email', 'k.mensah@fairlawn.ca')->firstOrFail();
        $dana  = Client::query()->where('email', 'd.walker@northshore-homes.ca')->firstOrFail();
        $jan   = Client::query()->where('email', 'j.kowalczyk@bridgeportbuild.ca')->firstOrFail();
        $sofia = Client::query()->where('email', 's.lindqvist@harbourview.ca')->firstOrFail();
        $ana   = Client::query()->where('email', 'a.ferreira@stonegate.ca')->firstOrFail();
        $marc  = Client::query()->where('email', 'm.tremblay@lakesideprop.ca')->firstOrFail();
        $luc   = Client::query()->where('email', 'l.beaulieu@ridgetop.ca')->firstOrFail();

        $this->assertNotNull($rita->suppressed_at);
        $this->assertNotNull($kwame->suppressed_at);
        $this->assertNull($dana->suppressed_at);
        $this->assertNull($jan->suppressed_at);

        $this->assertSame('stopped', $this->enrollment($rita)->status);
        $this->assertSame('stopped', $this->enrollment($kwame)->status);
        $this->assertSame('stopped', $this->enrollment($dana)->status);
        $this->assertSame('stopped', $this->enrollment($marc)->status);
        $this->assertSame('stopped', $this->enrollment($luc)->status);

        $this->assertSame('active', $this->enrollment($jan)->status);
        $this->assertSame('active', $this->enrollment($sofia)->status);
        $this->assertSame('active', $this->enrollment($ana)->status);

        $this->assertSame($kwame->id, $tasks['evt_01HZ8A0012']->client_id);
        $this->assertSame($dana->id, $tasks['evt_01HZ8A0001']->client_id);

        $this->assertTrue(
            ProcessedEvent::query()->where('tenant_id', 43)->where('event_id', 'evt_01HZ8A0011')->exists()
        );
        $this->assertSame(0, Client::query()->where('tenant_id', 43)->count());
        $this->assertSame(1, Client::query()->where('email', 'd.walker@northshore-homes.ca')->count());
    }

    private function enrollment(Client $client): CampaignEnrollment
    {
        return CampaignEnrollment::query()
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->sole();
    }
}
