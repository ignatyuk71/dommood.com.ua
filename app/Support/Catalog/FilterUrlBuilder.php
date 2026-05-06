<?php

namespace App\Support\Catalog;

class FilterUrlBuilder
{
    public const FILTER_PREFIX = 'filter';

    public const PAIR_SEPARATOR = '--';

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

        foreach ($segments as $segment) {
            $segment = trim((string) $segment, '/');

            if (! str_contains($segment, self::PAIR_SEPARATOR)) {
                continue;
            }

            [$attributeSlug, $valueSlug] = explode(self::PAIR_SEPARATOR, $segment, 2);
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
                $segments[] = $attributeSlug.self::PAIR_SEPARATOR.$valueSlug;
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
