<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeedConfig;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ProductFeedService;
use Tests\TestCase;

class ProductFeedGoogleIdentifiersTest extends TestCase
{
    public function test_variant_product_uses_variant_sku_as_mpn_like_dream_feed(): void
    {
        $service = new ProductFeedService;
        $variant = new ProductVariant([
            'sku' => 'DM-001-36',
            'barcode' => '3234567890126',
            'size' => '36-37',
            'price_cents' => 79900,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
        $variant->id = 501;
        $variant->setRelation('images', collect());

        $product = $this->makeProduct(variants: [$variant]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertNull($item['gtin']);
        $this->assertSame('DM-001-36', $item['mpn']);
        $this->assertNull($item['identifier_exists']);
    }

    public function test_product_without_gtin_and_mpn_marks_identifier_exists_as_no(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'sku' => null,
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertNull($item['gtin']);
        $this->assertNull($item['mpn']);
        $this->assertSame('no', $item['identifier_exists']);
    }

    public function test_kids_products_get_inferred_google_audience_when_fields_are_empty(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'name' => 'Дитячі кімнатні капці',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('unisex', $item['google_gender']);
        $this->assertSame('kids', $item['google_age_group']);
    }

    public function test_adult_products_default_to_female_and_adult_for_current_catalog(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'name' => 'Теплі домашні чуні',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('female', $item['google_gender']);
        $this->assertSame('adult', $item['google_age_group']);
    }

    public function test_slippers_default_to_google_taxonomy_id_for_shoes(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'name' => 'Теплі домашні чуні',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('187', $item['google_product_category']);
    }

    public function test_pajamas_get_specific_google_taxonomy_id(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'name' => 'Жіноча сатинова піжама',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('2580', $item['google_product_category']);
    }

    public function test_legacy_text_google_category_is_normalized_to_numeric_id(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct(configOverrides: [
            'google_product_category' => 'Apparel & Accessories > Shoes > Slippers',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('187', $item['google_product_category']);
    }

    public function test_generic_shoes_override_is_replaced_with_pajama_category_when_signals_match(): void
    {
        $service = new ProductFeedService;
        $product = $this->makeProduct([
            'name' => 'Жіноча сатинова піжама',
        ], configOverrides: [
            'google_product_category' => '187',
        ]);

        $item = $service->buildFeedItems($product, ProductFeedService::CHANNEL_GOOGLE)[0];

        $this->assertSame('2580', $item['google_product_category']);
    }

    public function test_google_merchant_view_renders_identifier_tags(): void
    {
        $xml = view('feeds.google_merchant', [
            'items' => collect([
                $this->feedItem([
                    'title' => 'Капці & піжами',
                    'product_type' => 'Pajamas & Loungewear > Women',
                    'google_product_category' => '2580',
                ]),
                $this->feedItem([
                    'id' => 'DM-001-36',
                    'mpn' => 'DM-001-36',
                    'google_product_category' => '187',
                ]),
                $this->feedItem([
                    'id' => 'custom-no-id',
                    'identifier_exists' => 'no',
                    'google_product_category' => '187',
                ]),
            ]),
        ])->render();

        $this->assertStringContainsString('<title>Капці &amp; піжами</title>', $xml);
        $this->assertStringContainsString('<g:product_type>Pajamas &amp; Loungewear &gt; Women</g:product_type>', $xml);
        $this->assertStringContainsString('<g:mpn>DM-001-36</g:mpn>', $xml);
        $this->assertStringContainsString('<g:identifier_exists>no</g:identifier_exists>', $xml);
        $this->assertStringContainsString('<g:google_product_category>2580</g:google_product_category>', $xml);
        $this->assertStringContainsString('<g:google_product_category>187</g:google_product_category>', $xml);
    }

    private function makeProduct(
        array $overrides = [],
        array $variants = [],
        array $configOverrides = []
    ): Product {
        $product = new Product(array_merge([
            'status' => Product::STATUS_ACTIVE,
            'sku' => 'DM-001',
            'name' => 'Дитячі кімнатні капці',
            'slug' => 'dytyachi-kimnatni-kaptsi',
            'short_description' => 'Мʼякі домашні капці для дітей.',
            'price_cents' => 99900,
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
        ], $overrides));
        $product->id = 101;

        $category = new Category([
            'name' => 'Кімнатні капці',
            'slug' => 'kimnatni-kaptsi',
            'sort_order' => 1,
        ]);
        $category->id = 10;

        $config = new ProductFeedConfig(array_merge([
            'channel' => ProductFeedService::CHANNEL_GOOGLE,
            'is_enabled' => true,
            'brand' => 'DomMood',
        ], $configOverrides));

        $image = new ProductImage([
            'disk' => 'public',
            'path' => 'products/101/product.jpg',
            'sort_order' => 1,
            'is_main' => true,
        ]);

        $product->setRelation('primaryCategory', $category);
        $product->setRelation('categories', collect([$category]));
        $product->setRelation('images', collect([$image]));
        $product->setRelation('feedConfigs', collect([$config]));
        $product->setRelation('variants', collect($variants));

        return $product;
    }

    private function feedItem(array $overrides = []): array
    {
        return array_merge([
            'id' => 'product-101',
            'title' => 'Дитячі кімнатні капці',
            'description' => 'Мʼякі домашні капці для дітей.',
            'link' => 'https://example.com/product',
            'image_link' => 'https://example.com/product.jpg',
            'additional_image_links' => [],
            'availability' => 'in_stock',
            'price' => 999,
            'sale_price' => null,
            'currency' => 'UAH',
            'condition' => 'new',
            'brand' => 'DomMood',
            'gtin' => null,
            'mpn' => null,
            'identifier_exists' => null,
            'item_group_id' => 'group-101',
            'google_gender' => '',
            'google_age_group' => '',
            'size' => '',
            'google_size_system' => '',
            'google_size_types' => [],
            'color' => '',
            'google_material' => '',
            'google_pattern' => '',
            'product_type' => '',
            'google_product_category' => '',
            'google_is_bundle' => false,
            'google_product_highlights' => [],
            'google_product_details' => [],
            'custom_labels' => [],
        ], $overrides);
    }
}
