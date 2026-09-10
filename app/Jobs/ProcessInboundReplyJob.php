<?php

namespace App\Jobs;

use App\Contracts\SentimentClassifier;
use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageSenderType;
use App\Domain\Conversations\Models\ConversationMessage;
use App\Domain\ReplyIntake\Data\ReplyAnalysis;
use App\Domain\ReplyIntake\Services\IntentRouter;
use App\Domain\ReplyIntake\Services\ReplyAnalyzer;
use App\Models\CampaignEnrollment;
use App\Models\Client;
use App\Models\ReplyTask;
use App\Support\IdempotencyGuard;
use App\Support\Outbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessInboundReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param array $payload decoded reply.received event
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(
        SentimentClassifier $classifier,
        IdempotencyGuard $guard,
        Outbox $outbox,
        ReplyAnalyzer $analyzer,
        IntentRouter $router,
    ): void {
        $tenantId = (int) ($this->payload['tenant_id'] ?? 0);
        $eventId  = (string) ($this->payload['event_id'] ?? '');

        if ($tenantId <= 0 || $eventId === '') {
            return;
        }

        DB::transaction(function () use ($classifier, $guard, $outbox, $analyzer, $router, $tenantId, $eventId) {
            if (!$guard->claim($tenantId, $eventId)) {
                return;
            }

            $client = $this->findClient($tenantId);

            if ($client === null) {
                return;
            }

            $analysis = $analyzer->analyze($this->payload, $classifier);
            $campaignId = CampaignEnrollment::query()
                ->where('tenant_id', $tenantId)
                ->where('client_id', $client->id)
                ->value('campaign_id');

            $created = ReplyTask::query()->insertOrIgnore([
                'tenant_id'   => $tenantId,
                'client_id'   => $client->id,
                'event_id'    => $eventId,
                'sentiment'   => $analysis->sentiment,
                'intent'      => $analysis->intent?->value,
                'confidence'  => $analysis->confidence,
                'body'        => $analysis->body,
                'status'      => 'open',
                'campaign_id' => $campaignId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            if ($created === 0) {
                return;
            }

            $effects = $this->applyCampaignEffects($tenantId, $client, $analysis->sentiment);

            $task = ReplyTask::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->first();

            $this->recordInbound($task, $analysis, $eventId);

            $routing = $router->route($task, $analysis, $client);

            $outbox->emit($tenantId, 'events.reply.processed', [
                'event_id'                  => $eventId,
                'tenant_id'                 => $tenantId,
                'client_id'                 => $client->id,
                'reply_task_id'             => $task->id,
                'sentiment'                 => $analysis->sentiment,
                'intent'                    => $analysis->intent?->value,
                'confidence'                => $analysis->confidence,
                'field_service_request_id'  => $routing['field_service_request']?->id,
                'campaign_stopped'          => $effects['campaign_stopped'],
                'client_suppressed'         => $effects['client_suppressed'],
                'occurred_at'               => now()->toIso8601String(),
            ], $eventId);
        });
    }

    private function findClient(int $tenantId): ?Client
    {
        $sender = strtolower(trim((string) ($this->payload['sender'] ?? '')));

        if ($sender === '' || !str_contains($sender, '@')) {
            return null;
        }

        if ($this->isOurAddress($sender)) {
            return null;
        }

        return Client::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$sender])
            ->first();
    }

    private function isOurAddress(string $email): bool
    {
        if (str_starts_with($email, 'campaign+')) {
            return true;
        }

        $recipient = strtolower(trim((string) ($this->payload['recipient'] ?? '')));

        return $recipient !== '' && $email === $recipient;
    }

    /**
     * @return array{campaign_stopped: bool, client_suppressed: bool}
     */
    private function applyCampaignEffects(int $tenantId, Client $client, ?string $sentiment): array
    {
        if ($sentiment === 'auto_reply') {
            return ['campaign_stopped' => false, 'client_suppressed' => false];
        }

        $stopped = CampaignEnrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->update([
                'status'       => 'stopped',
                'next_send_at' => null,
            ]);

        $suppressed = false;

        if (in_array($sentiment, ['unsubscribe', 'wrong_person'], true) && $client->suppressed_at === null) {
            $client->forceFill(['suppressed_at' => now()])->save();
            $suppressed = true;
        }

        return [
            'campaign_stopped'  => $stopped > 0,
            'client_suppressed' => $suppressed,
        ];
    }

    private function recordInbound(ReplyTask $task, ReplyAnalysis $analysis, string $eventId): void
    {
        ConversationMessage::query()->create([
            'tenant_id' => $task->tenant_id,
            'reply_task_id' => $task->id,
            'sender_type' => MessageSenderType::Customer,
            'sender_id' => $task->client_id,
            'direction' => MessageDirection::Inbound,
            'channel' => 'email',
            'body' => $analysis->body,
            'ai_generated' => false,
            'external_message_id' => $eventId,
        ]);

        if ($analysis->intent !== null || $analysis->sentiment !== null) {
            ConversationMessage::query()->create([
                'tenant_id' => $task->tenant_id,
                'reply_task_id' => $task->id,
                'sender_type' => MessageSenderType::Ai,
                'direction' => MessageDirection::Internal,
                'channel' => 'email',
                'body' => sprintf(
                    'Intent %s · sentiment %s · confidence %s. Copilot only — a manager must send the customer reply.',
                    $analysis->intent?->value ?? 'unknown',
                    $analysis->sentiment ?? 'unclassified',
                    $analysis->confidence !== null ? number_format($analysis->confidence, 2) : 'n/a',
                ),
                'ai_generated' => true,
                'metadata' => [
                    'intent' => $analysis->intent?->value,
                    'sentiment' => $analysis->sentiment,
                    'confidence' => $analysis->confidence,
                ],
            ]);
        }
    }
}
