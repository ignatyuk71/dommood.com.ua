<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\SitemapRun;
use App\Support\Catalog\FilterUrlBuilder;
use Illuminate\Support\Facades\File;

class SitemapGenerator
{
    public function __construct(private readonly FilterUrlBuilder $filterUrlBuilder) {}

    public function generate(?int $userId = null): SitemapRun
    {
        $startedAt = now();
        $products = Product::query()
            ->active()
            ->with(['primaryCategory:id,name,slug', 'categories:id,name,slug'])
            ->get();
        $categories = Category::query()->where('is_active', true)->get(['id', 'slug', 'updated_at']);
        $pages = ContentPage::query()->published()->get(['id', 'slug', 'updated_at']);
        $filterPages = FilterSeoPage::query()
            ->with('category:id,slug')
            ->where('is_indexable', true)
            ->active()
            ->get()
            ->filter(fn (FilterSeoPage $page): bool => ($page->filters ?? []) !== []);

        $urls = collect()
            ->merge($products->map(fn (Product $product): array => [
                'loc' => $this->productUrl($product),
                'lastmod' => $product->updated_at,
            ]))
            ->merge($categories->map(fn (Category $category): array => [
                'loc' => url('/catalog/'.$category->slug),
                'lastmod' => $category->updated_at,
            ]))
            ->merge($pages->map(fn (ContentPage $page): array => [
                'loc' => url('/'.$page->slug),
                'lastmod' => $page->updated_at,
            ]))
            ->merge($filterPages->map(fn (FilterSeoPage $page): array => [
                'loc' => $this->filterPageUrl($page),
                'lastmod' => $page->updated_at,
            ]))
            ->filter(fn (array $url): bool => trim((string) $url['loc']) !== '')
            ->values();

        $filePath = public_path('sitemap.xml');
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->xml($urls));

        return SitemapRun::query()->create([
            'status' => 'completed',
            'triggered_by' => $userId,
            'product_urls_count' => $products->count(),
            'category_urls_count' => $categories->count(),
            'page_urls_count' => $pages->count(),
            'total_urls_count' => $urls->count(),
            'file_path' => 'sitemap.xml',
            'meta' => [
                'url' => url('/sitemap.xml'),
                'filter_urls_count' => $filterPages->count(),
            ],
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    private function xml($urls): string
    {
        $rows = $urls
            ->map(function (array $url): string {
                $loc = e($url['loc']);
                $lastmod = $url['lastmod']?->toAtomString() ?? now()->toAtomString();

                return "    <url>\n        <loc>{$loc}</loc>\n        <lastmod>{$lastmod}</lastmod>\n    </url>";
            })
            ->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$rows."\n"
            ."</urlset>\n";
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
