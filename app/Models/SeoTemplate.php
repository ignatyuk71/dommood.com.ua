<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoTemplate extends Model
{
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_CATEGORY = 'category';
    public const ENTITY_PAGE = 'page';
    public const ENTITY_FILTER = 'filter';

    public const FIELD_TITLE = 'title';
    public const FIELD_DESCRIPTION = 'meta_description';
    public const FIELD_CANONICAL = 'canonical_url';

    protected $fillable = [
        'entity_type',
        'field',
        'template',
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
