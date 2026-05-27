<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Home
        $urls[] = [
            'loc' => url('/'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // Catalog index
        $urls[] = [
            'loc' => url('/catalog'),
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];

        // Categories
        Category::query()
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/catalog/'.$category->slug),
                    'lastmod' => $category->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.8',
                ];
            });

        // Products (active only)
        Product::query()
            ->where('status', 'active')
            ->with('primaryCategory:id,slug')
            ->orderBy('id')
            ->get(['id', 'slug', 'primary_category_id', 'updated_at'])
            ->each(function (Product $product) use (&$urls): void {
                $categorySlug = $product->primaryCategory?->slug;

                if (! $categorySlug) {
                    return;
                }

                $urls[] = [
                    'loc' => url('/catalog/'.$categorySlug.'/'.$product->slug),
                    'lastmod' => $product->updated_at?->toAtomString() ?? now()->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
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
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'."\n";
            $xml .= '        <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            $xml .= '        <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            $xml .= '        <priority>'.$url['priority'].'</priority>'."\n";
            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
