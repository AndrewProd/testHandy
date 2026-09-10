<?php

namespace App\Models;

use App\Domain\Conversations\Models\ConversationMessage;
use App\Domain\FieldService\Models\FieldServiceRequest;
use App\Domain\ReplyIntake\Enums\Intent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplyTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'event_id',
        'sentiment',
        'intent',
        'confidence',
        'body',
        'status',
        'assignee_id',
        'campaign_id',
        'notes',
    ];

    protected $casts = [
        'intent' => Intent::class,
        'confidence' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function fieldServiceRequest()
    {
        return $this->hasOne(FieldServiceRequest::class);
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at')->orderBy('id');
    }
}
