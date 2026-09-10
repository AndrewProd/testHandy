<?php

namespace App\Domain\Mail;

final class OutboundMailResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $messageId,
    ) {}
}
