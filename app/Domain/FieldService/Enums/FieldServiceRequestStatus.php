<?php

namespace App\Domain\FieldService\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum FieldServiceRequestStatus: string
{
    use ProvidesOptions;

    case New = 'new';
    case Offered = 'offered';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Offered => 'Offered',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Offered, self::Scheduled, self::InProgress], true);
    }
}
