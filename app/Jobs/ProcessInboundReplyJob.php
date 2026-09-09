<?php

namespace App\Jobs;

use App\Contracts\SentimentClassifier;
use App\Exceptions\ClassifierTimeoutException;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ReplyTask;
use App\Support\IdempotencyGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessInboundReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private const LABELS = [
        'interested',
        'question',
        'not_now',
        'unsubscribe',
        'wrong_person',
        'auto_reply',
    ];

    /**
     * @param array $payload decoded reply.received event
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(SentimentClassifier $classifier, IdempotencyGuard $guard): void
    {
        $tenantId = (int) ($this->payload['tenant_id'] ?? 0);
        $eventId  = (string) ($this->payload['event_id'] ?? '');

        if ($tenantId <= 0 || $eventId === '') {
            return;
        }

        DB::transaction(function () use ($classifier, $guard, $tenantId, $eventId) {
            if (!$guard->claim($tenantId, $eventId)) {
                return;
            }

            $client = $this->findClient($tenantId);

            if ($client === null) {
                return;
            }

            $body      = $this->resolveBody($this->payload);
            $sentiment = $this->resolveSentiment($classifier, $body);

            $created = ReplyTask::query()->insertOrIgnore([
                'tenant_id'  => $tenantId,
                'client_id'  => $client->id,
                'event_id'   => $eventId,
                'sentiment'  => $sentiment,
                'body'       => $body,
                'status'     => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($created === 0) {
                return;
            }

            $this->applyCampaignEffects($tenantId, $client, $sentiment);
        });
    }

    private function findClient(int $tenantId): ?Client
    {
        $sender = strtolower(trim((string) ($this->payload['sender'] ?? '')));

        if ($sender === '' || !str_contains($sender, '@')) {
            return null;
        }

        if ($this->isOurAddress($sender)) {
            return null;
        }

        return Client::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$sender])
            ->first();
    }

    private function isOurAddress(string $email): bool
    {
        if (str_starts_with($email, 'campaign+')) {
            return true;
        }

        $recipient = strtolower(trim((string) ($this->payload['recipient'] ?? '')));

        return $recipient !== '' && $email === $recipient;
    }

    private function resolveBody(array $payload): string
    {
        $plain = trim((string) ($payload['body_plain'] ?? ''));

        if ($plain !== '') {
            return $plain;
        }

        $html = (string) ($payload['body_html'] ?? '');

        if ($html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function resolveSentiment(SentimentClassifier $classifier, string $body): ?string
    {
        if ($this->isAutoReply()) {
            return 'auto_reply';
        }

        if ($this->looksLikeUnsubscribe($body)) {
            return 'unsubscribe';
        }

        return $this->classify($classifier, $body);
    }

    private function isAutoReply(): bool
    {
        $headers = [];

        foreach (($this->payload['headers'] ?? []) as $key => $value) {
            $headers[strtolower((string) $key)] = strtolower(trim((string) $value));
        }

        $autoSubmitted = $headers['auto-submitted'] ?? null;

        if ($autoSubmitted !== null && $autoSubmitted !== 'no') {
            return true;
        }

        if (array_key_exists('x-auto-response-suppress', $headers)) {
            return true;
        }

        if (in_array($headers['x-autoreply'] ?? null, ['yes', 'true', '1'], true)) {
            return true;
        }

        return in_array($headers['precedence'] ?? null, ['bulk', 'auto', 'junk'], true);
    }

    private function looksLikeUnsubscribe(string $body): bool
    {
        $text = mb_strtolower($body);

        foreach (['unsubscribe', 'take me off', 'stop emailing', 'remove me', 'opt out'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function classify(SentimentClassifier $classifier, string $body): ?string
    {
        try {
            $raw = $classifier->classify($body);
        } catch (ClassifierTimeoutException) {
            return null;
        }

        return $this->parseLabel($raw);
    }

    private function parseLabel(string $raw): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^```(?:json)?\s*(.*)$/is', $raw, $matches) === 1) {
            $raw = trim($matches[1], " \n\r\t`");
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['sentiment']) || !is_string($decoded['sentiment'])) {
            return null;
        }

        $label = strtolower(trim($decoded['sentiment']));

        return in_array($label, self::LABELS, true) ? $label : null;
    }

    private function applyCampaignEffects(int $tenantId, Client $client, ?string $sentiment): void
    {
        if ($sentiment === 'auto_reply') {
            return;
        }

        CampaignEnrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->update([
                'status'       => 'stopped',
                'next_send_at' => null,
            ]);

        if (in_array($sentiment, ['unsubscribe', 'wrong_person'], true) && $client->suppressed_at === null) {
            $client->forceFill(['suppressed_at' => now()])->save();
        }
    }
}
