<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Banner;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_exposes_active_catalog_products(): void
    {
        Storage::fake('public');

        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);

        $outdoorCategory = Category::query()->create([
            'name' => 'Жіночі капці для вулиці',
            'slug' => 'zhinochi-kaptsi-dlia-vulytsi',
        ]);
        $pajamasCategory = Category::query()->create([
            'name' => 'Жіночі піжами',
            'slug' => 'zhinochi-pizhamy',
        ]);

        Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Чернетка товару',
            'slug' => 'chernetka-tovaru',
            'status' => Product::STATUS_DRAFT,
            'price_cents' => 50000,
        ]);

        $product = Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Домашні капці Halluci',
            'slug' => 'domashni-kaptsi-halluci',
            'sku' => 'DM-HAL-001',
            'short_description' => 'Мʼякі домашні капці для щоденного комфорту.',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 79900,
            'old_price_cents' => 99900,
            'stock_status' => Product::STOCK_IN_STOCK,
            'is_new' => true,
            'published_at' => now()->subMinute(),
        ]);
        $product->categories()->attach($outdoorCategory->id);

        Product::query()->create([
            'primary_category_id' => $pajamasCategory->id,
            'name' => 'Жіноча піжама з бавовни',
            'slug' => 'zhinocha-pizhama-z-bavovny',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 119000,
            'old_price_cents' => 149000,
            'stock_status' => Product::STOCK_IN_STOCK,
            'published_at' => now()->subMinute(),
        ]);

        $path = "products/{$product->id}/main.jpg";
        $cardPath = "products/{$product->id}/domashni-kaptsi-halluci-card.webp";
        Storage::disk('public')->put($path, 'image');
        Storage::disk('public')->put($cardPath, 'card image');

        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $path,
            'alt' => 'Домашні капці Halluci',
            'is_main' => true,
        ]);

        $footerMenu = Menu::query()->create([
            'name' => 'Footer',
            'slug' => 'footer',
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'menu_id' => $footerMenu->id,
            'title' => 'Покупцям',
            'type' => 'custom_url',
            'url' => '#',
            'target' => '_self',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $footerParent = MenuItem::query()->where('title', 'Покупцям')->firstOrFail();

        foreach ([
            ['Доставка і оплата', '/payment-delivery'],
            ['Обмін і повернення', '/returns-exchanges'],
            ['Контакти магазину', '/contacts'],
            ['Новинки у футері', '/catalog?filter=new'],
            ['Каталог у футері', '/catalog'],
            ['Instagram', 'https://www.instagram.com/dommood.com.ua/'],
        ] as $index => [$title, $url]) {
            MenuItem::query()->create([
                'menu_id' => $footerMenu->id,
                'parent_id' => $footerParent->id,
                'title' => $title,
                'type' => 'custom_url',
                'url' => $url,
                'target' => str_starts_with($url, 'https://') ? '_blank' : '_self',
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $this->get('/')
            ->assertOk()
            ->assertViewIs('storefront.home')
            ->assertViewHas('storeName', 'DomMood')
            ->assertViewHas('footerMenuItems', function (array $items): bool {
                return count($items) === 1
                    && $items[0]['title'] === 'Покупцям'
                    && count($items[0]['children']) === 6
                    && $items[0]['children'][0]['title'] === 'Доставка і оплата'
                    && $items[0]['children'][5]['title'] === 'Instagram';
            })
            ->assertViewHas('products', function (array $products) use ($cardPath): bool {
                return count($products) === 2
                    && $products[0]['name'] === 'Домашні капці Halluci'
                    && $products[0]['category']['name'] === 'Капці'
                    && $products[0]['image_url'] === Storage::disk('public')->url($cardPath)
                    && $products[0]['image_large_url'] === Storage::disk('public')->url("products/{$products[0]['id']}/main.jpg")
                    && $products[0]['stock_status_label'] === 'В наявності';
            })
            ->assertSee('storefront-category-banner__layout', false)
            ->assertSee('storefront-featured-carousel', false)
            ->assertViewHas('outdoorPromoProducts', function (array $products): bool {
                return count($products) === 1
                    && $products[0]['name'] === 'Домашні капці Halluci'
                    && str_contains($products[0]['url'], '/catalog/zhinochi-kaptsi-dlia-vulytsi/domashni-kaptsi-halluci');
            })
            ->assertSee('/catalog/zhinochi-kaptsi-dlia-vulytsi/domashni-kaptsi-halluci', false)
            ->assertSee('outdoor-carousel-title', false)
            ->assertSee('Пухнасті моделі на гумовій підошві')
            ->assertSee('Жіночі капці для вулиці')
            ->assertDontSee('/catalog/vulychni-tapochky/zhinochi-tapochky-dlia-vulytsi-siro-blakytnyi', false)
            ->assertSee('pajamas-carousel-title', false)
            ->assertViewHas('pajamasPromoProducts', function (array $products): bool {
                return count($products) === 1
                    && $products[0]['name'] === 'Жіноча піжама з бавовни'
                    && str_contains($products[0]['url'], '/catalog/zhinochi-pizhamy/zhinocha-pizhama-z-bavovny');
            })
            ->assertSee('Мʼякі комплекти для дому та сну')
            ->assertSee('Жіноча піжама з бавовни')
            ->assertDontSee('Жіноча піжама з мʼякої бавовни')
            ->assertSee('DomMood - це домашній комфорт для кожного дня')
            ->assertSee('brand/home/brand-story-pink.webp', false)
            ->assertSee('brand/home/brand-story-black.webp', false)
            ->assertSee('storefront-brand-story__button', false)
            ->assertSee('href="'.url('/pro-nas').'" class="storefront-brand-story__button"', false)
            ->assertSee('>Про нас', false)
            ->assertViewHas('newProducts', function (array $products): bool {
                return count($products) === 1
                    && $products[0]['name'] === 'Домашні капці Halluci'
                    && $products[0]['is_new'] === true;
            })
            ->assertSee('new-products-title', false)
            ->assertSee('storefront-product-grid--new', false)
            ->assertSee('Усі новинки')
            ->assertDontSee('popular-products-title', false)
            ->assertDontSee('Популярні товари')
            ->assertSee('home-faq-title', false)
            ->assertSee('Що нас питають найчастіше?')
            ->assertSee('Чи підходять пухнасті тапочки для вулиці?')
            ->assertSee('Від якої суми доставка безкоштовна?')
            ->assertSee('Безкоштовна доставка діє для замовлень від 1200 грн')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false)
            ->assertSee('data-featured-carousel', false)
            ->assertDontSee('storefront-section--categories', false)
            ->assertDontSee('home-categories-title', false)
            ->assertDontSee('storefront-category-grid', false)
            ->assertSee('home-hero-title', false)
            ->assertSee('Домашні капці Halluci')
            ->assertSee('storefront-messenger-link is-viber', false)
            ->assertSee('viber://chat?number=%2B380679753512', false)
            ->assertSee('brand/icons/viber.svg', false)
            ->assertSee('storefront-messenger-link is-telegram', false)
            ->assertSee('tg://resolve?phone=380679753512', false)
            ->assertSee('brand/icons/telegram.svg', false)
            ->assertSee('storefront-messenger-link is-whatsapp', false)
            ->assertSee('https://wa.me/380679753512', false)
            ->assertSee('brand/icons/whatsapp.svg', false)
            ->assertSee('Покупцям')
            ->assertSee('Контактна інформація')
            ->assertSee('/payment-delivery', false)
            ->assertSee('Доставка і оплата')
            ->assertSee('Обмін і повернення')
            ->assertSee('Контакти магазину')
            ->assertSee('Новинки у футері')
            ->assertSee('Каталог у футері')
            ->assertSee('Instagram')
            ->assertSee('Клієнтам')
            ->assertSee('Вхід до кабінету')
            ->assertSee('Про нас')
            ->assertSee('Оплата і доставка')
            ->assertSee('Обмін та повернення')
            ->assertSee('Контакти')
            ->assertSee('Угода користувача')
            ->assertSee('Політика конфіденційності')
            ->assertSee('Відгуки про магазин')
            ->assertSee('Безкоштовне повернення')
            ->assertDontSee('storefront-footer-catalog', false)
            ->assertDontSee('storefront-footer-menu', false)
            ->assertDontSee('storefront-footer__platform', false)
            ->assertDontSee('ХОРОШОП')
            ->assertDontSee('Хорошоп')
            ->assertDontSee('horoshop', false)
            ->assertSee('brand/icons/instagram.svg', false)
            ->assertSee('brand/icons/tiktok.svg', false)
            ->assertSee('brand/icons/facebook.svg', false)
            ->assertDontSee('Мобільна версія')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Тестове повідомлення магазину')
            ->assertDontSee('Чернетка товару');
    }

    public function test_home_page_renders_active_home_hero_banner(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('banners/1/home-hero.webp', 'image');
        Storage::disk('public')->put('banners/2/home-hero-top.webp', 'image');
        Storage::disk('public')->put('banners/3/home-hero-bottom.webp', 'image');

        Banner::query()->create([
            'title' => 'Весняна добірка Halluci',
            'placement' => 'home_hero_main',
            'image_path' => 'banners/1/home-hero.webp',
            'url' => '/catalog/tapky-halluci',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Banner::query()->create([
            'title' => 'Літні тапочки',
            'placement' => 'home_hero_side_top',
            'image_path' => 'banners/2/home-hero-top.webp',
            'url' => '/catalog/litni-tapochky',
            'button_text' => '1 товар',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Banner::query()->create([
            'title' => 'Червоні хутряні капці',
            'placement' => 'home_hero_side_bottom',
            'image_path' => 'banners/3/home-hero-bottom.webp',
            'url' => '/catalog/chervoni-kaptsi',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Banner::query()->create([
            'title' => 'Вимкнений банер',
            'placement' => 'home_hero_main',
            'image_path' => 'banners/1/disabled.webp',
            'url' => '/disabled',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('banners/1/home-hero.webp'), false)
            ->assertSee(Storage::disk('public')->url('banners/2/home-hero-top.webp'), false)
            ->assertSee(Storage::disk('public')->url('banners/3/home-hero-bottom.webp'), false)
            ->assertSee('Весняна добірка Halluci')
            ->assertSee('Літні тапочки')
            ->assertSee('Червоні хутряні капці')
            ->assertSee(url('/catalog/tapky-halluci'), false)
            ->assertSee(url('/catalog/litni-tapochky'), false)
            ->assertSee(url('/catalog/chervoni-kaptsi'), false)
            ->assertDontSee('/disabled', false);
    }

    public function test_home_page_renders_nested_main_menu_items(): void
    {
        $menu = Menu::query()->create([
            'name' => 'Main',
            'slug' => 'main',
            'is_active' => true,
        ]);

        $catalog = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'title' => 'Каталог',
            'type' => 'custom_url',
            'url' => '/catalog',
            'target' => '_self',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $catalog->id,
            'title' => 'тапки Halluci',
            'type' => 'custom_url',
            'url' => '/catalog/kolory-halluci',
            'target' => '_self',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-desktop-submenu-toggle', false)
            ->assertSee('data-mobile-submenu-toggle', false)
            ->assertSee('storefront-desktop-menu__submenu', false)
            ->assertSee('Каталог')
            ->assertSee('тапки Halluci')
            ->assertSee('/catalog/kolory-halluci');
    }
}
