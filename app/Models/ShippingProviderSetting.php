<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingProviderSetting extends Model
{
    protected $fillable = [
        'code',
        'name',
        'settings',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
