<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'section',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }
}
