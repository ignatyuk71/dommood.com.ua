<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;

class ProductAvailabilityService
{
    public function gridBadge(Product $product): ?array
    {
        $metrics = $this->variantMetrics($product);
        $stockStatus = $this->calculateResolvedStockStatus($product, $metrics);

        if ($stockStatus === Product::STOCK_OUT_OF_STOCK) {
            return [
                'status' => 'out_of_stock',
                'label' => 'Немає в наявності',
                'tone' => 'muted',
                'is_purchasable' => false,
                'should_dim_image' => true,
                ...$metrics,
            ];
        }

        if ($stockStatus === Product::STOCK_PREORDER) {
            return [
                'status' => 'preorder',
                'label' => 'Передзамовлення',
                'tone' => 'warning',
                'is_purchasable' => true,
                'should_dim_image' => false,
                ...$metrics,
            ];
        }

        return null;
    }

    public function resolvedStockStatus(Product $product): string
    {
        return $this->calculateResolvedStockStatus($product, $this->variantMetrics($product));
    }

    /**
     * Повертає Eloquent агрегати для карток без N+1 запитів.
     */
    public function variantAvailabilityCounts(): array
    {
        return [
            'variants as active_variants_count' => fn ($query) => $query->where('is_active', true),
            'variants as available_variants_count' => fn ($query) => $query
                ->where('is_active', true)
                ->where('stock_quantity', '>', 0),
        ];
    }

    /**
     * Повертає суму доступного залишку для активних варіантів.
     */
    public function variantAvailabilitySums(): array
    {
        return [
            'variants as available_variants_stock' => fn ($query) => $query
                ->where('is_active', true)
                ->where('stock_quantity', '>', 0),
        ];
    }

    private function calculateResolvedStockStatus(Product $product, array $metrics): string
    {
        if ($metrics['active_variants_count'] > 0 && $metrics['available_variants_count'] === 0) {
            return Product::STOCK_OUT_OF_STOCK;
        }

        return $product->stock_status ?: Product::STOCK_IN_STOCK;
    }

    private function variantMetrics(Product $product): array
    {
        if ($product->relationLoaded('variants')) {
            $activeVariants = $product->variants->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active);
            $availableVariants = $activeVariants->filter(fn (ProductVariant $variant): bool => (int) $variant->stock_quantity > 0);

            return [
                'active_variants_count' => $activeVariants->count(),
                'available_variants_count' => $availableVariants->count(),
                'available_stock_quantity' => (int) $availableVariants->sum('stock_quantity'),
            ];
        }

        return [
            'active_variants_count' => (int) ($product->getAttribute('active_variants_count') ?? 0),
            'available_variants_count' => (int) ($product->getAttribute('available_variants_count') ?? 0),
            'available_stock_quantity' => (int) ($product->getAttribute('available_variants_stock') ?? 0),
        ];
    }
}
