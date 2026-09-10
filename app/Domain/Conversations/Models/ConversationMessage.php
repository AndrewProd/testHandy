<?php

namespace App\Domain\Conversations\Models;

use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageSenderType;
use App\Models\ReplyTask;
use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    protected $fillable = [
        'tenant_id',
        'reply_task_id',
        'sender_type',
        'sender_id',
        'direction',
        'channel',
        'body',
        'ai_generated',
        'provider',
        'external_message_id',
        'metadata',
    ];

    protected $casts = [
        'sender_type' => MessageSenderType::class,
        'direction' => MessageDirection::class,
        'ai_generated' => 'boolean',
        'metadata' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(ReplyTask::class, 'reply_task_id');
    }
}
