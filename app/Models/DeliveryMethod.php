<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'provider',
        'type',
        'description',
        'base_price_cents',
        'free_from_cents',
        'settings',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'free_from_cents' => 'integer',
            'settings' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(DeliveryTariff::class);
    }
}
