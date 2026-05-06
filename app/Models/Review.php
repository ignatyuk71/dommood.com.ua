<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'product_id',
        'customer_id',
        'moderated_by',
        'replied_by',
        'author_name',
        'author_email',
        'author_phone',
        'rating',
        'title',
        'body',
        'status',
        'is_verified_buyer',
        'source',
        'moderation_note',
        'admin_reply',
        'published_at',
        'moderated_at',
        'replied_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_buyer' => 'boolean',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
