<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'primary_category_id',
        'brand_id',
        'color_group_id',
        'size_chart_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'status',
        'price_cents',
        'old_price_cents',
        'cost_price_cents',
        'currency',
        'stock_status',
        'is_featured',
        'is_new',
        'is_bestseller',
        'color_sort_order',
        'sort_order',
        'meta_title',
        'meta_description',
        'seo_text',
        'canonical_url',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'old_price_cents' => 'integer',
            'cost_price_cents' => 'integer',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'is_bestseller' => 'boolean',
            'color_sort_order' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function colorGroup(): BelongsTo
    {
        return $this->belongsTo(ProductColorGroup::class, 'color_group_id');
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot(['is_primary', 'sort_order'])->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->withPivot(['type', 'sort_order'])
            ->withTimestamps();
    }

    public function menuItems(): MorphMany
    {
        return $this->morphMany(MenuItem::class, 'linkable');
    }
}
