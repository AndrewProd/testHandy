<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Support\Nats\NatsManager;
use Basis\Nats\Message\Payload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drains the Postgres outbox into NATS JetStream.
 *
 * Claims a batch of pending rows with `FOR UPDATE SKIP LOCKED` so several
 * relays can run side by side without publishing the same row twice. A publish
 * that succeeds but whose status update is then lost is republished later and
 * dropped by JetStream de-duplication (Nats-Msg-Id = dedupe_key).
 */
class OutboxRelayCommand extends Command
{
    protected $signature = 'outbox:relay
        {--once : Process a single batch and exit}
        {--batch= : Rows per batch (defaults to config)}
        {--sleep= : Seconds to wait when the outbox is empty (defaults to config)}';

    protected $description = 'Publish pending outbox_events rows to NATS JetStream';

    private bool $shouldStop = false;

    public function handle(NatsManager $nats): int
    {
        $cfg   = config('nats.relay');
        $batch = (int) ($this->option('batch') ?: $cfg['batch']);
        $sleep = (int) ($this->option('sleep') ?? $cfg['sleep']);
        $once  = (bool) $this->option('once');

        $this->trapSignals();
        $this->info(sprintf('outbox:relay started (batch=%d, sleep=%ds)%s', $batch, $sleep, $once ? ' --once' : ''));

        do {
            try {
                $handled = $this->relayBatch($nats, $batch, $cfg);
            } catch (Throwable $e) {
                // Connection blips etc. Back off and retry; rows stay pending.
                $this->error('relay batch failed: ' . $e->getMessage());
                $handled = 0;
                sleep(max(1, $sleep));
            }

            if ($once) {
                $this->info("relayed {$handled} row(s)");
                return self::SUCCESS;
            }

            if ($handled === 0 && !$this->shouldStop) {
                sleep(max(1, $sleep));
            }
        } while (!$this->shouldStop);

        $this->info('outbox:relay stopped');

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function relayBatch(NatsManager $nats, int $batch, array $cfg): int
    {
        return DB::transaction(function () use ($nats, $batch, $cfg) {
            $rows = DB::table('outbox_events')
                ->where('status', OutboxEvent::STATUS_PENDING)
                ->where('available_at', '<=', now())
                ->orderBy('id')
                ->limit($batch)
                ->lock('for update skip locked')
                ->get();

            $stream = $nats->stream('outbound');

            foreach ($rows as $row) {
                try {
                    $ack = $stream->publish(
                        $row->subject,
                        new Payload($row->payload, ['Nats-Msg-Id' => $row->dedupe_key]),
                    );

                    if (isset($ack->error)) {
                        throw new \RuntimeException($ack->error->description ?? 'JetStream publish rejected');
                    }

                    DB::table('outbox_events')->where('id', $row->id)->update([
                        'status'       => OutboxEvent::STATUS_PUBLISHED,
                        'attempts'     => $row->attempts + 1,
                        'published_at' => now(),
                        'last_error'   => null,
                        'updated_at'   => now(),
                    ]);
                } catch (Throwable $e) {
                    $attempts = $row->attempts + 1;
                    $failed   = $attempts >= (int) $cfg['max_attempts'];
                    $backoff  = min(
                        (int) $cfg['backoff_base'] * (2 ** max(0, $attempts - 1)),
                        (int) $cfg['backoff_cap'],
                    );

                    DB::table('outbox_events')->where('id', $row->id)->update([
                        'status'       => $failed ? OutboxEvent::STATUS_FAILED : OutboxEvent::STATUS_PENDING,
                        'attempts'     => $attempts,
                        'available_at' => now()->addSeconds($backoff),
                        'last_error'   => mb_substr($e->getMessage(), 0, 1000),
                        'updated_at'   => now(),
                    ]);

                    $this->warn("event {$row->id} publish failed (attempt {$attempts}): {$e->getMessage()}");
                }
            }

            return $rows->count();
        });
    }

    private function trapSignals(): void
    {
        if (app()->runningUnitTests() || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldStop = true;
            });
        }
    }
}
