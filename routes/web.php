<?php

use App\Models\Client;
use App\Models\OutboxEvent;
use App\Support\HarnessPage;
use App\Support\Nats\NatsManager;
use Basis\Nats\Message\Payload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Local test harness
|--------------------------------------------------------------------------
|
| A tiny page to hand-craft a `reply.received` event, drop it on NATS, and
| watch the running consumer + relay turn it into a reply task and an
| outbound event. Not part of the product - it is a dev tool.
|
*/

Route::get('/', function (Request $request) {
    $clients = Client::query()
        ->orderBy('tenant_id')->orderBy('email')
        ->get(['tenant_id', 'email', 'name']);

    $tasks = DB::table('reply_tasks')
        ->leftJoin('clients', 'clients.id', '=', 'reply_tasks.client_id')
        ->orderByDesc('reply_tasks.id')
        ->limit(10)
        ->get([
            'reply_tasks.id', 'reply_tasks.event_id', 'reply_tasks.tenant_id',
            'clients.email', 'reply_tasks.sentiment', 'reply_tasks.status', 'reply_tasks.created_at',
        ]);

    $events = OutboxEvent::query()->orderByDesc('id')->limit(10)->get();

    return response(HarnessPage::render(
        $clients,
        $tasks,
        $events,
        $request->query('published'),
        $request->query('error'),
    ));
});

Route::post('/publish', function (Request $request, NatsManager $nats) {
    $validated = $request->validate([
        'tenant_id'  => ['required', 'integer', 'min:1'],
        'sender'     => ['required', 'email'],
        'body_plain' => ['required', 'string', 'max:5000'],
    ]);

    $tenantId = (int) $validated['tenant_id'];
    $eventId  = 'evt_web_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(4));

    $event = [
        'event_id'   => $eventId,
        'tenant_id'  => $tenantId,
        'sender'     => $validated['sender'],
        'recipient'  => "campaign+c{$tenantId}@mg.ourdomain.com",
        'body_plain' => $validated['body_plain'],
        'headers'    => [
            'Message-Id'     => '<' . $eventId . '@web-harness>',
            'Auto-Submitted' => $request->boolean('auto_reply') ? 'auto-generated' : 'no',
        ],
        'timestamp'  => now()->timestamp,
    ];

    try {
        $ack = $nats->stream('inbound')->publish(
            'reply.received',
            new Payload(json_encode($event), ['Nats-Msg-Id' => $eventId]),
        );

        if (is_object($ack) && isset($ack->error)) {
            throw new RuntimeException($ack->error->description ?? 'JetStream rejected the publish');
        }
    } catch (Throwable $e) {
        return redirect('/?' . http_build_query(['error' => $e->getMessage()]));
    }

    return redirect('/?' . http_build_query(['published' => $eventId]));
});
