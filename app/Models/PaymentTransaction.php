<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public const PROVIDER_LIQPAY = 'liqpay';

    public const PROVIDER_MONOBANK = 'monobank';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_AMOUNT_MISMATCH = 'amount_mismatch';

    protected $fillable = [
        'order_id',
        'provider',
        'external_order_id',
        'provider_transaction_id',
        'payment_method',
        'action',
        'status',
        'amount_cents',
        'currency',
        'is_test',
        'request_payload',
        'callback_payload',
        'raw_data',
        'raw_signature',
        'failure_reason',
        'processed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'is_test' => 'boolean',
            'request_payload' => 'array',
            'callback_payload' => 'array',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->latest('id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
