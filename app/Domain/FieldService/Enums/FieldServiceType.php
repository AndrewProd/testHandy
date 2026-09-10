<?php

namespace App\Domain\FieldService\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum FieldServiceType: string
{
    use ProvidesOptions;

    case Measurement = 'measurement';
    case Consultation = 'consultation';
    case QuoteReview = 'quote_review';
    case Install = 'install';

    public function label(): string
    {
        return match ($this) {
            self::Measurement => 'On-site measure',
            self::Consultation => 'Consultation',
            self::QuoteReview => 'Quote review',
            self::Install => 'Install',
        };
    }
}
