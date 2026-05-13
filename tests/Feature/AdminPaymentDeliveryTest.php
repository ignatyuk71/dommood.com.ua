<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\DeliveryTariff;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPaymentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_payment_delivery_sections(): void
    {
        $user = User::factory()->create();
        $deliveryMethod = DeliveryMethod::query()->create([
            'name' => 'Нова пошта',
            'code' => 'nova_poshta',
            'provider' => 'nova_poshta',
            'type' => 'branch',
            'base_price_cents' => 7000,
            'is_active' => true,
        ]);
        PaymentMethod::query()->create([
            'name' => 'Оплата при отриманні',
            'code' => 'cod',
            'type' => 'cod',
            'is_active' => true,
        ]);
        DeliveryTariff::query()->create([
            'delivery_method_id' => $deliveryMethod->id,
            'name' => 'Стандарт',
            'code' => 'standard',
            'price_cents' => 7000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.payment-delivery.show', 'delivery-methods'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PaymentDelivery/Index', false)
                ->where('section', 'delivery-methods')
                ->where('deliveryMethods.0.name', 'Нова пошта')
                ->where('deliveryMethods.0.base_price', '70.00 грн')
                ->where('paymentMethods.0.name', 'Оплата при отриманні')
                ->where('tariffs.0.name', 'Стандарт')
                ->where('stats.active_delivery_methods', 1)
                ->where('stats.active_payment_methods', 1)
                ->where('stats.active_tariffs', 1));
    }

    public function test_admin_can_create_and_update_delivery_method(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.payment-delivery.delivery-methods.store'), [
                'name' => 'Нова пошта',
                'provider' => 'nova_poshta',
                'type' => 'branch',
                'description' => 'Відділення та поштомати.',
                'base_price' => '70.50',
                'free_from' => '2000',
                'is_active' => true,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.payment-delivery.show', 'delivery-methods'));

        $method = DeliveryMethod::query()->firstOrFail();

        $this->assertSame('nova_posta', $method->code);
        $this->assertSame(7050, $method->base_price_cents);
        $this->assertSame(200000, $method->free_from_cents);

        $this->actingAs($user)
            ->put(route('admin.payment-delivery.delivery-methods.update', $method), [
                'name' => 'НП: відділення',
                'code' => 'nova_poshta_branch',
                'provider' => 'nova_poshta',
                'type' => 'branch',
                'description' => 'Оновлений опис.',
                'base_price' => '80',
                'free_from' => '',
                'is_active' => false,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.payment-delivery.show', 'delivery-methods'));

        $this->assertDatabaseHas('delivery_methods', [
            'id' => $method->id,
            'name' => 'НП: відділення',
            'code' => 'nova_poshta_branch',
            'base_price_cents' => 8000,
            'free_from_cents' => null,
            'is_active' => false,
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_create_payment_method_and_delivery_tariff(): void
    {
        $user = User::factory()->create();
        $deliveryMethod = DeliveryMethod::query()->create([
            'name' => 'Самовивіз',
            'code' => 'pickup',
            'provider' => 'pickup',
            'type' => 'pickup',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.payment-delivery.payment-methods.store'), [
                'name' => 'LiqPay',
                'type' => 'liqpay',
                'description' => 'Онлайн-оплата карткою.',
                'fee_percent' => '2.75',
                'fixed_fee' => '0',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.payment-delivery.show', 'payment-methods'));

        $this->actingAs($user)
            ->post(route('admin.payment-delivery.tariffs.store'), [
                'delivery_method_id' => $deliveryMethod->id,
                'name' => 'Самовивіз безкоштовно',
                'region' => 'Івано-Франківська обл.',
                'city' => 'Івано-Франківськ',
                'min_order' => '0',
                'max_order' => '',
                'price' => '0',
                'free_from' => '',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.payment-delivery.show', 'tariffs'));

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'LiqPay',
            'code' => 'liqpay',
            'type' => 'liqpay',
            'fee_percent' => '2.75',
            'fixed_fee_cents' => 0,
        ]);
        $method = PaymentMethod::query()->where('code', 'liqpay')->firstOrFail();
        $this->assertSame([], $method->settings);
        $this->assertSame([], $method->secret_settings);
        $this->assertDatabaseHas('delivery_tariffs', [
            'delivery_method_id' => $deliveryMethod->id,
            'name' => 'Самовивіз безкоштовно',
            'code' => 'samoviviz_bezkostovno',
            'city' => 'Івано-Франківськ',
            'price_cents' => 0,
        ]);
    }

    public function test_generated_codes_are_unique_when_names_repeat(): void
    {
        $user = User::factory()->create();

        foreach ([1, 2] as $sortOrder) {
            $this->actingAs($user)
                ->post(route('admin.payment-delivery.delivery-methods.store'), [
                    'name' => 'Нова пошта',
                    'provider' => 'nova_poshta',
                    'type' => 'branch',
                    'base_price' => '70',
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ])
                ->assertRedirect(route('admin.payment-delivery.show', 'delivery-methods'));
        }

        $this->assertDatabaseHas('delivery_methods', ['code' => 'nova_posta']);
        $this->assertDatabaseHas('delivery_methods', ['code' => 'nova_posta_2']);
    }

    public function test_admin_can_delete_payment_method(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::query()->create([
            'name' => 'Оплата карткою',
            'code' => 'card',
            'type' => 'card',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.payment-delivery.payment-methods.destroy', $method))
            ->assertRedirect(route('admin.payment-delivery.show', 'payment-methods'));

        $this->assertDatabaseMissing('payment_methods', [
            'id' => $method->id,
        ]);
    }
}
