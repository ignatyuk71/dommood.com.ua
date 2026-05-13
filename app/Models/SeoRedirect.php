<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $fillable = [
        'source_path',
        'target_url',
        'status_code',
        'preserve_query',
        'is_active',
        'hits',
        'last_hit_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'preserve_query' => 'boolean',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
