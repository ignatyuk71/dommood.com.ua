<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoIndexingRule extends Model
{
    public const PATTERN_EXACT = 'exact';
    public const PATTERN_PREFIX = 'prefix';
    public const PATTERN_REGEX = 'regex';

    protected $fillable = [
        'name',
        'pattern',
        'pattern_type',
        'robots_directive',
        'meta_robots',
        'canonical_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
