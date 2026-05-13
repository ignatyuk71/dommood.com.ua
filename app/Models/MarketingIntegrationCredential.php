<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingIntegrationCredential extends Model
{
    protected $fillable = [
        'marketing_integration_id',
        'secret_type',
        'secret_value',
        'secret_last_four',
        'last_rotated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'secret_value' => 'encrypted',
            'last_rotated_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketingIntegration::class, 'marketing_integration_id');
    }
}
