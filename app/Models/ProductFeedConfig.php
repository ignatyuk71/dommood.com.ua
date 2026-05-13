<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFeedConfig extends Model
{
    protected $fillable = [
        'product_id',
        'channel',
        'is_enabled',
        'brand',
        'google_product_category',
        'custom_title',
        'custom_description',
        'google_gender',
        'google_age_group',
        'google_material',
        'google_pattern',
        'google_size_system',
        'google_size_types',
        'google_is_bundle',
        'google_item_group_id',
        'google_product_highlights',
        'google_product_details',
        'custom_label_0',
        'custom_label_1',
        'custom_label_2',
        'custom_label_3',
        'custom_label_4',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'google_size_types' => 'array',
            'google_is_bundle' => 'boolean',
            'google_product_highlights' => 'array',
            'google_product_details' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
