<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    */

    'host'    => env('NATS_HOST', 'nats'),
    'port'    => (int) env('NATS_PORT', 4222),
    'user'    => env('NATS_USER', 'app'),
    'pass'    => env('NATS_PASS', 'app'),
    'timeout' => (float) env('NATS_TIMEOUT', 2),

    /*
    |--------------------------------------------------------------------------
    | JetStream streams
    |--------------------------------------------------------------------------
    |
    | inbound  - reply.received events published by the email-gateway. The
    |            consumer pulls these and dispatches ProcessInboundReplyJob.
    | outbound - domain events this service emits. They are written to the
    |            Postgres outbox in the same transaction as the state change
    |            and the relay publishes them here.
    */

    'streams' => [
        'inbound' => [
            'name'     => env('NATS_INBOUND_STREAM', 'INBOUND'),
            'subjects' => ['reply.received'],
        ],
        'outbound' => [
            'name'     => env('NATS_OUTBOUND_STREAM', 'OUTBOUND'),
            'subjects' => ['events.>'],
        ],
    ],

    'consumers' => [
        // durable pull consumer used by `php artisan nats:consume-replies`
        'replies' => env('NATS_REPLIES_CONSUMER', 'reply-center'),
        // durable pull consumer used by `php artisan nats:consume-events`
        'events'  => env('NATS_EVENTS_CONSUMER', 'audit-log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox relay
    |--------------------------------------------------------------------------
    */

    'relay' => [
        'batch'        => (int) env('OUTBOX_RELAY_BATCH', 100),
        'sleep'        => (int) env('OUTBOX_RELAY_SLEEP', 1),
        'max_attempts' => (int) env('OUTBOX_RELAY_MAX_ATTEMPTS', 10),
        // seconds; retry backoff is min(base * 2**(attempts-1), cap)
        'backoff_base' => (int) env('OUTBOX_RELAY_BACKOFF_BASE', 5),
        'backoff_cap'  => (int) env('OUTBOX_RELAY_BACKOFF_CAP', 300),
    ],

];
