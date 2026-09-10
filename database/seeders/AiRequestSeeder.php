<?php

namespace Database\Seeders;

use App\Domain\AiAudit\Enums\AiPurpose;
use App\Domain\AiAudit\Enums\AiRequestStatus;
use App\Domain\AiAudit\Models\AiRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic mock AI audit trail — ~70 LLM calls over the last two weeks,
 * mostly reply classification, with the odd failure and pending row.
 */
class AiRequestSeeder extends Seeder
{
    private const TENANT = 42;

    /** provider => [model => [input_per_1k, output_per_1k]] in USD */
    private const PRICING = [
        'openai' => [
            'gpt-5' => [0.00125, 0.01000],
            'gpt-4o-mini' => [0.00015, 0.00060],
        ],
        'anthropic' => [
            'claude-sonnet-5' => [0.00300, 0.01500],
        ],
        'ollama' => [
            'qwen3' => [0.0, 0.0],
        ],
    ];

    private const REPLIES = [
        "Thanks, I'm interested. Could you call me tomorrow afternoon?",
        'How much would eight windows on the second floor cost?',
        'Not this year, our renovation budget is already spent.',
        "You've got the wrong person, I never requested a quote.",
        'Sounds good — what is the current lead time for installation?',
        'Please remove me from your list, I do not want more emails.',
        'I am out of office until Monday with limited access to email.',
        'Yes please go ahead and send the updated pricing.',
        'Can you break down the warranty terms before I decide?',
        'We already went with another supplier, thanks anyway.',
    ];

    public function run(): void
    {
        $rows = [];

        for ($i = 0; $i < 70; $i++) {
            $rows[] = $this->makeRow($i);
        }

        // a couple of hand-crafted showcase rows
        $rows[] = $this->showcaseInterested();
        $rows[] = $this->showcaseFailure();

        foreach (array_chunk($rows, 50) as $chunk) {
            AiRequest::insert($chunk);
        }
    }

    private function makeRow(int $i): array
    {
        $purpose = $this->pickPurpose();
        [$provider, $model] = $this->pickModel();
        $promptVersion = $this->promptVersionFor($purpose);

        $reply = self::REPLIES[$i % count(self::REPLIES)];
        $inputTokens = random_int(280, 1180);
        $outputTokens = random_int(40, 280);
        $status = $this->pickStatus();

        $createdAt = Carbon::now()
            ->subDays(random_int(0, 13))
            ->subMinutes(random_int(0, 1439));

        [$structured, $latency, $completedAt, $errorCode, $errorMessage] =
            $this->outcome($status, $purpose, $createdAt);

        $totalTokens = $status === AiRequestStatus::Failed ? $inputTokens : $inputTokens + $outputTokens;
        $cost = $this->cost($provider, $model, $inputTokens, $status === AiRequestStatus::Failed ? 0 : $outputTokens);

        $system = "You are the Reply Center classifier. Return valid JSON only, matching the {$promptVersion} schema.";
        $user = "Customer reply:\n\n\"{$reply}\"";

        return [
            'tenant_id' => self::TENANT,
            'provider' => $provider,
            'model' => $model,
            'purpose' => $purpose->value,
            'prompt_version' => $promptVersion,
            'system_prompt' => $system,
            'user_prompt' => $user,
            'input_tokens' => $inputTokens,
            'output_tokens' => $status === AiRequestStatus::Failed ? 0 : $outputTokens,
            'total_tokens' => $totalTokens,
            'temperature' => [0.0, 0.1, 0.2, 0.3][random_int(0, 3)],
            'max_tokens' => [200, 300, 400, 512][random_int(0, 3)],
            'request_payload' => json_encode([
                'model' => $model,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]),
            'response_payload' => json_encode($this->responsePayload($status, $inputTokens, $outputTokens)),
            'structured_output' => $structured ? json_encode($structured) : null,
            'status' => $status->value,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'latency_ms' => $latency,
            'cost_usd' => $cost,
            'subject_type' => random_int(0, 2) ? 'App\\Models\\ReplyTask' : null,
            'subject_id' => random_int(0, 2) ? random_int(1, 40) : null,
            'completed_at' => $completedAt,
            'created_at' => $createdAt,
            'updated_at' => $completedAt ?? $createdAt,
        ];
    }

