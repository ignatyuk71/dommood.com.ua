<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    protected $fillable = [
        'section',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $section, string $key, array $default = []): array
    {
        return self::query()
            ->where('section', $section)
            ->where('key', $key)
            ->first()
            ?->value ?? $default;
    }

    public static function putValue(string $section, string $key, array $value): self
    {
        return self::query()->updateOrCreate(
            ['section' => $section, 'key' => $key],
            ['value' => $value],
        );
    }
}
