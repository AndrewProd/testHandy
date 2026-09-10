<?php

namespace App\Domain\FieldService\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum FieldVisitStatus: string
{
    use ProvidesOptions;

    case Scheduled = 'scheduled';
    case EnRoute = 'en_route';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::EnRoute => 'En route',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    public function countsAgainstCapacity(): bool
    {
        return in_array($this, [self::Scheduled, self::EnRoute], true);
    }
}