    private function pickPurpose(): AiPurpose
    {
        return [
            AiPurpose::ReplyClassification, AiPurpose::ReplyClassification,
            AiPurpose::ReplyClassification, AiPurpose::ReplyClassification,
            AiPurpose::IntentAnalysis, AiPurpose::SentimentAnalysis,
            AiPurpose::ManagerAssistant, AiPurpose::Summarization,
        ][random_int(0, 7)];
    }

    /** @return array{0:string,1:string} */
    private function pickModel(): array
    {
        $options = [
            ['openai', 'gpt-5'], ['openai', 'gpt-5'], ['openai', 'gpt-4o-mini'],
            ['anthropic', 'claude-sonnet-5'], ['ollama', 'qwen3'],
        ];

        return $options[random_int(0, count($options) - 1)];
    }

    private function promptVersionFor(AiPurpose $purpose): string
    {
        return match ($purpose) {
            AiPurpose::ReplyClassification => ['reply_classifier_v1', 'reply_classifier_v2', 'reply_classifier_v3', 'reply_classifier_v3'][random_int(0, 3)],
            AiPurpose::IntentAnalysis => 'reply_classifier_v3',
            AiPurpose::SentimentAnalysis => 'sentiment_v1',
            AiPurpose::ManagerAssistant => 'assistant_v1',
            AiPurpose::Summarization => 'summary_v1',
        };
    }

    private function pickStatus(): AiRequestStatus
    {
        $r = random_int(1, 100);

        return match (true) {
            $r <= 93 => AiRequestStatus::Completed,
            $r <= 98 => AiRequestStatus::Failed,
            default => AiRequestStatus::Pending,
        };
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: int|null, 2: Carbon|null, 3: string|null, 4: string|null}
     */
    private function outcome(AiRequestStatus $status, AiPurpose $purpose, Carbon $createdAt): array
    {
        if ($status === AiRequestStatus::Pending) {
            return [null, null, null, null, null];
        }

        if ($status === AiRequestStatus::Failed) {
            [$code, $msg, $latency] = [
                ['timeout', 'Upstream request timed out after 30000ms', random_int(28000, 31000)],
                ['invalid_json', 'Model returned text that is not valid JSON', random_int(600, 2200)],
                ['rate_limit', 'Provider returned 429 Too Many Requests', random_int(200, 800)],
                ['schema_mismatch', 'JSON did not match the expected schema (missing "intent")', random_int(700, 1900)],
            ][random_int(0, 3)];

            return [null, $latency, $createdAt->copy()->addMilliseconds($latency), $code, $msg];
        }

        $latency = random_int(420, 3400);

        return [
            $this->structuredOutput($purpose),
            $latency,
            $createdAt->copy()->addMilliseconds($latency),
            null,
            null,
        ];
    }

    private function structuredOutput(AiPurpose $purpose): array
    {
        $intent = ['INTERESTED', 'CALL_BACK', 'QUESTION', 'NOT_INTERESTED', 'UNSUBSCRIBE', 'AUTO_REPLY'][random_int(0, 5)];
        $sentiment = ['POSITIVE', 'NEUTRAL', 'NEGATIVE'][random_int(0, 2)];
        $confidence = round(0.62 + random_int(0, 37) / 100, 2);

        return match ($purpose) {
            AiPurpose::SentimentAnalysis => [
                'sentiment' => $sentiment,
                'confidence' => $confidence,
            ],
            AiPurpose::ManagerAssistant => [
                'suggested_reply' => 'Happy to help — I can call you tomorrow afternoon, does 2pm work?',
                'tone' => 'friendly',
            ],
            AiPurpose::Summarization => [
                'summary' => 'Customer wants a callback and a firm quote for second-floor windows.',
            ],
            default => [
                'intent' => $intent,
                'sentiment' => $sentiment,
                'urgency' => ['LOW', 'MEDIUM', 'HIGH'][random_int(0, 2)],
                'confidence' => $confidence,
                'language' => 'en',
                'reason' => 'Customer expressed intent and asked to be contacted.',
            ],
        };
    }

