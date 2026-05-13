<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingEventOutbox extends Model
{
    protected $table = 'marketing_event_outbox';

    protected $fillable = [
        'marketing_integration_id',
        'provider',
        'event_name',
        'event_id',
        'transport',
        'payload',
        'status',
        'attempts',
        'last_error',
        'last_attempt_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketingIntegration::class, 'marketing_integration_id');
    }
}
