<?php

namespace App\Services\Seo;

class SeoTemplateRenderer
{
    public function render(string $template, array $context = []): string
    {
        $context = array_merge([
            'site_name' => config('app.name', 'DomMood'),
            'current_year' => now()->year,
        ], $context);

        $replacements = collect($context)
            ->mapWithKeys(fn (mixed $value, string $key): array => ['{'.$key.'}' => $this->stringValue($value)])
            ->all();

        return trim((string) preg_replace('/\s+/u', ' ', strtr($template, $replacements)));
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return '';
    }
}
