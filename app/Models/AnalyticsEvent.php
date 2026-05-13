<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'event_name',
        'event_id',
        'source',
        'channel',
        'session_id',
        'customer_id',
        'order_id',
        'product_id',
        'category_id',
        'currency',
        'value_cents',
        'utm',
        'click_ids',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'value_cents' => 'integer',
            'utm' => 'array',
            'click_ids' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
