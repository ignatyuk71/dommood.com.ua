<?php

namespace App\Support\Catalog;

class FilterUrlBuilder
{
    public const FILTER_PREFIX = 'filter';

    public function build(string $categoryPath, array $filters): string
    {
        $basePath = $this->normalizeCategoryPath($categoryPath);
        $segments = $this->segments($filters);

        if ($segments === []) {
            return $basePath;
        }

        return $basePath.'/'.self::FILTER_PREFIX.'/'.implode('/', $segments);
    }

    public function parse(string $path): array
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $filterIndex = array_search(self::FILTER_PREFIX, $segments, true);

        if ($filterIndex === false) {
            return [];
        }

        return $this->parseSegments(array_slice($segments, $filterIndex + 1));
    }

    public function parseSegments(array $segments): array
    {
        $filters = [];
        $segments = array_values($segments);

        for ($index = 0; $index < count($segments); $index += 2) {
            $attributeSlug = trim((string) ($segments[$index] ?? ''), '/');
            $valueSlug = trim((string) ($segments[$index + 1] ?? ''), '/');

            if ($attributeSlug === '' || $valueSlug === '') {
                continue;
            }

            $attributeSlug = $this->normalizeSlug($attributeSlug);
            $valueSlug = $this->normalizeSlug($valueSlug);

            if ($attributeSlug === '' || $valueSlug === '') {
                continue;
            }

            $filters[$attributeSlug][] = $valueSlug;
        }

        return $this->normalizeFilters($filters);
    }

    public function segments(array $filters): array
    {
        $segments = [];

        foreach ($this->normalizeFilters($filters) as $attributeSlug => $valueSlugs) {
            foreach ($valueSlugs as $valueSlug) {
                $segments[] = $attributeSlug;
                $segments[] = $valueSlug;
            }
        }

        return $segments;
    }

    public function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $attribute => $values) {
            if (is_int($attribute) && is_array($values)) {
                $attribute = $values['attribute'] ?? $values['attribute_slug'] ?? null;
                $values = $values['values'] ?? $values['value'] ?? $values['value_slug'] ?? [];
            }

            $attributeSlug = $this->normalizeSlug($attribute);

            if ($attributeSlug === '') {
                continue;
            }

            $valueList = is_array($values) ? $values : [$values];

            foreach ($valueList as $value) {
                $valueSlug = $this->normalizeSlug($value);

                if ($valueSlug !== '') {
                    $normalized[$attributeSlug][] = $valueSlug;
                }
            }
        }

        foreach ($normalized as $attributeSlug => $valueSlugs) {
            $normalized[$attributeSlug] = array_values(array_unique($valueSlugs));
            sort($normalized[$attributeSlug], SORT_NATURAL);
        }

        ksort($normalized, SORT_NATURAL);

        return $normalized;
    }

    private function normalizeCategoryPath(string $categoryPath): string
    {
        $path = trim($categoryPath, '/');

        if ($path === '') {
            return '/catalog';
        }

        if (! str_contains($path, '/') && $path !== 'catalog') {
            return '/catalog/'.$this->normalizeSlug($path);
        }

        return '/'.$path;
    }

    private function normalizeSlug(mixed $value): string
    {
        return CatalogSlug::make($value);
    }
}
