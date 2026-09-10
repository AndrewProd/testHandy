<?php

namespace App\Domain\ReplyIntake\Services;

use App\Contracts\SentimentClassifier;
use App\Domain\ReplyIntake\Data\ReplyAnalysis;
use App\Domain\ReplyIntake\Enums\Intent;
use App\Exceptions\ClassifierTimeoutException;

class ReplyAnalyzer
{
    private const SENTIMENTS = [
        'interested',
        'question',
        'not_now',
        'unsubscribe',
        'wrong_person',
        'auto_reply',
    ];

    private const MEASUREMENT_NEEDLES = [
        'come and measure',
        'come measure',
        'someone to come and measure',
        'someone to measure',
        'send someone to measure',
        'book a measure',
        'site measure',
        'on-site measure',
        'onsite measure',
        'measure the windows',
        'measure my',
        'come out and measure',
        'measurement',
        'come and measure.',
    ];

    public function analyze(array $payload, SentimentClassifier $classifier): ReplyAnalysis
    {
        $body = $this->resolveBody($payload);

        if ($this->isAutoReply($payload)) {
            return new ReplyAnalysis('auto_reply', Intent::AutoReply, 1.0, $body);
        }

        if ($this->looksLikeUnsubscribe($body)) {
            return new ReplyAnalysis('unsubscribe', Intent::Unsubscribe, 1.0, $body);
        }

        $parsed = $this->classify($classifier, $body);
        $analysis = new ReplyAnalysis(
            $parsed['sentiment'],
            $parsed['intent'] ?? Intent::fromSentiment($parsed['sentiment']),
            $parsed['confidence'],
            $body,
        );

        return $this->maybeMeasurement($analysis);
    }

    private function classify(SentimentClassifier $classifier, string $body): array
    {
        try {
            $raw = $classifier->classify($body);
        } catch (ClassifierTimeoutException) {
            return ['sentiment' => null, 'intent' => null, 'confidence' => null];
        }

        return $this->parse($raw);
    }

    private function parse(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('/^```(?:json)?\s*(.*)$/is', $raw, $matches) === 1) {
            $raw = trim($matches[1], " \n\r\t`");
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['sentiment']) || !is_string($decoded['sentiment'])) {
            return ['sentiment' => null, 'intent' => null, 'confidence' => null];
        }

        $label = strtolower(trim($decoded['sentiment']));
        $sentiment = in_array($label, self::SENTIMENTS, true) ? $label : null;

        $intent = null;
        if (isset($decoded['intent']) && is_string($decoded['intent'])) {
            $intent = Intent::tryFrom(strtolower(trim($decoded['intent'])));
        }

        $confidence = $this->normalizeConfidence($decoded['confidence'] ?? null);

        return [
            'sentiment' => $sentiment,
            'intent' => $intent,
            'confidence' => $confidence ?? ($sentiment !== null ? 0.5 : null),
        ];
    }

    private function maybeMeasurement(ReplyAnalysis $analysis): ReplyAnalysis
    {
        if ($analysis->intent === Intent::RequestMeasurement) {
            return $analysis;
        }

        if (!in_array($analysis->sentiment, ['interested', 'question'], true)) {
            return $analysis;
        }

        if (!$this->looksLikeMeasurement($analysis->body)) {
            return $analysis;
        }

        return new ReplyAnalysis(
            $analysis->sentiment,
            Intent::RequestMeasurement,
            max($analysis->confidence ?? 0, 0.9),
            $analysis->body,
        );
    }

    private function looksLikeMeasurement(string $body): bool
    {
        $text = mb_strtolower($body);

        foreach (self::MEASUREMENT_NEEDLES as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return (bool) preg_match('/\bmeasur(e|es|ed|ing|ement)\b/u', $text);
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

    private function isAutoReply(array $payload): bool
    {
        $headers = [];

        foreach (($payload['headers'] ?? []) as $key => $value) {
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

    private function normalizeConfidence(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $confidence = (float) $value;

        if ($confidence > 1) {
            $confidence = $confidence / 100;
        }

        if ($confidence < 0) {
            return 0.0;
        }

        if ($confidence > 1) {
            return 1.0;
        }

        return round($confidence, 3);
    }
}
