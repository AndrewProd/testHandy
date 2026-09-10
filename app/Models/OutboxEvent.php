<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $tenant_id
 * @property string      $subject
 * @property string|null $dedupe_key
 * @property array       $payload
 * @property string      $status
 * @property int         $attempts
 * @property mixed       $available_at
 * @property mixed       $published_at
 * @property string|null $last_error
 */
class OutboxEvent extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'tenant_id',
        'subject',
        'dedupe_key',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected $casts = [
        'payload'      => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
