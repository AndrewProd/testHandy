<?php

namespace App\Support;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Writes domain events into the transactional outbox.
 *
 * Call this INSIDE the same DB::transaction() as the state change that produced
 * the event. Nothing here talks to NATS - publishing is the relay's job.
 */
class Outbox
{
    /**
     * @param array<string, mixed> $payload
     * @param string|null $dedupeKey stable id for this event; becomes the
     *        Nats-Msg-Id header so a re-published row is dropped by JetStream.
     *        Defaults to a random ulid-ish value when omitted.
     */
    public function emit(int $tenantId, string $subject, array $payload, ?string $dedupeKey = null): OutboxEvent
    {
        if (!DB::transactionLevel()) {
            // Not fatal, but it defeats the point of an outbox. Surface it loudly
            // in local/dev; production would route this to the logger.
            trigger_error('Outbox::emit() called outside a transaction', E_USER_WARNING);
        }

        return OutboxEvent::create([
            'tenant_id'    => $tenantId,
            'subject'      => $subject,
            'dedupe_key'   => $dedupeKey ?? (string) \Illuminate\Support\Str::ulid(),
            'payload'      => $payload,
            'status'       => OutboxEvent::STATUS_PENDING,
            'attempts'     => 0,
            'available_at' => now(),
        ]);
    }
}
