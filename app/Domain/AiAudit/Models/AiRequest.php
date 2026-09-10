<?php

namespace App\Domain\AiAudit\Models;

use App\Domain\AiAudit\Enums\AiPurpose;
use App\Domain\AiAudit\Enums\AiRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int         $id
 * @property int|null    $tenant_id
 * @property string      $provider
 * @property string      $model
 * @property AiPurpose   $purpose
 * @property string|null $prompt_version
 * @property string|null $system_prompt
 * @property string|null $user_prompt
 * @property int         $input_tokens
 * @property int         $output_tokens
 * @property int         $total_tokens
 * @property float|null  $temperature
 * @property int|null    $max_tokens
 * @property array|null  $request_payload
 * @property array|null  $response_payload
 * @property array|null  $structured_output
 * @property AiRequestStatus $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null    $latency_ms
 * @property float|null  $cost_usd
 */
class AiRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'provider',
        'model',
        'purpose',
        'prompt_version',
        'system_prompt',
        'user_prompt',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'temperature',
        'max_tokens',
        'request_payload',
        'response_payload',
        'structured_output',
        'status',
        'error_code',
        'error_message',
        'latency_ms',
        'cost_usd',
        'subject_type',
        'subject_id',
        'completed_at',
    ];

    protected $casts = [
        'purpose' => AiPurpose::class,
        'status' => AiRequestStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
        'structured_output' => 'array',
        'temperature' => 'float',
        'cost_usd' => 'float',
        'completed_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
