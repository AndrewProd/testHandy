<?php

namespace App\Domain\FieldService\Models;

use App\Domain\FieldService\Enums\FieldVisitStatus;
use Database\Factories\FieldVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'field_service_request_id',
        'team_id',
        'scheduled_at',
        'status',
        'distance_km',
        'notes',
    ];

    protected $casts = [
        'status' => FieldVisitStatus::class,
        'scheduled_at' => 'datetime',
        'distance_km' => 'float',
    ];

    protected static function newFactory(): FieldVisitFactory
    {
        return FieldVisitFactory::new();
    }

    public function request()
    {
        return $this->belongsTo(FieldServiceRequest::class, 'field_service_request_id');
    }

    public function team()
    {
        return $this->belongsTo(FieldTeam::class, 'team_id');
    }
}
