<?php

namespace App\Domain\Conversations\Services;

use App\Domain\ReplyIntake\Enums\Intent;
use App\Models\ReplyTask;

class ReplyCopilot
{
    public function snapshot(ReplyTask $task): array
    {
        $intent = $task->intent ?? Intent::fromSentiment($task->sentiment);
        $action = $this->nextAction($task, $intent);

        return [
            'intent' => $intent?->value,
            'sentiment' => $task->sentiment,
            'urgency' => $this->urgency($intent),
            'confidence' => $task->confidence,
            'needs_human' => $intent !== Intent::AutoReply,
            'next_best_action' => $action['code'],
            'reason' => $action['reason'],
            'suggested_task' => $action['task'],
        ];
    }

    public function draft(ReplyTask $task, ?string $current = null): string
    {
        if ($current !== null && trim($current) !== '') {
            return $this->improve($task, $current);
        }

        $name = $task->client?->name ?: 'there';
        $intent = $task->intent ?? Intent::fromSentiment($task->sentiment);

        return match ($intent) {
            Intent::CallBack, Intent::Interested => "Hi {$name},\n\nHappy to help. I can call you to walk through the quote — what time tomorrow works best?\n\nBest,\nNorthshore Windows",
            Intent::RequestMeasurement => "Hi {$name},\n\nWe can send a technician to measure on site. Which day next week works, and is the address on file still correct?\n\nBest,\nNorthshore Windows",
            Intent::RequestQuote => "Hi {$name},\n\nI can send an updated quote. How many openings are we looking at, and is this a replacement or new construction?\n\nBest,\nNorthshore Windows",
            Intent::Question => "Hi {$name},\n\nThanks for the question — I'll get you a clear answer on that. If useful I can also jump on a short call.\n\nBest,\nNorthshore Windows",
            Intent::NotNow => "Hi {$name},\n\nNo problem at all. I'll pause the follow-ups. If you'd like, I can check back in the spring.\n\nBest,\nNorthshore Windows",
            Intent::Unsubscribe, Intent::WrongPerson => "Hi {$name},\n\nSorry for the noise — I've removed you from this sequence and you won't hear from us again.\n\nBest,\nNorthshore Windows",
            default => "Hi {$name},\n\nThanks for getting back to us. How can we help from here?\n\nBest,\nNorthshore Windows",
        };
    }

    public function improve(ReplyTask $task, string $current): string
    {
        $text = trim($current);
        $name = $task->client?->name ?: 'there';

        if (!str_starts_with(mb_strtolower($text), 'hi ')) {
            $text = "Hi {$name},\n\n" . $text;
        }

        if (!str_contains(mb_strtolower($text), 'northshore')) {
            $text .= "\n\nBest,\nNorthshore Windows";
        }

        return $text;
    }

    public function summarize(ReplyTask $task): string
    {
        $intent = $task->intent?->label() ?? $task->sentiment ?? 'unclassified';
        $name = $task->client?->name ?? 'Customer';
        $campaign = $task->campaign_id ? 'campaign #' . $task->campaign_id : 'the current drip';

        $lines = [
            "{$name} replied on {$campaign}.",
            "Intent: {$intent}" . ($task->confidence ? ' (' . round($task->confidence * 100) . '% confidence).' : '.'),
        ];

        if ($task->body) {
            $lines[] = 'Latest customer note: "' . mb_strimwidth($task->body, 0, 140, '…') . '"';
        }

        $action = $this->nextAction($task, $task->intent ?? Intent::fromSentiment($task->sentiment));
        $lines[] = 'Recommended: ' . $action['reason'];

        return implode("\n", $lines);
    }

    private function nextAction(ReplyTask $task, ?Intent $intent): array
    {
        $body = mb_strtolower((string) $task->body);

        if ($intent === Intent::RequestMeasurement) {
            return [
                'code' => 'SCHEDULE_MEASUREMENT',
                'reason' => 'Customer asked for an on-site measure.',
                'task' => 'measurement',
            ];
        }

        if ($intent === Intent::CallBack || str_contains($body, 'call me')) {
            return [
                'code' => 'SCHEDULE_CALLBACK',
                'reason' => 'Customer asked for a call.',
                'task' => 'call_back',
            ];
        }

        if ($intent === Intent::RequestQuote) {
            return [
                'code' => 'SEND_QUOTE',
                'reason' => 'Customer wants numbers.',
                'task' => 'quote',
            ];
        }

        if ($intent === Intent::Unsubscribe || $intent === Intent::WrongPerson) {
            return [
                'code' => 'STOP_CONTACT',
                'reason' => 'Do not keep this person on drip.',
                'task' => 'suppression',
            ];
        }

        if ($intent === Intent::NotNow) {
            return [
                'code' => 'PAUSE_DRIP',
                'reason' => 'Interest later — keep them suppressed from this sequence.',
                'task' => 'nurture',
            ];
        }

        if ($intent === Intent::Question) {
            return [
                'code' => 'ANSWER_QUESTION',
                'reason' => 'Reply with a clear answer, then offer a call.',
                'task' => 'reply',
            ];
        }

        return [
            'code' => 'DRAFT_REPLY',
            'reason' => 'Send a human reply before any field visit or quote.',
            'task' => 'reply',
        ];
    }

    private function urgency(?Intent $intent): string
    {
        return match ($intent) {
            Intent::Unsubscribe, Intent::WrongPerson, Intent::RequestMeasurement => 'high',
            Intent::CallBack, Intent::Interested, Intent::RequestQuote, Intent::Question => 'medium',
            Intent::NotNow, Intent::AutoReply => 'low',
            default => 'medium',
        };
    }
}
