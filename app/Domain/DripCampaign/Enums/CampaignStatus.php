<?php

namespace App\Domain\DripCampaign\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum CampaignStatus: string
{
    use ProvidesOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
        };
    }
}