    private function responsePayload(AiRequestStatus $status, int $in, int $out): array
    {
        if ($status === AiRequestStatus::Pending) {
            return ['state' => 'in_flight'];
        }

        if ($status === AiRequestStatus::Failed) {
            return ['error' => ['type' => 'upstream_error']];
        }

        return [
            'id' => 'chatcmpl_' . bin2hex(random_bytes(6)),
            'finish_reason' => 'stop',
            'usage' => [
                'prompt_tokens' => $in,
                'completion_tokens' => $out,
                'total_tokens' => $in + $out,
            ],
        ];
    }

    private function cost(string $provider, string $model, int $in, int $out): float
    {
        [$pin, $pout] = self::PRICING[$provider][$model] ?? [0.0, 0.0];

        return round(($in / 1000) * $pin + ($out / 1000) * $pout, 8);
    }

    private function showcaseInterested(): array
    {
        $created = Carbon::now()->subHours(3);
        $system = "You are the Reply Center classifier.\nReturn valid JSON only.";
        $user = "Customer reply:\n\n\"Thanks, I'm interested. Could you call me tomorrow afternoon?\"";

        return [
            'tenant_id' => self::TENANT,
            'provider' => 'openai',
            'model' => 'gpt-5',
            'purpose' => AiPurpose::ReplyClassification->value,
            'prompt_version' => 'reply_classifier_v3',
            'system_prompt' => $system,
            'user_prompt' => $user,
            'input_tokens' => 421,
            'output_tokens' => 87,
            'total_tokens' => 508,
            'temperature' => 0.10,
            'max_tokens' => 300,
            'request_payload' => json_encode([
                'model' => 'gpt-5',
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'system', 'content' => 'You classify customer replies.'],
                    ['role' => 'user', 'content' => 'Thanks, I am interested...'],
                ],
            ]),
            'response_payload' => json_encode(['id' => 'chatcmpl_mock_001', 'finish_reason' => 'stop']),
            'structured_output' => json_encode([
                'intent' => 'CALL_BACK',
                'sentiment' => 'POSITIVE',
                'urgency' => 'MEDIUM',
                'confidence' => 0.94,
                'language' => 'en',
                'reason' => 'Customer requested a callback tomorrow afternoon',
            ]),
            'status' => AiRequestStatus::Completed->value,
            'error_code' => null,
            'error_message' => null,
            'latency_ms' => 842,
            'cost_usd' => 0.00482100,
            'subject_type' => 'App\\Models\\ReplyTask',
            'subject_id' => 1,
            'completed_at' => $created->copy()->addMilliseconds(842),
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    private function showcaseFailure(): array
    {
        $created = Carbon::now()->subHours(9);

        return [
            'tenant_id' => self::TENANT,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'purpose' => AiPurpose::ReplyClassification->value,
            'prompt_version' => 'reply_classifier_v2',
            'system_prompt' => 'You classify customer replies. Return valid JSON only.',
            'user_prompt' => "Customer reply:\n\n\"k\"",
            'input_tokens' => 302,
            'output_tokens' => 0,
            'total_tokens' => 302,
            'temperature' => 0.00,
            'max_tokens' => 300,
            'request_payload' => json_encode(['model' => 'gpt-4o-mini']),
            'response_payload' => json_encode(['error' => ['type' => 'invalid_response']]),
            'structured_output' => null,
            'status' => AiRequestStatus::Failed->value,
            'error_code' => 'invalid_json',
            'error_message' => 'Model returned "I cannot classify this." instead of JSON',
            'latency_ms' => 1180,
            'cost_usd' => 0.00004530,
            'subject_type' => 'App\\Models\\ReplyTask',
            'subject_id' => 8,
            'completed_at' => $created->copy()->addMilliseconds(1180),
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }
}
