<?php

namespace App\Support;

use App\Models\ProcessedEvent;

class IdempotencyGuard
{
    public function claim(int $tenantId, string $eventId): bool
    {
        $now = now();

        $inserted = ProcessedEvent::query()->insertOrIgnore([
            'tenant_id'  => $tenantId,
            'event_id'   => $eventId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted > 0;
    }
}
