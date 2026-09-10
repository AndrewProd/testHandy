<?php

namespace App\Domain\ReplyIntake\Services;

use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\FieldService\Services\FieldServiceRequestService;
use App\Domain\ReplyIntake\Data\ReplyAnalysis;
use App\Models\Client;
use App\Models\ReplyTask;

class IntentRouter
{
    public function __construct(
        private readonly FieldServiceRequestService $fieldRequests,
    ) {}

    /**
     * @return array{field_service_request: FieldServiceRequest|null}
     */
    public function route(ReplyTask $task, ReplyAnalysis $analysis, Client $client): array
    {
        if (!$analysis->shouldAutoCreateFieldRequest()) {
            return ['field_service_request' => null];
        }

        $request = $this->fieldRequests->createFromReply(
            $task,
            $client,
            FieldServiceSource::AiAuto,
            FieldServiceType::Measurement,
        );

        return ['field_service_request' => $request];
    }
}
