<?php

namespace App\Support\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\Stream;

/**
 * Thin wrapper around the raw NATS client that owns this service's stream and
 * consumer topology. Stream names, subjects and the JetStream de-duplication
 * window live here so the rest of the code just asks for "the inbound stream".
 */
class NatsManager
{
    private ?Client $client = null;

    /** de-dup window for JetStream publishes carrying a Nats-Msg-Id header */
    private const DUPLICATE_WINDOW_SECONDS = 120.0;

    /**
     * @param array<string, mixed> $config the `config('nats')` array
     */
    public function __construct(private array $config)
    {
    }

    public function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(new Configuration(
                host: $this->config['host'],
                port: $this->config['port'],
                user: $this->config['user'] ?: null,
                pass: $this->config['pass'] ?: null,
                timeout: $this->config['timeout'],
            ));
        }

        return $this->client;
    }

    public function streamName(string $key): string
    {
        return $this->definition($key)['name'];
    }

    public function consumerName(string $key): string
    {
        return $this->config['consumers'][$key]
            ?? throw new \InvalidArgumentException("Unknown consumer [$key]");
    }

    public function stream(string $key): Stream
    {
        return $this->client()->getApi()->getStream($this->streamName($key));
    }

    /**
     * Durable pull consumer bound to a single subject of one stream.
     */
    public function durableConsumer(string $streamKey, string $consumerKey, string $subject): Consumer
    {
        $consumer = $this->stream($streamKey)->getConsumer($this->consumerName($consumerKey));

        if (!$consumer->exists()) {
            $consumer->getConfiguration()
                ->setSubjectFilter($subject)
                ->setAckWait(30_000_000_000)   // 30s, in nanoseconds
                ->setMaxDeliver(10);
        }

        return $consumer;
    }

    /**
     * Create every stream this service relies on. Idempotent: an existing
     * stream is left untouched.
     *
     * @return array<string, string> stream key => action taken
     */
    public function ensureStreams(): array
    {
        $result = [];

        foreach ($this->config['streams'] as $key => $definition) {
            $stream = $this->client()->getApi()->getStream($definition['name']);

            if ($stream->exists()) {
                $result[$key] = 'already present';
                continue;
            }

            $stream->getConfiguration()
                ->setRetentionPolicy(RetentionPolicy::LIMITS)
                ->setStorageBackend(StorageBackend::FILE)
                ->setSubjects($definition['subjects'])
                ->setDuplicateWindow(self::DUPLICATE_WINDOW_SECONDS);

            $stream->create();

            $result[$key] = 'created';
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $key): array
    {
        return $this->config['streams'][$key]
            ?? throw new \InvalidArgumentException("Unknown stream [$key]");
    }
}
