<?php

namespace App\Console\Commands;

use App\Support\Nats\NatsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Demo consumer for the OUTBOUND stream (events.>). Stands in for whatever
 * downstream service would subscribe to our domain events - here it just logs
 * them so you can watch the outbox -> relay -> bus path end to end.
 */
class ConsumeEventsCommand extends Command
{
    protected $signature = 'nats:consume-events
        {--once : Drain what is pending, then exit}
        {--batch=20 : Messages to pull per request}';

    protected $description = 'Consume events.> from JetStream and log them';

    private bool $shouldStop = false;

    public function handle(NatsManager $nats): int
    {
        $once  = (bool) $this->option('once');
        $batch = max(1, (int) $this->option('batch'));

        $this->trapSignals();

        $consumer = $nats->durableConsumer('outbound', 'events', 'events.>');
        $consumer->setBatching($batch)->create();
        $queue = $consumer->getQueue();

        $this->info('nats:consume-events started' . ($once ? ' --once' : ''));

        $processed = 0;

        while (!$this->shouldStop) {
            $messages = array_filter(
                $queue->fetchAll($batch),
                fn ($message) => !$message->payload->isEmpty(),
            );

            if ($messages === []) {
                if ($once) {
                    break;
                }
                continue;
            }

            foreach ($messages as $message) {
                Log::info('bus event', [
                    'subject' => $message->subject,
                    'payload' => json_decode((string) $message->payload, true),
                ]);
                $this->line("  {$message->subject}  {$message->payload}");
                $message->ack();
                $processed++;
            }
        }

        $nats->client()->unsubscribe($queue);
        $this->info("nats:consume-events stopped ({$processed} processed)");

        return self::SUCCESS;
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
