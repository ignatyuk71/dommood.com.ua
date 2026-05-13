<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilterSeoPage extends Model
{
    protected $fillable = [
        'category_id',
        'slug',
        'filters',
        'h1',
        'title',
        'meta_title',
        'meta_description',
        'canonical_url',
        'seo_text',
        'is_indexable',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_indexable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
