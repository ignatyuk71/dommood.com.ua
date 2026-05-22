<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MarketingIntegration;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promocode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_product_to_cart_and_open_cart_drawer(): void
    {
        $product = $this->makeProduct();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('cart.show'));

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertViewIs('storefront.cart.show')
            ->assertSee('Кошик')
            ->assertSee('Домашні капці Welcome Home')
            ->assertSee('Оформити замовлення');
    }

    public function test_ajax_add_to_cart_returns_right_side_drawer_payload(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('items_count', 1)
            ->assertJsonPath('quantity_count', 1)
            ->assertJsonPath('is_empty', false)
            ->assertJsonPath('cart_summary.free_shipping_remaining_cents', 87500)
            ->assertJsonPath('cart_summary.free_shipping_progress_percent', 27);

        $this->assertStringContainsString('data-cart-drawer', $response->json('drawer_html'));
        $this->assertStringContainsString('storefront-cart-drawer-shell is-open', $response->json('drawer_html'));
        $this->assertStringContainsString('Домашні капці Welcome Home', $response->json('drawer_html'));
        $this->assertStringContainsString('storefront-cart-free-shipping', $response->json('drawer_html'));
        $this->assertStringContainsString('Додайте ще 875 грн', $response->json('drawer_html'));
        $this->assertSame('Товар додано до кошика.', $response->json('status_message'));
        $this->assertSame('add_to_cart', $response->json('analytics_event.event'));
        $this->assertSame('UAH', $response->json('analytics_event.ecommerce.currency'));
        $this->assertEquals(325.0, $response->json('analytics_event.ecommerce.value'));
        $this->assertSame('Домашні капці Welcome Home', $response->json('analytics_event.ecommerce.items.0.item_name'));
    }

    public function test_google_analytics_tag_and_checkout_events_render_when_enabled(): void
    {
        $this->enableGoogleAnalytics();
        $product = $this->makeProduct();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.show'));

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ZTHCWKS4YP', false)
            ->assertSee('view_cart', false);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('begin_checkout', false)
            ->assertSee('add_shipping_info', false)
            ->assertSee('add_payment_info', false);
    }

    public function test_customer_can_change_cart_item_variant_and_price_updates(): void
    {
        $product = $this->makeProduct();
        $smallVariant = $this->makeVariant($product, '36-37', 32500);
        $largeVariant = $this->makeVariant($product, '38-39', 39900);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_variant_id' => $smallVariant->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.show'));

        $response = $this->patchJson(route('cart.items.update', 1), [
            'quantity' => 1,
            'product_variant_id' => $largeVariant->id,
        ])->assertOk()
            ->assertJsonPath('cart_summary.total_cents', 39900);

        $this->assertStringContainsString('data-cart-variant-select', $response->json('drawer_html'));
        $this->assertStringContainsString('38-39', $response->json('drawer_html'));

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $largeVariant->id,
            'price_cents' => 39900,
            'total_cents' => 39900,
        ]);
    }

    public function test_customer_cannot_add_zero_stock_variant_to_cart(): void
    {
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, '36-37', 32500);
        $variant->update(['stock_quantity' => 0]);

        $this->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_creates_order_from_session_cart(): void
    {
        $product = $this->makeProduct();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.show'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewIs('storefront.checkout.index')
            ->assertSee('Оформлення замовлення');

        $this->post(route('checkout.store'), [
            'customer_first_name' => 'Ірина',
            'customer_last_name' => 'Клименко',
            'customer_phone' => '+38 (093) 111-22-33',
            'customer_email' => 'iryna@example.com',
            'delivery_method' => 'nova_poshta_branch',
            'delivery_city' => 'Київ',
            'delivery_branch' => 'Відділення 12',
            'payment_method' => 'cod',
            'comment' => 'Подзвонити перед відправкою.',
            'terms_accepted' => '1',
        ])->assertRedirect();

        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame('Ірина Клименко', $order->customer_name);
        $this->assertSame('new', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('+380931112233', $order->customer_phone);
        $this->assertSame(9000, $order->delivery_price_cents);
        $this->assertSame(41500, $order->total_cents);
        $this->assertCount(1, $order->items);
        $this->assertSame('Домашні капці Welcome Home', $order->items->first()->product_name);
        $this->assertDatabaseHas('customers', [
            'phone' => '+380931112233',
            'orders_count' => 1,
            'total_spent_cents' => 41500,
        ]);

        $this->get(route('checkout.thank-you', $order->order_number))
            ->assertOk()
            ->assertViewIs('storefront.checkout.thank-you')
            ->assertSee('Дякуємо, замовлення прийнято')
            ->assertSee($order->order_number)
            ->assertSee('Домашні капці Welcome Home');
    }

    public function test_promocode_discount_is_carried_to_order(): void
    {
        $product = $this->makeProduct();

        Promocode::query()->create([
            'code' => 'WELCOME50',
            'name' => 'Welcome discount',
            'discount_type' => 'fixed',
            'amount_cents' => 5000,
            'is_active' => true,
        ]);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.show'));

        $this->post(route('cart.promocode.apply'), [
            'code' => 'welcome50',
        ])->assertRedirect(route('cart.show'));

        $this->post(route('checkout.store'), [
            'customer_first_name' => 'Олена',
            'customer_phone' => '+38 067 111 22 33',
            'delivery_method' => 'nova_poshta_branch',
            'delivery_city' => 'Львів',
            'delivery_branch' => 'Поштомат 5',
            'payment_method' => 'cod',
            'terms_accepted' => '1',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame('WELCOME50', $order->promocode_code);
        $this->assertSame(5000, $order->discount_total_cents);
        $this->assertSame(36500, $order->total_cents);
    }

    public function test_checkout_reuses_existing_customer_for_authenticated_user(): void
    {
        $product = $this->makeProduct();
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
        ]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Покупець',
            'email' => 'buyer@example.com',
        ]);

        $this->actingAs($user)
            ->post(route('cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertRedirect(route('cart.show'));

        $this->actingAs($user)
            ->post(route('checkout.store'), [
                'customer_first_name' => 'Ірина',
                'customer_last_name' => 'Клименко',
                'customer_phone' => '+38 (093) 111-22-33',
                'customer_email' => 'buyer@example.com',
                'delivery_method' => 'nova_poshta_branch',
                'delivery_city' => 'Київ',
                'delivery_branch' => 'Відділення 12',
                'payment_method' => 'cod',
                'terms_accepted' => '1',
            ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => $user->id,
            'phone' => '+380931112233',
            'orders_count' => 1,
            'total_spent_cents' => 41500,
        ]);
    }

    private function makeProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'is_active' => true,
        ]);

        return Product::query()->create([
            'primary_category_id' => $category->id,
            'name' => 'Домашні капці Welcome Home',
            'slug' => 'domashni-kaptsi-welcome-home',
            'status' => Product::STATUS_ACTIVE,
            'price_cents' => 32500,
            'old_price_cents' => 45000,
            'stock_status' => Product::STOCK_IN_STOCK,
            'published_at' => now()->subMinute(),
        ]);
    }

    private function makeVariant(Product $product, string $size, int $priceCents): ProductVariant
    {
        return $product->variants()->create([
            'size' => $size,
            'price_cents' => $priceCents,
            'old_price_cents' => $priceCents + 10000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function enableGoogleAnalytics(): void
    {
        $integration = MarketingIntegration::query()->create([
            'provider' => MarketingIntegration::PROVIDER_GOOGLE,
            'status' => MarketingIntegration::STATUS_ACTIVE,
            'mode' => MarketingIntegration::MODE_PROD,
        ]);

        $integration->settings()->create([
            'settings' => [
                'send_client' => true,
                'send_server' => false,
                'measurement_id' => 'G-ZTHCWKS4YP',
                'gtm_container_id' => null,
            ],
        ]);
    }
}
