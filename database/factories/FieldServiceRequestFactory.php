<?php

namespace Database\Factories;

use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldServiceRequestFactory extends Factory
{
    protected $model = FieldServiceRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 42,
            'client_id' => Client::factory(),
            'reply_task_id' => null,
            'type' => FieldServiceType::Measurement,
            'status' => FieldServiceRequestStatus::New,
            'source' => FieldServiceSource::Manager,
            'address' => '18 Finch Ave W',
            'city' => 'North York',
            'region' => 'Toronto North',
            'postal_code' => 'M2N 2G9',
            'latitude' => 43.7807,
            'longitude' => -79.4163,
            'requested_window' => 'Monday afternoon',
        ];
    }
}
