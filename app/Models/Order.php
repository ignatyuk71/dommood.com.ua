<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_provider',
        'payment_reference',
        'paid_at',
        'delivery_method',
        'delivery_provider',
        'delivery_type',
        'delivery_city',
        'delivery_city_ref',
        'delivery_address',
        'delivery_branch',
        'delivery_branch_ref',
        'delivery_recipient_name',
        'delivery_recipient_phone',
        'delivery_snapshot',
        'customer_name',
        'customer_phone',
        'customer_email',
        'currency',
        'subtotal_cents',
        'discount_total_cents',
        'delivery_price_cents',
        'total_cents',
        'promocode_code',
        'comment',
        'manager_comment',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'landing_page_url',
        'referrer_url',
        'ip_address',
        'user_agent',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'delivery_price_cents' => 'integer',
            'total_cents' => 'integer',
            'delivery_snapshot' => 'array',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
