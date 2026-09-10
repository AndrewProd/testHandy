<?php

namespace App\Domain\FieldService\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum FieldServiceSource: string
{
    use ProvidesOptions;

    case AiAuto = 'ai_auto';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::AiAuto => 'AI auto',
            self::Manager => 'Manager',
        };
    }
}
