<?php

namespace App\Domain\DripCampaign\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum EnrollmentStatus: string
{
    use ProvidesOptions;

    case Active = 'active';
    case Stopped = 'stopped';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Stopped => 'Stopped',
            self::Completed => 'Completed',
        };
    }
}
