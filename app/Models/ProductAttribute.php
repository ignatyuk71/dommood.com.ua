<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
    use HasFactory;

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTI_SELECT = 'multi_select';

    public const TYPE_COLOR = 'color';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPES = [
        self::TYPE_SELECT,
        self::TYPE_MULTI_SELECT,
        self::TYPE_COLOR,
        self::TYPE_BOOLEAN,
    ];

    protected $table = 'attributes';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_filterable',
        'is_variant_option',
        'sort_order',
    ];

    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_variant_option' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id')
            ->orderBy('sort_order')
            ->orderBy('value');
    }
}
