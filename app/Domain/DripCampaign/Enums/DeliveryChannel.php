<?php

namespace App\Domain\DripCampaign\Enums;

use App\Domain\DripCampaign\Enums\Concerns\ProvidesOptions;

enum DeliveryChannel: string
{
    use ProvidesOptions;

    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
        };
    }

    public function requiresSubject(): bool
    {
        return $this === self::Email;
    }
}
