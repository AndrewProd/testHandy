<?php

namespace App\Services\Mail;

use App\Contracts\OutboundMailer;
use App\Domain\Mail\OutboundMailResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MailgunMailer implements OutboundMailer
{
    public function send(string $to, string $subject, string $body, array $headers = []): OutboundMailResult
    {
        $messageId = '<' . Str::uuid() . '@mg.ourdomain.com>';

        Log::info('mailgun.mock.send', [
            'to' => $to,
            'subject' => $subject,
            'message_id' => $messageId,
            'bytes' => strlen($body),
        ]);

        return new OutboundMailResult('mailgun', $messageId);
    }
}
