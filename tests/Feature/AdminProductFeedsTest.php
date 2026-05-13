<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeedConfig;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductFeedsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_product_feeds_index(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct();

        $this->actingAs($user)
            ->get(route('admin.product-feeds.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/ProductFeeds/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->has('feedUrls.google_merchant.url')
            );
    }

    public function test_admin_can_update_product_feed_config(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct();

        $response = $this->actingAs($user)->put(route('admin.product-feeds.update', $product), [
            'channels' => [
                ProductFeedService::CHANNEL_GOOGLE => [
                    'is_enabled' => true,
                    'brand' => 'DomMood',
                    'google_product_category' => '187',
                    'google_gender' => 'female',
                    'google_age_group' => 'adult',
                    'google_size_system' => 'EU',
                    'google_size_types' => ['regular'],
                    'google_product_highlights' => "Мʼякий верх\nРезинова підошва",
                    'google_product_details' => "Матеріал | Штучне хутро\nПідошва | Резина",
                    'custom_label_0' => 'test',
                ],
                ProductFeedService::CHANNEL_META => [
                    'is_enabled' => true,
                    'brand' => 'DomMood',
                ],
                ProductFeedService::CHANNEL_TIKTOK => [
                    'is_enabled' => false,
                    'brand' => 'DomMood',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin.product-feeds.edit', $product))
            ->assertSessionHas('success', 'Налаштування Product Feeds оновлено');

        $this->assertDatabaseHas('product_feed_configs', [
            'product_id' => $product->id,
            'channel' => ProductFeedService::CHANNEL_GOOGLE,
            'is_enabled' => true,
            'brand' => 'DomMood',
            'google_gender' => 'female',
        ]);

        $config = ProductFeedConfig::query()
            ->where('product_id', $product->id)
            ->where('channel', ProductFeedService::CHANNEL_GOOGLE)
            ->firstOrFail();

        $this->assertSame(['regular'], $config->google_size_types);
        $this->assertSame('Резинова підошва', $config->google_product_highlights[1]);
        $this->assertSame('Підошва', $config->google_product_details[1]['attribute_name']);
    }

    public function test_google_merchant_feed_matches_dream_xml_structure(): void
    {
        Storage::fake('public');

        $product = $this->createProduct();

        ProductFeedConfig::query()->create([
            'product_id' => $product->id,
            'channel' => ProductFeedService::CHANNEL_GOOGLE,
            'is_enabled' => true,
            'brand' => 'DomMood',
            'google_product_category' => '187',
            'google_gender' => 'female',
            'google_age_group' => 'adult',
            'google_size_system' => 'EU',
            'google_size_types' => ['regular'],
            'google_material' => 'Штучне хутро',
            'google_pattern' => 'Однотонні',
            'google_product_highlights' => ['Мʼякий верх'],
            'google_product_details' => [
                [
                    'section_name' => null,
                    'attribute_name' => 'Підошва',
                    'attribute_value' => 'Резина',
                ],
            ],
            'custom_label_0' => 'new',
        ]);

        $response = $this->get(route('feeds.google-merchant'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();

        $this->assertStringContainsString('<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">', $xml);
        $this->assertStringContainsString('<title>DomMood</title>', $xml);
        $this->assertStringContainsString('<g:id>DM-001-36</g:id>', $xml);
        $this->assertStringContainsString('<g:image_link>'.url('/storage/products/1/main.jpg').'</g:image_link>', $xml);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $xml);
        $this->assertStringContainsString('<g:price>999.00 UAH</g:price>', $xml);
        $this->assertStringContainsString('<g:sale_price>799.00 UAH</g:sale_price>', $xml);
        $this->assertStringContainsString('<g:mpn>DM-001-36</g:mpn>', $xml);
        $this->assertStringContainsString('<g:item_group_id>DM-001</g:item_group_id>', $xml);
        $this->assertStringContainsString('<g:gender>female</g:gender>', $xml);
        $this->assertStringContainsString('<g:size>36-37</g:size>', $xml);
        $this->assertStringContainsString('<g:product_detail>', $xml);
        $this->assertStringContainsString('<g:custom_label_0>new</g:custom_label_0>', $xml);
    }

    private function createProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Літні тапочки',
            'slug' => 'litni-tapochky',
        ]);

        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Жіночі тапочки для вулиці',
            'slug' => 'zhinochi-tapochky-dlia-vulytsi',
            'sku' => 'DM-001',
            'short_description' => 'Малинові тапочки на резиновій підошві.',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 79900,
            'old_price_cents' => 99900,
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
        ]);

        $product->categories()->attach($category->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'DM-001-36',
            'size' => '36-37',
            'price_cents' => 79900,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => "products/{$product->id}/main.jpg",
            'is_main' => true,
        ]);

        return $product;
    }
}
