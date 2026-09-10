<?php

namespace App\Domain\DripCampaign\Models;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Models\CampaignEnrollment;
use Database\Factories\DripCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DripCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'entry_status',
        'stop_on_reply',
        'handoff_intents',
        'field_intents',
        'suppress_intents',
    ];

    protected $casts = [
        'status' => CampaignStatus::class,
        'entry_status' => LeadStatus::class,
        'stop_on_reply' => 'boolean',
        'handoff_intents' => 'array',
        'field_intents' => 'array',
        'suppress_intents' => 'array',
    ];

    public static function defaultHandoffIntents(): array
    {
        return ['interested', 'call_back', 'question', 'request_quote', 'request_measurement'];
    }

    public static function defaultFieldIntents(): array
    {
        return ['request_measurement'];
    }

    public static function defaultSuppressIntents(): array
    {
        return ['unsubscribe', 'wrong_person'];
    }

    protected static function newFactory(): DripCampaignFactory
    {
        return DripCampaignFactory::new();
    }

    public function steps()
    {
        return $this->hasMany(DripCampaignStep::class, 'campaign_id')->orderBy('position');
    }

    public function enrollments()
    {
        return $this->hasMany(CampaignEnrollment::class, 'campaign_id');
    }
}
