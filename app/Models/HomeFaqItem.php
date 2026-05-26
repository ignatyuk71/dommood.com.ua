<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeFaqItem extends Model
{
    protected $fillable = ['question', 'answer', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeActive($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
