<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_categories_attributes_and_image(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $attribute = ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
        ]);
        $value = $attribute->values()->create([
            'value' => 'Еко-шкіра',
            'slug' => 'eko-shkira',
        ]);

        $response = $this->actingAs($user)->post(route('admin.products.store'), [
            'primary_category_id' => $category->id,
            'category_ids' => [$category->id],
            'name' => 'Домашні капці Halluci',
            'slug' => '',
            'sku' => 'DM-HAL-001',
            'short_description' => 'Мʼякі домашні капці.',
            'description' => 'Детальний опис товару.',
            'status' => Product::STATUS_ACTIVE,
            'price' => '799.90',
            'old_price' => '999.00',
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
            'is_new' => true,
            'attribute_value_ids' => [$value->id],
            'variants' => [
                [
                    'sku' => 'DM-HAL-001-36',
                    'size' => '36-37',
                    'price' => '799.90',
                    'stock_quantity' => 4,
                    'is_active' => true,
                ],
            ],
            'images' => [
                UploadedFile::fake()->image('slippers.jpg', 900, 700),
                UploadedFile::fake()->image('slippers-second.jpg', 900, 700),
            ],
            'new_image_keys' => ['first', 'second'],
            'image_order' => ['n:second', 'n:first'],
            'meta_title' => 'Домашні капці Halluci купити',
            'meta_description' => 'Домашні капці DomMood з доставкою по Україні.',
        ]);

        $response
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Товар створено');

        $product = Product::query()->where('sku', 'DM-HAL-001')->firstOrFail();

        $this->assertSame('domashni-kaptsi-halluci', $product->slug);
        $this->assertSame(79990, $product->price_cents);
        $this->assertSame(99900, $product->old_price_cents);
        $this->assertTrue($product->is_new);
        $this->assertNotNull($product->published_at);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $category->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $value->id,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'DM-HAL-001-36',
            'size' => '36-37',
            'price_cents' => 79990,
            'stock_quantity' => 4,
            'is_active' => true,
        ]);

        $image = $product->images()->where('is_main', true)->firstOrFail();

        $this->assertTrue($image->is_main);
        $this->assertStringStartsWith("products/{$product->id}/domashni-kaptsi-halluci-dommood-", $image->path);
        $this->assertStringEndsWith('-2.webp', $image->path);
        Storage::disk('public')->assertExists($image->path);

        $cardPath = "products/{$product->id}/domashni-kaptsi-halluci-card.webp";
        $thumbPath = "products/{$product->id}/domashni-kaptsi-halluci-thumb.webp";
        $swatchPath = "products/{$product->id}/domashni-kaptsi-halluci-swatch.webp";

        Storage::disk('public')->assertExists($cardPath);
        Storage::disk('public')->assertExists($thumbPath);
        Storage::disk('public')->assertExists($swatchPath);

        [$cardWidth, $cardHeight] = getimagesize(Storage::disk('public')->path($cardPath));
        [$thumbWidth, $thumbHeight] = getimagesize(Storage::disk('public')->path($thumbPath));
        [$swatchWidth, $swatchHeight] = getimagesize(Storage::disk('public')->path($swatchPath));

        $this->assertLessThanOrEqual(600, $cardWidth);
        $this->assertLessThanOrEqual(600, $cardHeight);
        $this->assertLessThanOrEqual(320, $thumbWidth);
        $this->assertLessThanOrEqual(320, $thumbHeight);
        $this->assertLessThanOrEqual(180, $swatchWidth);
        $this->assertLessThanOrEqual(180, $swatchHeight);
    }

    public function test_admin_can_update_product_and_delete_existing_image(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();
        $oldCategory = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $newCategory = Category::query()->create([
            'name' => 'Піжами',
            'slug' => 'pizhamy',
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $oldCategory->id,
            'name' => 'Старий товар',
            'slug' => 'staryi-tovar',
            'sku' => 'OLD-SKU',
            'price_cents' => 50000,
        ]);
        $product->categories()->attach($oldCategory->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $oldPath = "products/{$product->id}/old.jpg";
        $oldCardPath = "products/{$product->id}/staryi-tovar-card.webp";
        $oldThumbPath = "products/{$product->id}/staryi-tovar-thumb.webp";
        $oldSwatchPath = "products/{$product->id}/staryi-tovar-swatch.webp";
        Storage::disk('public')->put($oldPath, 'image');
        Storage::disk('public')->put($oldCardPath, 'old card');
        Storage::disk('public')->put($oldThumbPath, 'old thumb');
        Storage::disk('public')->put($oldSwatchPath, 'old swatch');
        $image = $product->images()->create([
            'disk' => 'public',
            'path' => $oldPath,
            'is_main' => true,
        ]);

        $response = $this->actingAs($user)->post(route('admin.products.update', $product), [
            '_method' => 'put',
            'primary_category_id' => $newCategory->id,
            'category_ids' => [$newCategory->id],
            'name' => 'Новий товар',
            'slug' => 'novyi-tovar',
            'sku' => 'NEW-SKU',
            'status' => Product::STATUS_DRAFT,
            'price' => '650.00',
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_PREORDER,
            'delete_image_ids' => [$image->id],
            'images' => [
                UploadedFile::fake()->image('new-product.jpg', 900, 700),
            ],
        ]);

        $response
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Товар оновлено');

        $product->refresh();

        $this->assertSame('novyi-tovar', $product->slug);
        $this->assertSame('NEW-SKU', $product->sku);
        $this->assertSame(65000, $product->price_cents);
        $this->assertSame($newCategory->id, $product->primary_category_id);
        $this->assertDatabaseMissing('category_product', [
            'product_id' => $product->id,
            'category_id' => $oldCategory->id,
        ]);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $newCategory->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseMissing('product_images', [
            'id' => $image->id,
        ]);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertMissing($oldCardPath);
        Storage::disk('public')->assertMissing($oldThumbPath);
        Storage::disk('public')->assertMissing($oldSwatchPath);

        $newImage = $product->images()->firstOrFail();

        $this->assertTrue($newImage->is_main);
        $this->assertStringStartsWith("products/{$product->id}/novyi-tovar-dommood-", $newImage->path);
        $this->assertStringEndsWith('.webp', $newImage->path);
        Storage::disk('public')->assertExists($newImage->path);
        Storage::disk('public')->assertExists("products/{$product->id}/novyi-tovar-card.webp");
        Storage::disk('public')->assertExists("products/{$product->id}/novyi-tovar-thumb.webp");
        Storage::disk('public')->assertExists("products/{$product->id}/novyi-tovar-swatch.webp");
    }

    public function test_products_index_exposes_main_image_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'price_cents' => 79900,
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
        $path = "products/{$product->id}/main.jpg";
        Storage::disk('public')->put($path, 'image');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $path,
            'is_main' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/Products/Index', false)
                ->where('products.data.0.slug', 'domashni-kaptsi')
                ->where('products.data.0.main_image_url', Storage::disk('public')->url($path))
                ->where('products.data.0.categories.0.name', 'Капці')
                ->where('products.data.0.variants.0.size', '36-37')
                ->where('categoryOptions.0.label', 'Капці')
            );
    }

    public function test_products_index_can_filter_by_category(): void
    {
        $user = User::factory()->create();
        $slippers = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $pajamas = Category::query()->create([
            'name' => 'Піжами',
            'slug' => 'pizhamy',
        ]);

        $firstProduct = Product::query()->create([
            'primary_category_id' => $slippers->id,
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'price_cents' => 79900,
        ]);
        $firstProduct->categories()->attach($slippers->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $secondProduct = Product::query()->create([
            'primary_category_id' => $pajamas->id,
            'name' => 'Піжама',
            'slug' => 'pizhama',
            'price_cents' => 129900,
        ]);
        $secondProduct->categories()->attach($pajamas->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.products.index', ['category_id' => $slippers->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/Products/Index', false)
                ->where('products.data.0.slug', 'domashni-kaptsi')
                ->has('products.data', 1)
            );
    }

    public function test_admin_can_quick_update_product_from_index(): void
    {
        $user = User::factory()->create();
        $oldCategory = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $newCategory = Category::query()->create([
            'name' => 'Тапочки',
            'slug' => 'tapochky',
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $oldCategory->id,
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'status' => Product::STATUS_DRAFT,
            'price_cents' => 79900,
        ]);
        $product->categories()->attach($oldCategory->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->patchJson(route('admin.products.quick', $product), [
            'price' => '899.50',
            'old_price' => '1099.00',
            'status' => Product::STATUS_ACTIVE,
            'stock_status' => Product::STOCK_PREORDER,
            'category_ids_present' => true,
            'category_ids' => [$newCategory->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Товар оновлено')
            ->assertJsonPath('product.price', '899.50')
            ->assertJsonPath('product.categories.0.name', 'Тапочки');

        $product->refresh();

        $this->assertSame(89950, $product->price_cents);
        $this->assertSame(109900, $product->old_price_cents);
        $this->assertSame(Product::STATUS_ACTIVE, $product->status);
        $this->assertSame(Product::STOCK_PREORDER, $product->stock_status);
        $this->assertSame($newCategory->id, $product->primary_category_id);
        $this->assertDatabaseMissing('category_product', [
            'product_id' => $product->id,
            'category_id' => $oldCategory->id,
        ]);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $newCategory->id,
            'is_primary' => true,
        ]);
    }

    public function test_admin_can_quick_update_and_delete_variant_from_index(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'price_cents' => 79900,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'DM-001-36',
            'size' => '36-37',
            'price_cents' => 79900,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.products.variants.update', [$product, $variant]), [
                'size' => '38-39',
                'price' => '849.00',
                'stock_quantity' => 8,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Варіацію оновлено')
            ->assertJsonPath('variant.size', '38-39')
            ->assertJsonPath('variant.price', '849.00');

        $variant->refresh();

        $this->assertSame('38-39', $variant->size);
        $this->assertSame(84900, $variant->price_cents);
        $this->assertSame(8, $variant->stock_quantity);
        $this->assertFalse($variant->is_active);

        $this->actingAs($user)
            ->deleteJson(route('admin.products.variants.destroy', [$product, $variant]))
            ->assertOk()
            ->assertJsonPath('message', 'Варіацію видалено')
            ->assertJsonPath('variants_count', 0);

        $this->assertSoftDeleted('product_variants', [
            'id' => $variant->id,
        ]);
    }

    public function test_admin_cannot_create_product_without_primary_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.products.store'), [
            'name' => 'Без категорії',
            'status' => Product::STATUS_DRAFT,
            'price' => '100.00',
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
        ]);

        $response->assertSessionHasErrors('primary_category_id');
    }
}
