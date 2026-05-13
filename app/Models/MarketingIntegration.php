<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingIntegration extends Model
{
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_TIKTOK = 'tiktok';
    public const PROVIDER_META = 'meta';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    public const MODE_PROD = 'prod';
    public const MODE_TEST = 'test';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DISABLED,
    ];

    public const MODES = [
        self::MODE_PROD,
        self::MODE_TEST,
    ];

    protected $fillable = [
        'provider',
        'status',
        'mode',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DISABLED,
        'mode' => self::MODE_PROD,
    ];

    public static function providers(): array
    {
        return [
            self::PROVIDER_GOOGLE,
            self::PROVIDER_TIKTOK,
            self::PROVIDER_META,
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(MarketingIntegrationSetting::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(MarketingIntegrationCredential::class);
    }

    public function outboxEvents(): HasMany
    {
        return $this->hasMany(MarketingEventOutbox::class);
    }
}
