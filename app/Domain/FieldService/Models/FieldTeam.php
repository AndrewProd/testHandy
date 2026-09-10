<?php

namespace App\Domain\FieldService\Models;

use App\Domain\FieldService\Enums\FieldServiceType;
use Database\Factories\FieldTeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'region',
        'services',
        'work_starts_at',
        'work_ends_at',
        'daily_capacity',
        'lead_cap',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'services' => 'array',
        'daily_capacity' => 'integer',
        'lead_cap' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): FieldTeamFactory
    {
        return FieldTeamFactory::new();
    }

    public function members()
    {
        return $this->hasMany(FieldTeamMember::class, 'team_id');
    }

    public function visits()
    {
        return $this->hasMany(FieldVisit::class, 'team_id');
    }

    public function requests()
    {
        return $this->hasMany(FieldServiceRequest::class, 'assigned_team_id');
    }

    public function offersService(FieldServiceType $type): bool
    {
        return in_array($type->value, $this->services ?? [], true);
    }

    public function workStartHour(): int
    {
        return (int) substr((string) $this->work_starts_at, 0, 2);
    }

    public function workEndHour(): int
    {
        return (int) substr((string) $this->work_ends_at, 0, 2);
    }
}
