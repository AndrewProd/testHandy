<?php

namespace App\Domain\ReplyIntake\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum Intent: string
{
    use ProvidesOptions;

    case Interested = 'interested';
    case Question = 'question';
    case NotNow = 'not_now';
    case Unsubscribe = 'unsubscribe';
    case WrongPerson = 'wrong_person';
    case AutoReply = 'auto_reply';
    case RequestMeasurement = 'request_measurement';
    case RequestQuote = 'request_quote';
    case CallBack = 'call_back';

    public function label(): string
    {
        return match ($this) {
            self::Interested => 'Interested',
            self::Question => 'Question',
            self::NotNow => 'Not now',
            self::Unsubscribe => 'Unsubscribe',
            self::WrongPerson => 'Wrong person',
            self::AutoReply => 'Auto reply',
            self::RequestMeasurement => 'Request measurement',
            self::RequestQuote => 'Request quote',
            self::CallBack => 'Call back',
        };
    }

    public static function fromSentiment(?string $sentiment): ?self
    {
        return self::tryFrom((string) $sentiment);
    }

    public function allowsManagerMeasurement(): bool
    {
        return in_array($this, [
            self::Interested,
            self::Question,
            self::CallBack,
            self::RequestMeasurement,
            self::RequestQuote,
        ], true);
    }
}
