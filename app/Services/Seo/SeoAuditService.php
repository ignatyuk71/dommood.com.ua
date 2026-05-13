<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\SeoRedirect;
use App\Models\SeoTemplate;

class SeoAuditService
{
    public function payload(): array
    {
        $issues = [
            $this->issue('products_without_meta_title', 'Товари без meta title', Product::query()->whereNull('meta_title')->orWhere('meta_title', '')->count(), 'high'),
            $this->issue('products_without_meta_description', 'Товари без meta description', Product::query()->whereNull('meta_description')->orWhere('meta_description', '')->count(), 'high'),
            $this->issue('categories_without_seo_text', 'Категорії без SEO тексту', Category::query()->whereNull('seo_text')->orWhere('seo_text', '')->count(), 'medium'),
            $this->issue('pages_without_canonical', 'Сторінки без canonical', ContentPage::query()->whereNull('canonical_url')->orWhere('canonical_url', '')->count(), 'medium'),
            $this->issue('pages_without_h1', 'Сторінки без H1 у контенті', $this->pagesWithoutH1(), 'medium'),
            $this->issue('long_product_titles', 'Товари з довгим title понад 65 символів', $this->productTitleCount(fn (string $title): bool => mb_strlen($title) > 65), 'low'),
            $this->issue('short_product_titles', 'Товари з коротким title до 20 символів', $this->productTitleCount(fn (string $title): bool => mb_strlen($title) < 20), 'low'),
            $this->issue('duplicate_slugs', 'Дублікати slug між типами сторінок', $this->duplicateSlugCount(), 'high'),
        ];

        return [
            'summary' => [
                'score' => $this->score($issues),
                'issues_total' => collect($issues)->sum('count'),
                'products_total' => Product::query()->count(),
                'categories_total' => Category::query()->count(),
                'pages_total' => ContentPage::query()->count(),
                'active_redirects' => SeoRedirect::query()->active()->count(),
                'active_templates' => SeoTemplate::query()->active()->count(),
                'indexable_filter_pages' => FilterSeoPage::query()->where('is_indexable', true)->active()->count(),
            ],
            'issues' => $issues,
        ];
    }

    private function issue(string $key, string $label, int $count, string $priority): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'priority' => $priority,
        ];
    }

    private function pagesWithoutH1(): int
    {
        return ContentPage::query()
            ->where(function ($query): void {
                $query->whereNull('content')
                    ->orWhere('content', '')
                    ->orWhere('content', 'not like', '%<h1%')
                    ->where('content', 'not like', '%# %');
            })
            ->count();
    }

    private function duplicateSlugCount(): int
    {
        return collect()
            ->merge(Product::query()->pluck('slug')->map(fn (string $slug): string => 'product:'.$slug))
            ->merge(Category::query()->pluck('slug')->map(fn (string $slug): string => 'category:'.$slug))
            ->merge(ContentPage::query()->pluck('slug')->map(fn (string $slug): string => 'page:'.$slug))
            ->map(fn (string $value): string => str($value)->after(':')->toString())
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->count();
    }

    private function productTitleCount(callable $callback): int
    {
        return Product::query()
            ->whereNotNull('meta_title')
            ->pluck('meta_title')
            ->map(fn (?string $title): string => trim((string) $title))
            ->filter(fn (string $title): bool => $title !== '' && $callback($title))
            ->count();
    }

    private function score(array $issues): int
    {
        $penalty = collect($issues)->sum(function (array $issue): int {
            $weight = match ($issue['priority']) {
                'high' => 8,
                'medium' => 4,
                default => 2,
            };

            return min(40, $issue['count'] * $weight);
        });

        return max(0, 100 - $penalty);
    }
}
