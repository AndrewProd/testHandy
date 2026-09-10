<?php

namespace App\Domain\DripCampaign\Models;

use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use Database\Factories\DripCampaignStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DripCampaignStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'position',
        'status',
        'delay_minutes',
        'channel',
        'subject',
        'message',
        'is_active',
    ];

    protected $casts = [
        'status' => LeadStatus::class,
        'channel' => DeliveryChannel::class,
        'delay_minutes' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): DripCampaignStepFactory
    {
        return DripCampaignStepFactory::new();
    }

    public function campaign()
    {
        return $this->belongsTo(DripCampaign::class, 'campaign_id');
    }
}
