<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_content_page_is_rendered_on_storefront(): void
    {
        ContentPage::query()->updateOrCreate(
            ['slug' => 'oplata-i-dostavka'],
            [
                'title' => 'Оплата і доставка',
                'content' => '<p>Доставляємо Новою поштою.</p>',
                'status' => 'published',
                'meta_title' => 'Оплата і доставка DomMood',
                'meta_description' => 'Умови оплати і доставки.',
                'published_at' => now()->subMinute(),
            ],
        );

        $this->get('/oplata-i-dostavka')
            ->assertOk()
            ->assertViewIs('storefront.page')
            ->assertSee('Оплата і доставка')
            ->assertSee('Доставляємо Новою поштою.', false)
            ->assertSee('Оплата і доставка DomMood');
    }

    public function test_free_return_page_uses_wide_layout_and_legacy_slug_redirects(): void
    {
        $this->get('/bezkoshtovne-povernennia-novoiu-poshtoiu')
            ->assertOk()
            ->assertSee('storefront-content-page__container--wide', false)
            ->assertSee('Легке повернення')
            ->assertSee('/brand/content-pages/returns/easy-return-step-1.jpeg', false);

        $this->get('/bezkoshtovne-povernennia-novoiu-poshtoiu/')
            ->assertOk()
            ->assertSee('Безкоштовне повернення');

        $this->get('/bezkoshtovne-povernennia')
            ->assertRedirect('/bezkoshtovne-povernennia-novoiu-poshtoiu');

        $this->get('/bezkoshtovne-povernennia/')
            ->assertRedirect('/bezkoshtovne-povernennia-novoiu-poshtoiu');
    }

    public function test_privacy_policy_page_uses_migrated_legal_content(): void
    {
        $this->get('/polityka-konfidentsiinosti/')
            ->assertOk()
            ->assertSee('Політика конфіденційності')
            ->assertSee('storefront-site-header', false)
            ->assertSee('storefront-category-nav', false)
            ->assertSee('storefront-footer', false)
            ->assertSee('Контактна інформація')
            ->assertSee('legal-page', false)
            ->assertSee('Про захист персональних даних')
            ->assertSee('Остання редакція: 13.11.2025');
    }

    public function test_legacy_content_pages_are_migrated_to_storefront(): void
    {
        $this->get('/uhoda-korystuvacha/')
            ->assertOk()
            ->assertSee('storefront-site-header', false)
            ->assertSee('storefront-content-page__container--wide', false)
            ->assertSee('ПУБЛІЧНА УГОДА КОРИСТУВАЧА')
            ->assertSee('ФОП Ігнатюк Михайло');

        $this->get('/kontakty/')
            ->assertOk()
            ->assertSee('dommood.com.ua@gmail.com')
            ->assertSee('Костопіль')
            ->assertSee('contacts-page__map', false);

        $this->get('/obmin-ta-povernennya/')
            ->assertOk()
            ->assertSee('Порядок повернення товару')
            ->assertSee('Нова пошта № 4');

        $this->get('/oplata-i-dostavka/')
            ->assertOk()
            ->assertSee('Безкоштовна доставка від 1200 грн')
            ->assertSee('Відстежити посилку');

        $this->get('/pro-nas/')
            ->assertOk()
            ->assertSee('маленьке українське виробництво домашнього затишку')
            ->assertSee('домашні капці, мʼяке взуття та текстиль')
            ->assertSee('Наша філософія')
            ->assertSee('Виробництво')
            ->assertSee('зроблено в Україні для дому');
    }

    public function test_renamed_content_page_slugs_redirect_to_live_urls(): void
    {
        $this->get('/obmin-ta-povernennia')
            ->assertRedirect('/obmin-ta-povernennya');

        $this->get('/obmin-ta-povernennia/')
            ->assertRedirect('/obmin-ta-povernennya');

        $this->get('/uhoda-korystuvacha-oferta')
            ->assertRedirect('/uhoda-korystuvacha');

        $this->get('/uhoda-korystuvacha-oferta/')
            ->assertRedirect('/uhoda-korystuvacha');
    }

    public function test_home_page_renders_utility_menu_links(): void
    {
        $page = ContentPage::query()
            ->where('slug', 'polityka-konfidentsiinosti')
            ->firstOrFail();

        $menu = Menu::query()->create([
            'name' => 'Верхня полоска',
            'slug' => 'utility',
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'title' => 'Політика конфіденційності',
            'type' => 'page',
            'linkable_type' => ContentPage::class,
            'linkable_id' => $page->id,
            'target' => '_self',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('utilityLinks', function (array $links): bool {
                return count($links) === 1
                    && $links[0]['title'] === 'Політика конфіденційності'
                    && str_ends_with($links[0]['url'], '/polityka-konfidentsiinosti');
            })
            ->assertSee('Політика конфіденційності');
    }
}
