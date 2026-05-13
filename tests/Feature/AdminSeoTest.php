<?php

namespace Tests\Feature;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\SeoRedirect;
use App\Models\SeoTemplate;
use App\Models\User;
use App\Services\Seo\SeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(public_path('sitemap.xml'));

        parent::tearDown();
    }

    public function test_admin_can_open_seo_overview_with_audit_summary(): void
    {
        $user = User::factory()->create();

        Product::query()->create([
            'primary_category_id' => null,
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'status' => 'active',
            'price_cents' => 129900,
        ]);
        Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        ContentPage::query()->create([
            'title' => 'Доставка',
            'slug' => 'dostavka',
            'content' => '<p>Оплата і доставка</p>',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('admin.seo.show', 'overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Seo/Index', false)
                ->where('section', 'overview')
                ->has('tabs', 7)
                ->where('audit.summary.products_total', 1)
                ->where('audit.summary.categories_total', 1)
                ->where('audit.summary.pages_total', 1)
                ->where('audit.issues.0.key', 'products_without_meta_title')
                ->where('audit.issues.0.count', 1)
            );
    }

    public function test_admin_can_update_meta_templates_and_resolver_uses_fallback(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Капці Fluffy',
            'slug' => 'kaptsi-fluffy',
            'status' => 'active',
            'price_cents' => 159900,
        ]);
        $product->categories()->attach($category->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->put(route('admin.seo.meta.update'), [
                'settings' => [
                    'default_title' => 'DomMood',
                    'default_meta_description' => 'Магазин DomMood',
                    'default_favicon_url' => '/favicon.ico',
                    'default_og_image_url' => '/og.jpg',
                    'default_canonical_url' => url('/'),
                ],
                'templates' => [
                    [
                        'entity_type' => 'product',
                        'field' => 'title',
                        'template' => '{product_name} купити в Україні | DomMood',
                        'is_active' => true,
                    ],
                    [
                        'entity_type' => 'product',
                        'field' => 'meta_description',
                        'template' => 'Купити {product_name} у категорії {category_name}.',
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.seo.show', 'meta'));

        $this->assertDatabaseHas('seo_templates', [
            'entity_type' => 'product',
            'field' => 'title',
            'template' => '{product_name} купити в Україні | DomMood',
        ]);

        $meta = app(SeoResolver::class)->metaForProduct($product->fresh());

        $this->assertSame('Капці Fluffy купити в Україні | DomMood', $meta['title']);
        $this->assertSame('Купити Капці Fluffy у категорії Жіночі капці.', $meta['meta_description']);

        $product->update(['meta_title' => 'Ручний title']);

        $this->assertSame('Ручний title', app(SeoResolver::class)->metaForProduct($product->fresh())['title']);
    }

    public function test_admin_can_create_redirect_and_cycles_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.seo.redirects.store'), [
                'source_path' => 'old-url',
                'target_url' => '/new-url',
                'status_code' => 301,
                'preserve_query' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.seo.show', 'redirects'));

        $this->assertDatabaseHas('seo_redirects', [
            'source_path' => '/old-url',
            'target_url' => '/new-url',
            'status_code' => 301,
        ]);

        $this->actingAs($user)
            ->post(route('admin.seo.redirects.store'), [
                'source_path' => '/old-url/',
                'target_url' => '/another-url',
                'status_code' => 301,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('source_path');

        $this->actingAs($user)
            ->post(route('admin.seo.redirects.store'), [
                'source_path' => '/new-url',
                'target_url' => '/old-url',
                'status_code' => 301,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('target_url');
    }

    public function test_admin_can_regenerate_sitemap(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Каталог',
            'slug' => 'catalog',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Капці',
            'slug' => 'kaptsi',
            'status' => 'active',
            'price_cents' => 90000,
        ]);
        $product->categories()->attach($category->id, [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        ContentPage::query()->create([
            'title' => 'Про нас',
            'slug' => 'pro-nas',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('admin.seo.sitemap.regenerate'))
            ->assertRedirect(route('admin.seo.show', 'sitemap'));

        $this->assertDatabaseHas('sitemap_runs', [
            'status' => 'completed',
            'product_urls_count' => 1,
            'category_urls_count' => 1,
            'page_urls_count' => 1,
            'total_urls_count' => 3,
            'file_path' => 'sitemap.xml',
        ]);
        $this->assertTrue(File::exists(public_path('sitemap.xml')));
    }

    public function test_admin_can_create_filter_seo_page(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        $material = ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
        ]);
        $size = ProductAttribute::query()->create([
            'name' => 'Розмір',
            'slug' => 'size',
            'type' => ProductAttribute::TYPE_MULTI_SELECT,
            'is_filterable' => true,
        ]);
        $fur = AttributeValue::query()->create([
            'attribute_id' => $material->id,
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);
        $size36 = AttributeValue::query()->create([
            'attribute_id' => $size->id,
            'value' => '36',
            'slug' => '36',
        ]);
        $size37 = AttributeValue::query()->create([
            'attribute_id' => $size->id,
            'value' => '37',
            'slug' => '37',
        ]);

        $this->actingAs($user)
            ->post(route('admin.seo.filter-pages.store'), [
                'category_id' => $category->id,
                'slug' => 'zhinochi-kaptsi-z-hutrom',
                'filters' => [
                    [
                        'attribute_id' => $material->id,
                        'value_ids' => [$fur->id],
                    ],
                    [
                        'attribute_id' => $size->id,
                        'value_ids' => [$size36->id, $size37->id],
                    ],
                ],
                'h1' => 'Жіночі капці з хутром',
                'meta_title' => 'Жіночі капці з хутром купити | DomMood',
                'meta_description' => 'Добірка жіночих капців з хутром.',
                'canonical_url' => '/catalog/zhinochi-kaptsi/filter/material/shtuchne-hutro/size/36/size/37',
                'seo_text' => 'SEO текст для фільтра.',
                'is_indexable' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.seo.show', 'filter-seo'));

        $page = FilterSeoPage::query()->firstOrFail();

        $this->assertSame(['material' => ['shtuchne-hutro'], 'size' => ['36', '37']], $page->filters);
        $this->assertTrue($page->is_indexable);
    }

    public function test_filter_seo_rejects_value_from_another_attribute(): void
    {
        $user = User::factory()->create();
        $material = ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
        ]);
        $size = ProductAttribute::query()->create([
            'name' => 'Розмір',
            'slug' => 'size',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
        ]);
        $size36 = AttributeValue::query()->create([
            'attribute_id' => $size->id,
            'value' => '36',
            'slug' => '36',
        ]);

        $this->actingAs($user)
            ->post(route('admin.seo.filter-pages.store'), [
                'slug' => 'wrong-filter',
                'filters' => [
                    [
                        'attribute_id' => $material->id,
                        'value_ids' => [$size36->id],
                    ],
                ],
                'h1' => 'Некоректний фільтр',
                'is_indexable' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('filters.0.value_ids');
    }

    public function test_manager_cannot_open_seo_by_default(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.seo.show', 'overview'))
            ->assertForbidden();
    }
}
