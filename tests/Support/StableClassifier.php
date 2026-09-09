<?php

namespace Tests\Support;

use App\Contracts\SentimentClassifier;
use App\Exceptions\ClassifierTimeoutException;

class StableClassifier implements SentimentClassifier
{
    public function __construct(
        private readonly string $output = '{"sentiment": "interested"}',
        private readonly bool $timeout = false,
    ) {}

    public static function label(string $label): self
    {
        return new self(json_encode(['sentiment' => $label], JSON_THROW_ON_ERROR));
    }

    public static function timeout(): self
    {
        return new self(timeout: true);
    }

    public static function raw(string $output): self
    {
        return new self($output);
    }

    public function classify(string $body): string
    {
        if ($this->timeout) {
            throw new ClassifierTimeoutException();
        }

        return $this->output;
    }
}
