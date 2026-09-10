<?php

namespace App\Domain\AiAudit\Enums;

enum AiPurpose: string
{
    case ReplyClassification = 'reply_classification';
    case SentimentAnalysis = 'sentiment_analysis';
    case IntentAnalysis = 'intent_analysis';
    case ManagerAssistant = 'manager_assistant';
    case Summarization = 'summarization';

    public function label(): string
    {
        return match ($this) {
            self::ReplyClassification => 'Reply classification',
            self::SentimentAnalysis => 'Sentiment analysis',
            self::IntentAnalysis => 'Intent analysis',
            self::ManagerAssistant => 'Manager assistant',
            self::Summarization => 'Summarization',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
