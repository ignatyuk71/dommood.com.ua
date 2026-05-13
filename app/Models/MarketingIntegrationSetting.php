<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingIntegrationSetting extends Model
{
    protected $fillable = [
        'marketing_integration_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketingIntegration::class, 'marketing_integration_id');
    }
}
