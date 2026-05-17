<?php

namespace Tests\Feature;

use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductColorGroup;
use App\Models\Review;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_category_page_opens_from_storefront_links(): void
    {
        $category = Category::query()->create([
            'name' => 'Капці Halluci',
            'slug' => 'kaptsi-halluci',
            'meta_title' => 'Капці Halluci купити | DomMood',
            'meta_description' => 'Оригінальні капці Halluci з доставкою по Україні.',
            'is_active' => true,
        ]);

        Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Домашні капці Halluci',
            'slug' => 'domashni-kaptsi-halluci',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 79900,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog/kaptsi-halluci')
            ->assertOk()
            ->assertViewIs('storefront.catalog.index')
            ->assertSee('Капці Halluci')
            ->assertSee('Домашні капці Halluci')
            ->assertDontSee('У кошик')
            ->assertDontSee('storefront-product-card__cart', false)
            ->assertSee('<title>Капці Halluci купити | DomMood</title>', false)
            ->assertSee('<meta name="description" content="Оригінальні капці Halluci з доставкою по Україні.">', false);
    }

    public function test_catalog_product_card_uses_variant_stock_quantity(): void
    {
        $category = Category::query()->create([
            'name' => 'Капці Halluci',
            'slug' => 'kaptsi-halluci',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Капці без залишків',
            'slug' => 'kaptsi-bez-zalyshkiv',
            'status' => Product::STATUS_ACTIVE,
            'stock_status' => Product::STOCK_IN_STOCK,
            'price_cents' => 79900,
            'published_at' => now()->subMinute(),
        ]);
        $product->variants()->create([
            'sku' => 'DM-ZERO-36',
            'size' => '36-37',
            'price_cents' => 79900,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        $this->get('/catalog/kaptsi-halluci')
            ->assertOk()
            ->assertSee('Капці без залишків')
            ->assertSee('Немає в наявності')
            ->assertDontSee('>В наявності<', false);
    }

    public function test_featured_promo_product_template_opens_without_catalog_record(): void
    {
        $this->get('/catalog/vulychni-tapochky/zhinochi-tapochky-dlia-vulytsi-siro-blakytnyi')
            ->assertOk()
            ->assertViewIs('storefront.catalog.show')
            ->assertSee('Жіночі тапочки для вулиці, сіро-блакитний')
            ->assertSee('450 грн')
            ->assertDontSee('product-detail-accordion', false)
            ->assertDontSee('Чи підходять ці тапочки для вулиці?')
            ->assertDontSee('"@type":"FAQPage"', false);
    }

    public function test_product_page_renders_detail_tabs_under_gallery(): void
    {
        app(SiteSettingsService::class)->set('payment_delivery', [
            'free_shipping_threshold' => '1400.00',
        ]);

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
        $fur = AttributeValue::query()->create([
            'attribute_id' => $material->id,
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Пухнасті рожеві капці',
            'slug' => 'pukhnasti-rozhevi-kaptsi',
            'short_description' => 'Короткий опис капців.',
            'description' => 'Детальний опис посадки, матеріалу та щоденного сценарію використання.',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);

        $product->attributeValues()->attach($fur->id, ['attribute_id' => $material->id]);
        Review::query()->create([
            'product_id' => $product->id,
            'author_name' => 'Олена',
            'rating' => 5,
            'title' => 'Дуже мʼякі',
            'body' => 'Розмір підійшов, доставка швидка.',
            'status' => Review::STATUS_APPROVED,
            'is_verified_buyer' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog/zhinochi-kaptsi/pukhnasti-rozhevi-kaptsi')
            ->assertOk()
            ->assertViewIs('storefront.catalog.show')
            ->assertSee('product-details-tabs', false)
            ->assertSee('Опис')
            ->assertSee('Доставка і повернення')
            ->assertSee('Для всіх замовлень від 1 400 грн')
            ->assertSee('Діє для замовлень від 1 400 грн.')
            ->assertSee('Як оформити обмін або повернення')
            ->assertSee('Відгуки(1)')
            ->assertSee('Характеристики')
            ->assertSee('Нещодавно переглянуті')
            ->assertSee('data-recently-viewed-track', false)
            ->assertDontSee('З цим товаром купують')
            ->assertSee('Детальний опис посадки, матеріалу та щоденного сценарію використання.')
            ->assertSee('Штучне хутро')
            ->assertSee('5.0')
            ->assertSee('Залишити відгук')
            ->assertSee('Розмір підійшов, доставка швидка.');
    }

    public function test_product_characteristics_do_not_render_color_group_as_attribute(): void
    {
        $category = Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        $colorGroup = ProductColorGroup::query()->create([
            'name' => 'Група рожевих відтінків',
            'code' => 'pink-family',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'color_group_id' => $colorGroup->id,
            'name' => 'Капці з кольорової групи',
            'slug' => 'kaptsi-z-kolorovoi-hrupy',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);

        Product::query()->create([
            'primary_category_id' => $category->id,
            'color_group_id' => $colorGroup->id,
            'name' => 'Інший колір групи',
            'slug' => 'inshyi-kolir-hrupy',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog/zhinochi-kaptsi/kaptsi-z-kolorovoi-hrupy')
            ->assertOk()
            ->assertViewIs('storefront.catalog.show')
            ->assertSee('Інший колір групи')
            ->assertDontSee('Група рожевих відтінків');
    }

    public function test_catalog_category_accepts_products_attached_as_secondary_category(): void
    {
        $primaryCategory = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
            'is_active' => true,
        ]);
        $outdoorCategory = Category::query()->create([
            'name' => 'Жіночі капці для вулиці',
            'slug' => 'zhinochi-kaptsi-dlia-vulytsi',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $primaryCategory->id,
            'name' => 'Жіночі капці на гумовій підошві',
            'slug' => 'zhinochi-kaptsi-na-humovii-pidoshvi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);
        $product->categories()->attach($outdoorCategory->id);

        $this->get('/catalog/zhinochi-kaptsi-dlia-vulytsi')
            ->assertOk()
            ->assertSee('Жіночі капці на гумовій підошві');

        $this->get('/catalog/zhinochi-kaptsi-dlia-vulytsi/zhinochi-kaptsi-na-humovii-pidoshvi')
            ->assertOk()
            ->assertViewIs('storefront.catalog.show')
            ->assertSee('Жіночі капці на гумовій підошві');
    }

    public function test_catalog_filters_products_by_price_and_multiple_categories(): void
    {
        $homeCategory = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);
        $summerCategory = Category::query()->create([
            'name' => 'Літні капці',
            'slug' => 'litni-kaptsi',
            'is_active' => true,
        ]);
        $streetCategory = Category::query()->create([
            'name' => 'Вуличні капці',
            'slug' => 'vulychni-kaptsi',
            'is_active' => true,
        ]);

        Product::query()->create([
            'primary_category_id' => $homeCategory->id,
            'name' => 'Мʼякі домашні капці',
            'slug' => 'miaki-domashni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 65000,
            'published_at' => now()->subMinute(),
        ]);
        Product::query()->create([
            'primary_category_id' => $summerCategory->id,
            'name' => 'Легкі літні капці',
            'slug' => 'lehki-litni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 85000,
            'published_at' => now()->subMinute(),
        ]);
        Product::query()->create([
            'primary_category_id' => $homeCategory->id,
            'name' => 'Преміальні домашні капці',
            'slug' => 'premialni-domashni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 120000,
            'published_at' => now()->subMinute(),
        ]);
        Product::query()->create([
            'primary_category_id' => $streetCategory->id,
            'name' => 'Гумові капці для двору',
            'slug' => 'humovi-kaptsi-dlia-dvoru',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 70000,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog?'.http_build_query([
            'categories' => ['domashni-kaptsi', 'litni-kaptsi'],
            'price_from' => 600,
            'price_to' => 900,
        ]))
            ->assertOk()
            ->assertSee('Ціна')
            ->assertSee('Категорії')
            ->assertSee('Мʼякі домашні капці')
            ->assertSee('Легкі літні капці')
            ->assertDontSee('Преміальні домашні капці')
            ->assertDontSee('Гумові капці для двору')
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_catalog_category_filters_products_by_bound_attribute(): void
    {
        $category = Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        $color = ProductAttribute::query()->create([
            'name' => 'Колір',
            'slug' => 'kolir',
            'type' => ProductAttribute::TYPE_COLOR,
            'is_filterable' => true,
        ]);
        $red = AttributeValue::query()->create([
            'attribute_id' => $color->id,
            'value' => 'Червоний',
            'slug' => 'chervonyi',
        ]);
        $blue = AttributeValue::query()->create([
            'attribute_id' => $color->id,
            'value' => 'Синій',
            'slug' => 'synii',
        ]);
        $category->filterAttributes()->attach($color->id, [
            'is_active' => true,
            'display_type' => 'color',
            'sort_order' => 0,
        ]);
        $redProduct = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Червоні капці',
            'slug' => 'chervoni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 79900,
            'published_at' => now()->subMinute(),
        ]);
        $blueProduct = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Сині капці',
            'slug' => 'syni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);
        $redProduct->attributeValues()->attach($red->id, ['attribute_id' => $color->id]);
        $blueProduct->attributeValues()->attach($blue->id, ['attribute_id' => $color->id]);

        $this->get('/catalog/zhinochi-kaptsi/filter/kolir/chervonyi')
            ->assertOk()
            ->assertSee('Червоні капці')
            ->assertDontSee('Сині капці')
            ->assertSee('Колір')
            ->assertSee('Червоний')
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_catalog_filter_page_uses_indexable_filter_seo_meta(): void
    {
        $category = Category::query()->create([
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
        ]);
        $color = ProductAttribute::query()->create([
            'name' => 'Колір',
            'slug' => 'kolir',
            'type' => ProductAttribute::TYPE_COLOR,
            'is_filterable' => true,
        ]);
        $red = AttributeValue::query()->create([
            'attribute_id' => $color->id,
            'value' => 'Червоний',
            'slug' => 'chervonyi',
        ]);
        $category->filterAttributes()->attach($color->id, [
            'is_active' => true,
            'display_type' => 'color',
            'sort_order' => 0,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Червоні капці',
            'slug' => 'chervoni-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 79900,
            'published_at' => now()->subMinute(),
        ]);
        $product->attributeValues()->attach($red->id, ['attribute_id' => $color->id]);
        FilterSeoPage::query()->create([
            'category_id' => $category->id,
            'slug' => 'chervoni-kaptsi',
            'filters' => ['kolir' => ['chervonyi']],
            'h1' => 'Червоні жіночі капці',
            'meta_title' => 'Червоні жіночі капці купити | DomMood',
            'meta_description' => 'Добірка червоних жіночих капців DomMood.',
            'is_indexable' => true,
            'is_active' => true,
        ]);

        $this->get('/catalog/zhinochi-kaptsi/filter/kolir/chervonyi')
            ->assertOk()
            ->assertSee('<title>Червоні жіночі капці купити | DomMood</title>', false)
            ->assertSee('<meta name="description" content="Добірка червоних жіночих капців DomMood.">', false)
            ->assertSee('<h1>Червоні жіночі капці</h1>', false)
            ->assertDontSee('noindex,follow');
    }

    public function test_product_page_outputs_product_seo_meta(): void
    {
        $category = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);

        Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Молочні пухнасті капці',
            'slug' => 'molochni-pukhnasti-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'meta_title' => 'Молочні пухнасті капці DomMood',
            'meta_description' => 'Мʼякі молочні капці для дому з швидкою доставкою по Україні.',
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi')
            ->assertOk()
            ->assertSee('<title>Молочні пухнасті капці DomMood</title>', false)
            ->assertSee('<meta name="description" content="Мʼякі молочні капці для дому з швидкою доставкою по Україні.">', false)
            ->assertSee('<link rel="canonical" href="'.url('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi').'">', false);
    }

    public function test_product_page_does_not_output_bottom_faq_accordion(): void
    {
        $category = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);

        Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Молочні пухнасті капці',
            'slug' => 'molochni-pukhnasti-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi')
            ->assertOk()
            ->assertDontSee('product-detail-accordion', false)
            ->assertDontSee('Чи є товар у наявності?')
            ->assertDontSee('Як підібрати правильний розмір?')
            ->assertDontSee('"@type":"FAQPage"', false)
            ->assertDontSee('"@type":"Question"', false);
    }

    public function test_product_page_marks_zero_stock_variants_unavailable(): void
    {
        $category = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Капці з розмірами',
            'slug' => 'kaptsi-z-rozmiramy',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'stock_status' => Product::STOCK_IN_STOCK,
            'published_at' => now()->subMinute(),
        ]);
        $product->variants()->create([
            'sku' => 'DM-36',
            'size' => '36-37',
            'price_cents' => 89900,
            'stock_quantity' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $availableVariant = $product->variants()->create([
            'sku' => 'DM-38',
            'size' => '38-39',
            'price_cents' => 89900,
            'stock_quantity' => 3,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->get('/catalog/domashni-kaptsi/kaptsi-z-rozmiramy')
            ->assertOk()
            ->assertSee('36-37')
            ->assertSee('38-39')
            ->assertSee('is-disabled', false)
            ->assertSee('name="product_variant_id" value="'.$availableVariant->id.'"', false)
            ->assertSee('data-product-add-label>У кошик', false);
    }

    public function test_customer_can_submit_product_review_for_moderation(): void
    {
        $category = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Молочні пухнасті капці',
            'slug' => 'molochni-pukhnasti-kaptsi',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 89900,
            'published_at' => now()->subMinute(),
        ]);

        $this->from('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi')
            ->post(route('catalog.product.reviews.store', [
                'categorySlug' => $category->slug,
                'productSlug' => $product->slug,
            ]), [
                'author_name' => 'Катя',
                'author_email' => 'katia@example.com',
                'rating' => 5,
                'body' => 'Класні, мені сподобалися.',
                'website' => '',
            ])
            ->assertRedirect('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi')
            ->assertSessionHas('review_status', 'Дякуємо. Відгук зʼявиться після модерації.');

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'author_name' => 'Катя',
            'author_email' => 'katia@example.com',
            'rating' => 5,
            'body' => 'Класні, мені сподобалися.',
            'status' => Review::STATUS_PENDING,
            'source' => 'site',
        ]);

        $this->get('/catalog/domashni-kaptsi/molochni-pukhnasti-kaptsi')
            ->assertOk()
            ->assertSee('Залишити відгук')
            ->assertSee('data-product-dialog="review-form"', false)
            ->assertDontSee('Класні, мені сподобалися.');
    }
}
