<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_method_id',
        'name',
        'code',
        'region',
        'city',
        'min_order_cents',
        'max_order_cents',
        'price_cents',
        'free_from_cents',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_method_id' => 'integer',
            'min_order_cents' => 'integer',
            'max_order_cents' => 'integer',
            'price_cents' => 'integer',
            'free_from_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class);
    }
}
