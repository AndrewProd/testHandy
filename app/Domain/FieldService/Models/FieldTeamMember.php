<?php

namespace App\Domain\FieldService\Models;

use Database\Factories\FieldTeamMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'role',
        'skills',
        'is_active',
    ];

    protected $casts = [
        'skills' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): FieldTeamMemberFactory
    {
        return FieldTeamMemberFactory::new();
    }

    public function team()
    {
        return $this->belongsTo(FieldTeam::class, 'team_id');
    }

    public function hasSkill(string $skill): bool
    {
        foreach ($this->skills ?? [] as $owned) {
            if (strcasecmp((string) $owned, $skill) === 0) {
                return true;
            }
        }

        return false;
    }
}
