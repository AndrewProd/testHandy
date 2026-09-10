<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReplyTaskResource;
use App\Models\Client;
use App\Models\ProcessedEvent;
use App\Models\ReplyTask;
use App\Support\Nats\NatsManager;
use Basis\Nats\Message\Payload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class HarnessController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $clients = Client::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('email')
            ->get(['id', 'tenant_id', 'name', 'email']);

        return response()->json(['data' => $clients]);
    }

    public function publish(Request $request, NatsManager $nats): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'min:1'],
            'sender' => ['required', 'email'],
            'body_plain' => ['required', 'string', 'max:5000'],
            'auto_reply' => ['sometimes', 'boolean'],
        ]);

        $tenantId = (int) $validated['tenant_id'];
        $eventId = 'evt_web_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(4));

        $event = [
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
            'sender' => $validated['sender'],
            'recipient' => "campaign+c{$tenantId}@mg.ourdomain.com",
            'body_plain' => $validated['body_plain'],
            'headers' => [
                'Message-Id' => '<' . $eventId . '@web-harness>',
                'Auto-Submitted' => $request->boolean('auto_reply') ? 'auto-generated' : 'no',
            ],
            'timestamp' => now()->timestamp,
        ];

        try {
            $ack = $nats->stream('inbound')->publish(
                'reply.received',
                new Payload(json_encode($event), ['Nats-Msg-Id' => $eventId]),
            );

            if (is_object($ack) && isset($ack->error)) {
                throw new \RuntimeException($ack->error->description ?? 'JetStream rejected the publish');
            }
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'event' => $event,
            ], 502);
        }

        return response()->json([
            'event_id' => $eventId,
            'event' => $event,
        ], 202);
    }

    public function show(Request $request, string $eventId): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $processed = ProcessedEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->exists();

        $task = ReplyTask::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->with(['client', 'messages'])
            ->first();

        $outcome = 'pending';
        if ($processed && $task) {
            $outcome = 'reply_task created';
        } elseif ($processed) {
            $outcome = 'no task — client not found for tenant';
        }

        return response()->json([
            'processed' => $processed,
            'outcome' => $outcome,
            'data' => $task ? new ReplyTaskResource($task) : null,
        ]);
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->header('X-Tenant-Id') ?: $request->integer('tenant_id', 42));
    }
}
