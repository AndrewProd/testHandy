<?php

namespace App\Domain\DripCampaign\Enums\Concerns;

trait ProvidesOptions
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            static fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
