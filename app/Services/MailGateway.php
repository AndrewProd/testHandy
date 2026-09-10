<?php

namespace App\Services;

use App\Contracts\OutboundMailer;

class MailGateway
{
    public function __construct(
        private readonly OutboundMailer $mailer,
    ) {}

    public function send(string $to, string $subject, string $body): string
    {
        return $this->mailer->send($to, $subject, $body)->messageId;
    }
}
