<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitemapRun extends Model
{
    protected $fillable = [
        'status',
        'triggered_by',
        'product_urls_count',
        'category_urls_count',
        'page_urls_count',
        'total_urls_count',
        'file_path',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'product_urls_count' => 'integer',
            'category_urls_count' => 'integer',
            'page_urls_count' => 'integer',
            'total_urls_count' => 'integer',
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
