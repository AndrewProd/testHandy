<?php

namespace App\Console\Commands;

use App\Support\Nats\NatsManager;
use Illuminate\Console\Command;

/**
 * Creates the JetStream streams this service relies on. Safe to run repeatedly;
 * it is wired into `make up` so a fresh checkout comes up ready to publish.
 */
class NatsSetupCommand extends Command
{
    protected $signature = 'nats:setup {--fresh : Purge every message from the streams first}';

    protected $description = 'Create the INBOUND / OUTBOUND JetStream streams if they do not exist';

    public function handle(NatsManager $nats): int
    {
        $this->info("Connecting to NATS at {$nats->client()->configuration->host}:{$nats->client()->configuration->port} ...");

        if (!$nats->client()->ping()) {
            $this->error('NATS did not answer ping');

            return self::FAILURE;
        }

        foreach ($nats->ensureStreams() as $key => $action) {
            $this->line(sprintf('  %-10s %s (%s)', $key, $nats->streamName($key), $action));
        }

        if ($this->option('fresh')) {
            foreach (['inbound', 'outbound'] as $key) {
                $nats->stream($key)->purge();
                $this->line("  purged {$nats->streamName($key)}");
            }
        }

        $this->info('Streams ready.');

        return self::SUCCESS;
    }
}
