<?php

namespace App\Domain\ReplyIntake\Services;

use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Models\OutboxEvent;
use App\Models\ReplyTask;

class ReplyTaskHistory
{
    public function for(ReplyTask $task): array
    {
        $task->loadMissing(['client', 'fieldServiceRequest.visits']);

        $rows = OutboxEvent::query()
            ->where('tenant_id', $task->tenant_id)
            ->where(function ($query) use ($task) {
                $query->where('dedupe_key', $task->event_id)
                    ->orWhere('payload->event_id', $task->event_id)
                    ->orWhere('payload->reply_task_id', $task->id)
                    ->orWhere('payload->client_id', $task->client_id);
            })
            ->orderBy('id')
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (OutboxEvent $event, int $index) => [
                'seq' => $event->id,
                'type' => $event->subject,
                'ts' => ($event->published_at ?? $event->created_at)?->toIso8601String(),
                'status' => $event->status,
                'payload' => $event->payload,
            ])->values()->all();
        }

        return $this->reconstruct($task);
    }

    private function reconstruct(ReplyTask $task): array
    {
        $at = $task->created_at ?? now();
        $seq = 1;
        $events = [];

        $push = function (string $type, array $payload, $time = null) use (&$events, &$seq, $at, $task) {
            $events[] = [
                'seq' => $seq++,
                'type' => $type,
                'ts' => ($time ?? $at)->toIso8601String(),
                'status' => 'published',
                'payload' => array_merge([
                    'event_id' => $task->event_id,
                    'tenant_id' => $task->tenant_id,
                    'client_id' => $task->client_id,
                    'reply_task_id' => $task->id,
                ], $payload),
            ];
        };

        $push('reply.received', [
            'sender' => $task->client?->email,
            'body_plain' => $task->body,
        ]);

        if ($task->sentiment !== null || $task->intent !== null) {
            $push('reply.classified', [
                'sentiment' => $task->sentiment,
                'intent' => $task->intent?->value,
                'confidence' => $task->confidence,
            ], $at->copy()->addSeconds(1));
        } else {
            $push('classifier.failed', [
                'error' => 'unclassified',
                'fallback' => 'sentiment=null',
            ], $at->copy()->addSeconds(1));
        }

        $push('reply_task.created', [
            'sentiment' => $task->sentiment,
            'intent' => $task->intent?->value,
            'status' => $task->status,
        ], $at->copy()->addSeconds(2));

        if ($task->sentiment !== 'auto_reply') {
            $push('campaign.stopped', [
                'campaign_id' => $task->campaign_id,
                'reason' => 'inbound_reply',
            ], $at->copy()->addSeconds(3));
        }

        if (in_array($task->sentiment, ['unsubscribe', 'wrong_person'], true)) {
            $push('client.suppressed', [
                'source' => 'reply_pipeline',
                'reason' => $task->sentiment,
            ], $at->copy()->addSeconds(4));
        }

        $push('events.reply.processed', [
            'sentiment' => $task->sentiment,
            'intent' => $task->intent?->value,
            'confidence' => $task->confidence,
        ], $at->copy()->addSeconds(5));

        $fsr = $task->fieldServiceRequest;
        if ($fsr !== null) {
            $push('field.service_request.created', [
                'field_service_request_id' => $fsr->id,
                'type' => $fsr->type->value,
                'source' => $fsr->source->value,
                'status' => $fsr->status->value,
            ], $fsr->created_at ?? $at->copy()->addSeconds(6));

            foreach ($fsr->visits as $visit) {
                if ($visit->status === FieldVisitStatus::Cancelled) {
                    continue;
                }

                $push('field.visit.scheduled', [
                    'field_visit_id' => $visit->id,
                    'field_service_request_id' => $fsr->id,
                    'team_id' => $visit->team_id,
                    'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
                    'status' => $visit->status->value,
                ], $visit->created_at ?? $at->copy()->addSeconds(7));
            }
        }

        return $events;
    }
}
