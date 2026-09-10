<?php

namespace App\Domain\DripCampaign\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum LeadStatus: string
{
    use ProvidesOptions;

    case Dormant = 'dormant';
    case Contacted = 'contacted';
    case Interested = 'interested';
    case Question = 'question';
    case NotNow = 'not_now';
    case Qualified = 'qualified';
    case QuoteSent = 'quote_sent';
    case Won = 'won';
    case Lost = 'lost';
    case Unsubscribed = 'unsubscribed';
    case WrongPerson = 'wrong_person';

    public function label(): string
    {
        return match ($this) {
            self::Dormant => 'Dormant',
            self::Contacted => 'Contacted',
            self::Interested => 'Interested',
            self::Question => 'Question',
            self::NotNow => 'Not now',
            self::Qualified => 'Qualified',
            self::QuoteSent => 'Quote sent',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Unsubscribed => 'Unsubscribed',
            self::WrongPerson => 'Wrong person',
        };
    }
}
