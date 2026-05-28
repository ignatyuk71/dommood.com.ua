<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [];

        // Home
        $urls[] = [
            'loc' => url('/'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '1.0',
            'images' => [],
        ];

        // Catalog index
        $urls[] = [
            'loc' => url('/catalog'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '0.9',
            'images' => [],
        ];

        // Categories (with category image)
        Category::query()
            ->orderBy('id')
            ->get(['slug', 'name', 'image_path', 'updated_at'])
            ->each(function (Category $category) use (&$urls): void {
                $images = [];

                if ($category->image_path) {
                    $images[] = [
                        'loc' => Storage::disk('public')->url($category->image_path),
                        'title' => $category->name,
                    ];
                }

                $urls[] = [
                    'loc' => url('/catalog/'.$category->slug),
                    'lastmod' => $category->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.8',
                    'images' => $images,
                ];
            });

        // Products (active only) with their images
        Product::query()
            ->where('status', 'active')
            ->with([
                'primaryCategory:id,slug',
                'images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')->limit(5),
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'primary_category_id', 'updated_at'])
            ->each(function (Product $product) use (&$urls): void {
                $categorySlug = $product->primaryCategory?->slug;

                if (! $categorySlug) {
                    return;
                }

                $images = $product->images
                    ->take(5)
                    ->map(fn (ProductImage $img): array => [
                        'loc' => Storage::disk('public')->url($img->path),
                        'title' => $img->alt ?: $product->name,
                    ])
                    ->all();

                $urls[] = [
                    'loc' => url('/catalog/'.$categorySlug.'/'.$product->slug),
                    'lastmod' => $product->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                    'images' => $images,
                ];
            });

        // Content pages
        ContentPage::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (ContentPage $page) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/'.$page->slug),
                    'lastmod' => $page->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                    'images' => [],
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'."\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'."\n";
            $xml .= '        <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            $xml .= '        <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            $xml .= '        <priority>'.$url['priority'].'</priority>'."\n";

            foreach ($url['images'] as $image) {
                $xml .= "        <image:image>\n";
                $xml .= '            <image:loc>'.htmlspecialchars($image['loc'], ENT_XML1).'</image:loc>'."\n";

                if (! empty($image['title'])) {
                    $xml .= '            <image:title>'.htmlspecialchars($image['title'], ENT_XML1).'</image:title>'."\n";
                }

                $xml .= "        </image:image>\n";
            }

            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
