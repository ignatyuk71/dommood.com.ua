<?php

namespace App\Support\Catalog;

use Illuminate\Support\Str;

class CatalogSlug
{
    public static function make(mixed $value): string
    {
        $value = Str::lower((string) $value);
        $value = strtr($value, [
            'є' => 'ie',
            'і' => 'i',
            'ї' => 'i',
            'ґ' => 'g',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'h',
            'д' => 'd',
            'е' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'y',
            'й' => 'i',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'kh',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ь' => '',
            'ю' => 'iu',
            'я' => 'ia',
            'ё' => 'e',
        ]);

        return Str::of($value)
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->replaceMatches('/^-+|-+$/', '')
            ->replaceMatches('/-{2,}/', '-')
            ->toString();
    }
}
