<?php

namespace App\Support\Catalog;

use Illuminate\Database\Eloquent\Builder;

class ProductFilterQuery
{
    public function __construct(private readonly FilterUrlBuilder $urlBuilder) {}

    public function apply(Builder $query, array $filters): Builder
    {
        foreach ($this->urlBuilder->normalizeFilters($filters) as $attributeSlug => $valueSlugs) {
            $query->where(function (Builder $productQuery) use ($attributeSlug, $valueSlugs): void {
                $productQuery
                    ->whereHas('attributeValues', fn (Builder $valueQuery) => $this->matchingValueQuery($valueQuery, $attributeSlug, $valueSlugs))
                    ->orWhereHas('variants', function (Builder $variantQuery) use ($attributeSlug, $valueSlugs): void {
                        $variantQuery
                            ->where('is_active', true)
                            ->whereHas('attributeValues', fn (Builder $valueQuery) => $this->matchingValueQuery($valueQuery, $attributeSlug, $valueSlugs));
                    });
            });
        }

        return $query;
    }

    private function matchingValueQuery(Builder $query, string $attributeSlug, array $valueSlugs): Builder
    {
        return $query
            ->whereIn('attribute_values.slug', $valueSlugs)
            ->whereHas('attribute', function (Builder $attributeQuery) use ($attributeSlug): void {
                $attributeQuery
                    ->where('slug', $attributeSlug)
                    ->where('is_filterable', true);
            });
    }
}
