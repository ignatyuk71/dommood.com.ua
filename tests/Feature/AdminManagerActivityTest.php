<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColorGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_manager_activity_report_with_filters(): void
    {
        $admin = User::factory()->create(['name' => 'Я Адмін', 'role' => 'admin']);
        $manager = User::factory()->create([
            'name' => 'Аліна Менеджер',
            'email' => 'olena@example.com',
            'role' => 'manager',
        ]);
        $order = $this->makeOrder(['order_number' => 'A-1001']);
        $product = Product::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
            'price_cents' => 79900,
        ]);

        AdminActivityLog::query()->create([
            'user_id' => $manager->id,
            'event' => 'order.status_updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'subject_label' => 'Замовлення #A-1001',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'completed'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
        AdminActivityLog::query()->create([
            'user_id' => $manager->id,
            'event' => 'product.updated',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
            'subject_label' => 'Домашні капці',
            'old_values' => ['price_cents' => 70000],
            'new_values' => ['price_cents' => 79900],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.manager-activity.index', [
                'manager_id' => $manager->id,
                'event_group' => 'orders',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ManagerActivity/Index', false)
                ->where('filters.manager_id', $manager->id)
                ->where('filters.event_group', 'orders')
                ->where('summary.total_actions', 1)
                ->where('summary.order_actions_count', 1)
                ->where('logs.data.0.event', 'order.status_updated')
                ->where('logs.data.0.manager.name', 'Аліна Менеджер')
                ->where('logs.data.0.subject_label', 'Замовлення #A-1001')
                ->where('logs.data.0.changes.0.old', 'Нове')
                ->where('logs.data.0.changes.0.new', 'Завершено')
                ->where('managers.0.name', 'Аліна Менеджер'));
    }

    public function test_admin_login_product_create_and_order_status_are_logged(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => 'manager',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $order = $this->makeOrder(['status' => 'new']);

        $this->post(route('login'), [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $manager->id,
            'event' => 'admin.login',
        ]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'primary_category_id' => $category->id,
            'category_ids' => [$category->id],
            'name' => 'Тестові капці',
            'slug' => '',
            'sku' => 'TEST-001',
            'status' => Product::STATUS_ACTIVE,
            'price' => '799.00',
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('sku', 'TEST-001')->firstOrFail();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->id,
            'event' => 'product.created',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.orders.status', $order), [
                'status' => 'completed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->id,
            'event' => 'order.status_updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_admin_module_visits_are_logged_once_per_throttle_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk();

        $this->assertSame(1, AdminActivityLog::query()
            ->where('user_id', $admin->id)
            ->where('event', 'admin.module_viewed')
            ->where('new_values->module', 'Категорії')
            ->count());

        $log = AdminActivityLog::query()
            ->where('event', 'admin.module_viewed')
            ->firstOrFail();

        $this->assertSame('admin.categories.index', $log->new_values['route']);
    }

    public function test_catalog_changes_are_logged_by_admin_activity_observer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.color-groups.store'), [
                'name' => 'Синій',
                'code' => 'blue',
                'description' => 'Сині товари',
                'is_active' => true,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.color-groups.index'));

        $group = ProductColorGroup::query()->where('code', 'blue')->firstOrFail();
        $createdLog = AdminActivityLog::query()
            ->where('event', 'catalog.color_group.created')
            ->where('subject_type', ProductColorGroup::class)
            ->where('subject_id', $group->id)
            ->firstOrFail();

        $this->assertSame('Синій', $createdLog->new_values['name']);

        $this->actingAs($admin)
            ->put(route('admin.color-groups.update', $group), [
                'name' => 'Синя група',
                'code' => 'blue',
                'description' => 'Сині товари',
                'is_active' => false,
                'sort_order' => 20,
            ])
            ->assertRedirect(route('admin.color-groups.index'));

        $updatedLog = AdminActivityLog::query()
            ->where('event', 'catalog.color_group.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Синій', $updatedLog->old_values['name']);
        $this->assertSame('Синя група', $updatedLog->new_values['name']);
        $this->assertFalse($updatedLog->new_values['is_active']);

        $this->actingAs($admin)
            ->delete(route('admin.color-groups.destroy', $group))
            ->assertRedirect(route('admin.color-groups.index'));

        $this->assertDatabaseHas('admin_activity_logs', [
            'event' => 'catalog.color_group.deleted',
            'subject_type' => ProductColorGroup::class,
            'subject_id' => $group->id,
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
            'delivery_city' => $overrides['delivery_city'] ?? 'Львів',
            'delivery_branch' => $overrides['delivery_branch'] ?? 'Відділення №7',
            'delivery_recipient_name' => $overrides['delivery_recipient_name'] ?? 'Ірина Клименко',
            'delivery_recipient_phone' => $overrides['delivery_recipient_phone'] ?? '380931112233',
            'customer_name' => $overrides['customer_name'] ?? 'Ірина Клименко',
            'customer_phone' => $overrides['customer_phone'] ?? '380931112233',
            'customer_email' => $overrides['customer_email'] ?? 'iryna@example.com',
            'currency' => $overrides['currency'] ?? 'UAH',
            'subtotal_cents' => $overrides['subtotal_cents'] ?? 79900,
            'delivery_price_cents' => $overrides['delivery_price_cents'] ?? 7000,
            'total_cents' => $overrides['total_cents'] ?? 86900,
        ]);
    }
}
