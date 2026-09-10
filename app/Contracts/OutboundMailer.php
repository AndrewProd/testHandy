<?php

namespace App\Contracts;

use App\Domain\Mail\OutboundMailResult;

interface OutboundMailer
{
    public function send(string $to, string $subject, string $body, array $headers = []): OutboundMailResult;
}
