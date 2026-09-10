<?php

namespace App\Domain\FieldService\Models;

use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Models\Client;
use App\Models\ReplyTask;
use Database\Factories\FieldServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'reply_task_id',
        'type',
        'status',
        'source',
        'address',
        'city',
        'region',
        'postal_code',
        'latitude',
        'longitude',
        'requested_window',
        'requested_at',
        'notes',
        'assigned_team_id',
    ];

    protected $casts = [
        'type' => FieldServiceType::class,
        'status' => FieldServiceRequestStatus::class,
        'source' => FieldServiceSource::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'requested_at' => 'datetime',
    ];

    protected static function newFactory(): FieldServiceRequestFactory
    {
        return FieldServiceRequestFactory::new();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function replyTask()
    {
        return $this->belongsTo(ReplyTask::class);
    }

    public function assignedTeam()
    {
        return $this->belongsTo(FieldTeam::class, 'assigned_team_id');
    }

    public function visits()
    {
        return $this->hasMany(FieldVisit::class);
    }

    public function latestVisit()
    {
        return $this->hasOne(FieldVisit::class)->latestOfMany();
    }
}
