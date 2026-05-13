<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'fee_percent',
        'fixed_fee_cents',
        'settings',
        'secret_settings',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee_percent' => 'decimal:2',
            'fixed_fee_cents' => 'integer',
            'settings' => 'array',
            'secret_settings' => 'encrypted:array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
