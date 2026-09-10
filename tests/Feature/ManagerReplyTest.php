<?php

namespace Tests\Feature;

use App\Contracts\OutboundMailer;
use App\Domain\Conversations\Models\ConversationMessage;
use App\Models\Client;
use App\Models\OutboxEvent;
use App\Models\ReplyTask;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\SendGridMailer;
use Tests\TestCase;

class ManagerReplyTest extends TestCase
{
    public function test_manager_send_goes_through_mailgun_and_writes_outbox(): void
    {
        $this->app['config']->set('outbound_mail.driver', 'mailgun');
        $this->app->bind(OutboundMailer::class, MailgunMailer::class);

        $task = $this->openTask();

        $this->postJson('/api/reply-tasks/' . $task->id . '/messages', [
            'body' => 'Sure — Thursday 14:00 works. I will call you then.',
            'manager_id' => 2,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'waiting_customer')
            ->assertJsonPath('mail_provider', 'mailgun');

        $message = ConversationMessage::query()->where('reply_task_id', $task->id)->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('manager', $message->sender_type->value);
        $this->assertSame('mailgun', $message->provider);
        $this->assertStringContainsString('@mg.ourdomain.com', (string) $message->external_message_id);
        $this->assertTrue(
            OutboxEvent::query()->where('subject', 'conversation.message.sent')->exists()
        );
        $this->assertSame('waiting_customer', $task->fresh()->status);
    }

    public function test_sendgrid_driver_stamps_provider_on_the_message(): void
    {
        $this->app['config']->set('outbound_mail.driver', 'sendgrid');
        $this->app->bind(OutboundMailer::class, SendGridMailer::class);

        $task = $this->openTask();

        $this->postJson('/api/reply-tasks/' . $task->id . '/messages', [
            'body' => 'Sending via SendGrid mock.',
        ], $this->headers())->assertCreated();

        $this->assertSame(
            'sendgrid',
            ConversationMessage::query()->where('reply_task_id', $task->id)->value('provider')
        );
        $this->assertStringStartsWith(
            'sg.',
            (string) ConversationMessage::query()->where('reply_task_id', $task->id)->value('external_message_id')
        );
    }

    public function test_copilot_drafts_but_does_not_send(): void
    {
        $task = $this->openTask([
            'sentiment' => 'interested',
            'intent' => 'call_back',
            'body' => 'Can you call me Thursday afternoon?',
        ]);

        $this->postJson('/api/reply-tasks/' . $task->id . '/copilot', [
            'action' => 'draft',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.action', 'draft')
            ->assertJsonPath('data.snapshot.next_best_action', 'SCHEDULE_CALLBACK');

        $this->assertSame(0, ConversationMessage::query()->where('sender_type', 'manager')->count());
        $this->assertSame(0, OutboxEvent::query()->where('subject', 'conversation.message.sent')->count());
    }

    public function test_suppressed_client_cannot_be_emailed(): void
    {
        $client = Client::factory()->suppressed()->create();
        $task = ReplyTask::factory()->create([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'status' => 'open',
        ]);

        $this->postJson('/api/reply-tasks/' . $task->id . '/messages', [
            'body' => 'This should not go out.',
        ], $this->headers())->assertUnprocessable();
    }

    private function openTask(array $attrs = []): ReplyTask
    {
        $client = Client::factory()->create(['tenant_id' => 42, 'name' => 'Dana Walker']);

        return ReplyTask::factory()->create(array_merge([
            'tenant_id' => 42,
            'client_id' => $client->id,
            'sentiment' => 'interested',
            'intent' => 'interested',
            'status' => 'in_progress',
            'assignee_id' => 2,
            'body' => 'Sounds good. Can you call me Thursday afternoon?',
        ], $attrs));
    }

    private function headers(): array
    {
        return ['X-Tenant-Id' => '42'];
    }
}
