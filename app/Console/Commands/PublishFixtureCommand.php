<?php

namespace App\Console\Commands;

use App\Support\Nats\NatsManager;
use Basis\Nats\Message\Payload;
use Illuminate\Console\Command;

/**
 * Replays a JSON array of reply.received events onto the bus, standing in for
 * the email-gateway. Handy for exercising the whole
 * publish -> JetStream -> consumer -> job path locally.
 *
 *   php artisan nats:publish-fixture
 *   php artisan nats:publish-fixture storage/my-events.json
 */
class PublishFixtureCommand extends Command
{
    protected $signature = 'nats:publish-fixture
        {file=tests/Fixtures/inbound_events.json : Path to a JSON array of events}';

    protected $description = 'Publish reply.received events from a fixture file to NATS';

    public function handle(NatsManager $nats): int
    {
        $path = $this->argument('file');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $events = json_decode((string) file_get_contents($path), true);

        if (!is_array($events)) {
            $this->error('Fixture is not a JSON array');

            return self::FAILURE;
        }

        $stream = $nats->stream('inbound');
        $count  = 0;

        foreach ($events as $event) {
            $eventId = $event['event_id'] ?? ('gen_' . $count);

            $ack = $stream->publish('reply.received', new Payload(
                json_encode($event),
                ['Nats-Msg-Id' => (string) $eventId],
            ));

            if (isset($ack->error)) {
                $this->error("  {$eventId}: " . ($ack->error->description ?? 'rejected'));
                continue;
            }

            $this->line("  published {$eventId} (seq {$ack->seq})");
            $count++;
        }

        $this->info("Published {$count} event(s) to reply.received");

        return self::SUCCESS;
    }
}
