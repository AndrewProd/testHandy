<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInboundReplyJob;
use App\Support\Nats\NatsManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pull consumer for `reply.received`. Decodes each event and hands it to
 * ProcessInboundReplyJob, which owns idempotency and the classifier retries.
 *
 *  - malformed JSON        -> term() (never redelivered; it will never parse)
 *  - job throws            -> nack() (redelivered; the job is idempotent)
 *  - job returns normally  -> ack()
 */
class ConsumeRepliesCommand extends Command
{
    protected $signature = 'nats:consume-replies
        {--once : Drain what is pending, then exit}
        {--max=0 : Stop after N messages (0 = unlimited)}
        {--batch=10 : Messages to pull per request}';

    protected $description = 'Consume reply.received from JetStream and dispatch ProcessInboundReplyJob';

    private bool $shouldStop = false;

    public function handle(NatsManager $nats): int
    {
        $once  = (bool) $this->option('once');
        $max   = (int) $this->option('max');
        $batch = max(1, (int) $this->option('batch'));

        $this->trapSignals();

        $consumer = $nats->durableConsumer('inbound', 'replies', 'reply.received');
        $consumer->setBatching($batch)->create();
        $queue = $consumer->getQueue();

        $this->info(sprintf(
            'nats:consume-replies started (stream=%s, consumer=%s)%s',
            $nats->streamName('inbound'),
            $nats->consumerName('replies'),
            $once ? ' --once' : '',
        ));

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
                $payload = json_decode((string) $message->payload, true);

                if (!is_array($payload)) {
                    $message->term('malformed payload');
                    $this->warn('dropped malformed message');
                    continue;
                }

                try {
                    ProcessInboundReplyJob::dispatchSync($payload);
                    $message->ack();
                    $processed++;
                    $this->line("  processed {$payload['event_id']}");
                } catch (Throwable $e) {
                    $message->nack(1.0);
                    $this->error("  event {$payload['event_id']} failed, will retry: {$e->getMessage()}");
                }

                if ($max > 0 && $processed >= $max) {
                    $this->shouldStop = true;
                    break;
                }
            }
        }

        $nats->client()->unsubscribe($queue);
        $this->info("nats:consume-replies stopped ({$processed} processed)");

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
