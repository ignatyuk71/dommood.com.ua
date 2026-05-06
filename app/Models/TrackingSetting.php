<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
