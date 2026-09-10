<?php

namespace App\Domain\ReplyIntake\Data;

use App\Domain\ReplyIntake\Enums\Intent;

final class ReplyAnalysis
{
    public const AUTO_FIELD_CONFIDENCE = 0.85;

    public function __construct(
        public readonly ?string $sentiment,
        public readonly ?Intent $intent,
        public readonly ?float $confidence,
        public readonly string $body,
    ) {}

    public function shouldAutoCreateFieldRequest(): bool
    {
        return $this->intent === Intent::RequestMeasurement
            && $this->confidence !== null
            && $this->confidence >= self::AUTO_FIELD_CONFIDENCE;
    }
}
