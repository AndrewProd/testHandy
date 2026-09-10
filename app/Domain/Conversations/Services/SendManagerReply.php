<?php

namespace App\Domain\Conversations\Services;

use App\Contracts\OutboundMailer;
use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageSenderType;
use App\Domain\Conversations\Models\ConversationMessage;
use App\Models\ReplyTask;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SendManagerReply
{
    public function __construct(
        private readonly OutboundMailer $mailer,
        private readonly Outbox $outbox,
    ) {}

    public function handle(ReplyTask $task, string $body, array $opts = []): ConversationMessage
    {
        $client = $task->client;
        if ($client === null) {
            throw new RuntimeException('Task has no client.');
        }

        if ($client->isSuppressed()) {
            throw new RuntimeException('Client is suppressed.');
        }

        $subject = $opts['subject'] ?? 'Re: your window quote';
        $aiGenerated = (bool) ($opts['ai_generated'] ?? false);
        $managerId = isset($opts['manager_id']) ? (int) $opts['manager_id'] : $task->assignee_id;

        $result = $this->mailer->send(
            $client->email,
            $subject,
            $body,
            ['In-Reply-To' => $task->event_id],
        );

        return DB::transaction(function () use ($task, $body, $subject, $aiGenerated, $managerId, $result) {
            $message = ConversationMessage::query()->create([
                'tenant_id' => $task->tenant_id,
                'reply_task_id' => $task->id,
                'sender_type' => MessageSenderType::Manager,
                'sender_id' => $managerId,
                'direction' => MessageDirection::Outbound,
                'channel' => 'email',
                'body' => $body,
                'ai_generated' => $aiGenerated,
                'provider' => $result->provider,
                'external_message_id' => $result->messageId,
                'metadata' => ['subject' => $subject],
            ]);

            $task->forceFill([
                'status' => 'waiting_customer',
                'assignee_id' => $managerId ?: $task->assignee_id,
            ])->save();

            $this->outbox->emit($task->tenant_id, 'conversation.message.sent', [
                'reply_task_id' => $task->id,
                'message_id' => $message->id,
                'client_id' => $task->client_id,
                'event_id' => $task->event_id,
                'provider' => $result->provider,
                'external_message_id' => $result->messageId,
                'ai_generated' => $aiGenerated,
            ], 'msg:' . $message->id);

            return $message;
        });
    }
}
