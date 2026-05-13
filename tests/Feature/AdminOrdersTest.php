<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_compact_orders_index(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Жіночі тапочки',
            'slug' => 'zhinochi-tapochky',
            'sku' => '3455',
            'price_cents' => 79900,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/1/main.webp',
            'is_main' => true,
        ]);
        Storage::disk('public')->put('products/1/main.webp', 'image');

        $order = $this->makeOrder([
            'order_number' => '1001',
            'delivery_method' => 'nova_poshta_branch',
            'delivery_city' => 'Київ',
            'delivery_branch' => 'Відділення №1',
            'source' => 'meta',
            'delivery_price_cents' => 7000,
            'total_cents' => 86900,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Жіночі тапочки',
            'variant_name' => '38/39',
            'sku' => '3455-38',
            'price_cents' => 79900,
            'quantity' => 1,
            'total_cents' => 79900,
        ]);

        $this->actingAs($user)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index', false)
                ->where('orders.data.0.order_number', '1001')
                ->where('orders.data.0.delivery_line', 'Відділення №1')
                ->where('orders.data.0.delivery_price', '70.00 грн')
                ->where('orders.data.0.total', '869.00 грн')
                ->where('orders.data.0.payment_ui.tone', 'cod')
                ->where('orders.data.0.payment_ui.method_label', 'Післяплата')
                ->where('orders.data.0.payment_ui.status_label', 'При отриманні')
                ->where('orders.data.0.payment_ui.amount_label', 'До оплати: 869.00 грн')
                ->where('orders.data.0.source.label', 'Meta')
                ->where('orders.data.0.items.0.product_name', 'Жіночі тапочки')
                ->where('orders.data.0.thumbs.0', Storage::disk('public')->url('products/1/main.webp')));
    }

    public function test_admin_can_update_order_status_and_history_is_created(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.orders.status', $order), [
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Статус замовлення оновлено')
            ->assertJsonPath('status.value', 'completed')
            ->assertJsonPath('status.label', 'Завершено');

        $order->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => 'new',
            'to_status' => 'completed',
        ]);
    }

    public function test_admin_can_open_order_details_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Жіночі тапочки',
            'slug' => 'zhinochi-tapochky',
            'sku' => '3455',
            'price_cents' => 79900,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/1/main.webp',
            'is_main' => true,
        ]);
        Storage::disk('public')->put('products/1/main.webp', 'image');

        $order = $this->makeOrder([
            'order_number' => '4001',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'delivery_provider' => 'nova_poshta',
            'delivery_type' => 'branch',
            'delivery_city' => 'Одеса',
            'delivery_city_ref' => 'city-ref',
            'delivery_branch' => 'Відділення №1: вул. Героїв України, 9',
            'delivery_branch_ref' => 'warehouse-ref',
            'total_cents' => 86900,
            'delivery_price_cents' => 7000,
            'utm_campaign' => 'spring-sale',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Жіночі тапочки',
            'variant_name' => '38/39',
            'sku' => '3455-38',
            'price_cents' => 79900,
            'quantity' => 1,
            'total_cents' => 79900,
            'product_snapshot' => [
                'size' => '38/39',
                'color' => 'малиновий',
            ],
        ]);
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => 'new',
            'to_status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show', false)
                ->where('order.order_number', '4001')
                ->where('order.status_meta.label', 'Підтверджено')
                ->where('order.amount_due', '869.00 грн')
                ->where('order.items_quantity', 1)
                ->where('order.positions_count', 1)
                ->where('order.delivery_line', 'Відділення №1: вул. Героїв України, 9')
                ->where('order.delivery_provider', 'nova_poshta')
                ->where('order.delivery_type', 'branch')
                ->where('order.delivery_city_ref', 'city-ref')
                ->where('order.delivery_branch_ref', 'warehouse-ref')
                ->where('order.tracking.utm_campaign', 'spring-sale')
                ->where('order.items.0.product_name', 'Жіночі тапочки')
                ->where('order.items.0.snapshot.color', 'малиновий')
                ->where('order.status_histories.0.to_status_label', 'Підтверджено')
                ->where('statusOptions.0.value', 'new'));
    }

    public function test_orders_index_can_filter_by_status_group(): void
    {
        $user = User::factory()->create();

        $newOrder = $this->makeOrder([
            'order_number' => '2001',
            'status' => 'new',
        ]);
        $completedOrder = $this->makeOrder([
            'order_number' => '2002',
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('admin.orders.index', ['status_group' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.data.0.id', $completedOrder->id)
                ->missing('orders.data.1'));

        $this->actingAs($user)
            ->get(route('admin.orders.index', [
                'status_group' => 'completed',
                'status' => 'new',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.data.0.id', $newOrder->id)
                ->missing('orders.data.1'));

        $this->assertDatabaseHas('orders', ['id' => $newOrder->id]);
    }

    public function test_invalid_order_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($user)
            ->patchJson(route('admin.orders.status', $order), [
                'status' => 'bad-status',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_soft_delete_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'order_number' => '3001',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success', 'Замовлення видалено');

        $this->assertSoftDeleted('orders', [
            'id' => $order->id,
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::query()->create([
            'order_number' => $overrides['order_number'] ?? '1000',
            'status' => $overrides['status'] ?? 'new',
            'payment_status' => $overrides['payment_status'] ?? 'unpaid',
            'payment_method' => $overrides['payment_method'] ?? 'cod',
            'delivery_method' => $overrides['delivery_method'] ?? 'nova_poshta_branch',
            'delivery_provider' => $overrides['delivery_provider'] ?? null,
            'delivery_type' => $overrides['delivery_type'] ?? null,
            'delivery_city' => $overrides['delivery_city'] ?? 'Львів',
            'delivery_city_ref' => $overrides['delivery_city_ref'] ?? null,
            'delivery_address' => $overrides['delivery_address'] ?? null,
            'delivery_branch' => $overrides['delivery_branch'] ?? 'Відділення №7',
            'delivery_branch_ref' => $overrides['delivery_branch_ref'] ?? null,
            'delivery_recipient_name' => $overrides['delivery_recipient_name'] ?? 'Ірина Клименко',
            'delivery_recipient_phone' => $overrides['delivery_recipient_phone'] ?? '380931112233',
            'customer_name' => $overrides['customer_name'] ?? 'Ірина Клименко',
            'customer_phone' => $overrides['customer_phone'] ?? '380931112233',
            'customer_email' => $overrides['customer_email'] ?? 'iryna@example.com',
            'currency' => $overrides['currency'] ?? 'UAH',
            'subtotal_cents' => $overrides['subtotal_cents'] ?? 79900,
            'discount_total_cents' => $overrides['discount_total_cents'] ?? 0,
            'delivery_price_cents' => $overrides['delivery_price_cents'] ?? 0,
            'total_cents' => $overrides['total_cents'] ?? 79900,
            'source' => $overrides['source'] ?? 'tiktok',
            'utm_source' => $overrides['utm_source'] ?? null,
            'utm_medium' => $overrides['utm_medium'] ?? null,
            'utm_campaign' => $overrides['utm_campaign'] ?? null,
            'utm_content' => $overrides['utm_content'] ?? null,
            'utm_term' => $overrides['utm_term'] ?? null,
        ]);
    }
}
