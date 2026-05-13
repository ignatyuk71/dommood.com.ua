<?php

use App\Models\Product;
use App\Services\Media\ProductImageOptimizer;
use App\Support\Catalog\CatalogSlug;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('product-images:refresh-versions {--product_id= : Оновити версії тільки для одного товару}', function () {
    $query = Product::query()
        ->with(['images' => fn ($query) => $query
            ->orderByDesc('is_main')
            ->orderBy('sort_order')
            ->orderBy('id')]);

    $productId = (int) $this->option('product_id');

    if ($productId > 0) {
        $query->whereKey($productId);
    }

    $optimizer = app(ProductImageOptimizer::class);
    $processed = 0;
    $updated = 0;

    $query->chunkById(100, function ($products) use ($optimizer, &$processed, &$updated): void {
        foreach ($products as $product) {
            $processed++;

            $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();

            if (! $mainImage) {
                continue;
            }

            $directory = "products/{$product->id}";
            $disk = $mainImage->disk ?: 'public';
            $baseFilename = CatalogSlug::make($product->slug) ?: 'product-'.$product->id;
            $generatedPaths = array_values(array_filter(
                Storage::disk($disk)->files($directory),
                fn (string $path): bool => str_ends_with($path, '-card.webp')
                    || str_ends_with($path, '-thumb.webp')
                    || str_ends_with($path, '-swatch.webp')
            ));

            if ($generatedPaths !== []) {
                Storage::disk($disk)->delete($generatedPaths);
            }

            $paths = $optimizer->storeResponsiveVersions(
                $mainImage->path,
                $directory,
                $baseFilename,
                $disk,
            );

            if ($paths !== []) {
                $updated++;
            }
        }
    });

    $this->info("Оновлено версії зображень: {$updated}/{$processed}");
})->purpose('Regenerate product card, thumb and color swatch WebP images');
