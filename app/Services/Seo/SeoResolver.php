<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\SeoSetting;
use App\Models\SeoTemplate;
use App\Support\Catalog\FilterUrlBuilder;

class SeoResolver
{
    private const DEFAULT_TEMPLATES = [
        'product' => [
            'title' => '{product_name} купити в Україні | {site_name}',
            'meta_description' => 'Купити {product_name} в інтернет-магазині {site_name}. Швидка доставка по Україні.',
            'canonical_url' => '{product_url}',
        ],
        'category' => [
            'title' => '{category_name} купити в Україні | {site_name}',
            'meta_description' => '{category_name} в інтернет-магазині {site_name}. Актуальні ціни, наявність і доставка по Україні.',
            'canonical_url' => '{category_url}',
        ],
        'page' => [
            'title' => '{page_title} | {site_name}',
            'meta_description' => '{page_title} на сайті {site_name}.',
            'canonical_url' => '{page_url}',
        ],
        'filter' => [
            'title' => '{filter_h1} | {site_name}',
            'meta_description' => '{filter_h1}: добірка товарів {site_name} з доставкою по Україні.',
            'canonical_url' => '{filter_url}',
        ],
    ];

    public function __construct(
        private readonly SeoTemplateRenderer $renderer,
        private readonly FilterUrlBuilder $filterUrlBuilder,
    ) {}

    public function metaForProduct(Product $product): array
    {
        $product->loadMissing(['primaryCategory:id,name,slug', 'categories:id,name,slug']);
        $category = $product->primaryCategory ?: $product->categories->first();
        $context = [
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category_name' => $category?->name,
            'category_slug' => $category?->slug,
            'price' => number_format(((int) $product->price_cents) / 100, 2, '.', ''),
            'product_url' => $this->productUrl($product),
        ];

        return [
            'title' => $this->firstFilled($product->meta_title, $this->renderTemplate('product', 'title', $context), $this->metaSetting('default_title')),
            'meta_description' => $this->firstFilled($product->meta_description, $this->renderTemplate('product', 'meta_description', $context), $this->metaSetting('default_meta_description')),
            'canonical_url' => $this->firstFilled($product->canonical_url, $this->renderTemplate('product', 'canonical_url', $context), $this->metaSetting('default_canonical_url')),
        ];
    }

    public function metaForCategory(Category $category): array
    {
        $context = [
            'category_name' => $category->name,
            'category_slug' => $category->slug,
            'category_url' => url('/catalog/'.$category->slug),
        ];

        return [
            'title' => $this->firstFilled($category->meta_title, $this->renderTemplate('category', 'title', $context), $this->metaSetting('default_title')),
            'meta_description' => $this->firstFilled($category->meta_description, $this->renderTemplate('category', 'meta_description', $context), $this->metaSetting('default_meta_description')),
            'canonical_url' => $this->renderTemplate('category', 'canonical_url', $context),
        ];
    }

    public function metaForPage(ContentPage $page): array
    {
        $context = [
            'page_title' => $page->title,
            'page_slug' => $page->slug,
            'page_url' => url('/'.$page->slug),
        ];

        return [
            'title' => $this->firstFilled($page->meta_title, $this->renderTemplate('page', 'title', $context), $this->metaSetting('default_title')),
            'meta_description' => $this->firstFilled($page->meta_description, $this->renderTemplate('page', 'meta_description', $context), $this->metaSetting('default_meta_description')),
            'canonical_url' => $this->firstFilled($page->canonical_url, $this->renderTemplate('page', 'canonical_url', $context), $this->metaSetting('default_canonical_url')),
        ];
    }

    public function metaForFilterPage(FilterSeoPage $page): array
    {
        $page->loadMissing('category:id,name,slug');
        $filterUrl = $this->filterPageUrl($page);
        $context = [
            'filter_h1' => $page->h1 ?: $page->title ?: $page->slug,
            'filter_slug' => $page->slug,
            'filter_url' => $filterUrl,
            'category_name' => $page->category?->name,
            'category_slug' => $page->category?->slug,
        ];

        return [
            'title' => $this->firstFilled($page->meta_title, $this->renderTemplate('filter', 'title', $context), $this->metaSetting('default_title')),
            'meta_description' => $this->firstFilled($page->meta_description, $this->renderTemplate('filter', 'meta_description', $context), $this->metaSetting('default_meta_description')),
            'canonical_url' => $this->firstFilled($page->canonical_url, $this->renderTemplate('filter', 'canonical_url', $context), $this->metaSetting('default_canonical_url')),
        ];
    }

    public function renderTemplate(string $entityType, string $field, array $context = []): string
    {
        $template = SeoTemplate::query()
            ->active()
            ->where('entity_type', $entityType)
            ->where('field', $field)
            ->value('template') ?: (self::DEFAULT_TEMPLATES[$entityType][$field] ?? '');

        return $this->renderer->render($template, $context);
    }

    public function defaultTemplates(): array
    {
        return self::DEFAULT_TEMPLATES;
    }

    private function metaSetting(string $key): ?string
    {
        return SeoSetting::getValue('meta', 'global')[$key] ?? null;
    }

    private function firstFilled(?string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function productUrl(Product $product): string
    {
        $categorySlug = $product->primaryCategory?->slug
            ?: $product->categories->first()?->slug;

        return $categorySlug
            ? url('/catalog/'.$categorySlug.'/'.$product->slug)
            : url('/products/'.$product->slug);
    }

    private function filterPageUrl(FilterSeoPage $page): string
    {
        $path = $this->filterUrlBuilder->build($page->category?->slug ?: 'catalog', $page->filters ?? []);

        return url($path);
    }
}
