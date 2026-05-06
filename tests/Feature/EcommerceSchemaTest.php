<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EcommerceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_schema_contains_core_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('categories', [
            'parent_id',
            'name',
            'slug',
            'is_active',
            'meta_title',
            'meta_description',
            'seo_text',
        ]));

        $this->assertTrue(Schema::hasColumns('products', [
            'primary_category_id',
            'brand_id',
            'name',
            'slug',
            'sku',
            'status',
            'price_cents',
            'old_price_cents',
            'stock_status',
            'published_at',
        ]));

        $this->assertTrue(Schema::hasColumns('product_variants', [
            'product_id',
            'sku',
            'price_cents',
            'stock_quantity',
            'reserved_quantity',
            'is_active',
        ]));
    }

    public function test_order_schema_keeps_customer_and_product_snapshots(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', [
            'order_number',
            'customer_id',
            'status',
            'payment_status',
            'customer_name',
            'customer_phone',
            'subtotal_cents',
            'discount_total_cents',
            'delivery_price_cents',
            'total_cents',
            'utm_source',
            'utm_campaign',
        ]));

        $this->assertTrue(Schema::hasColumns('order_items', [
            'order_id',
            'product_id',
            'product_variant_id',
            'product_name',
            'variant_name',
            'sku',
            'price_cents',
            'quantity',
            'total_cents',
            'product_snapshot',
        ]));
    }

    public function test_marketing_schema_supports_seo_promos_and_tracking(): void
    {
        $this->assertTrue(Schema::hasTable('content_pages'));
        $this->assertTrue(Schema::hasTable('banners'));
        $this->assertTrue(Schema::hasTable('promocodes'));
        $this->assertTrue(Schema::hasTable('tracking_settings'));
    }
}
