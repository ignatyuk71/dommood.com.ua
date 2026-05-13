<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\LiqPayService;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiqPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_liqpay_callback_marks_order_as_paid(): void
    {
        $this->makeLiqPayMethod();
        $order = $this->makeOrder([
            'order_number' => 'DM-1001',
            'payment_method' => 'liqpay',
            'payment_status' => 'pending',
            'total_cents' => 129900,
        ]);

        [$data, $signature] = $this->signedPayload([
            'order_id' => 'DM-1001',
            'payment_id' => 'lp-123',
            'status' => 'success',
            'amount' => '1299.00',
            'currency' => 'UAH',
            'paytype' => 'card',
            'action' => 'pay',
        ]);

        $this->post(route('payments.liqpay.callback'), [
            'data' => $data,
            'signature' => $signature,
        ])->assertOk()->assertSee('ok');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('liqpay', $order->payment_provider);
        $this->assertSame('lp-123', $order->payment_reference);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider' => 'liqpay',
            'provider_transaction_id' => 'lp-123',
            'status' => PaymentTransaction::STATUS_PAID,
            'amount_cents' => 129900,
        ]);
    }

    public function test_liqpay_callback_rejects_invalid_signature(): void
    {
        $this->makeLiqPayMethod();
        $order = $this->makeOrder([
            'order_number' => 'DM-1002',
            'payment_status' => 'pending',
        ]);

        [$data] = $this->signedPayload([
            'order_id' => 'DM-1002',
            'payment_id' => 'lp-124',
            'status' => 'success',
            'amount' => '799.00',
            'currency' => 'UAH',
        ]);

        $this->post(route('payments.liqpay.callback'), [
            'data' => $data,
            'signature' => 'bad-signature',
        ])->assertForbidden();

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseMissing('payment_transactions', [
            'provider_transaction_id' => 'lp-124',
        ]);
    }

    public function test_liqpay_amount_mismatch_does_not_mark_order_paid(): void
    {
        $this->makeLiqPayMethod();
        $order = $this->makeOrder([
            'order_number' => 'DM-1003',
            'payment_status' => 'pending',
            'total_cents' => 79900,
        ]);

        [$data, $signature] = $this->signedPayload([
            'order_id' => 'DM-1003',
            'payment_id' => 'lp-125',
            'status' => 'success',
            'amount' => '1.00',
            'currency' => 'UAH',
        ]);

        $this->post(route('payments.liqpay.callback'), [
            'data' => $data,
            'signature' => $signature,
        ])->assertOk();

        $order->refresh();

        $this->assertSame('failed', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider_transaction_id' => 'lp-125',
            'status' => PaymentTransaction::STATUS_AMOUNT_MISMATCH,
            'failure_reason' => 'Сума callback не збігається із сумою замовлення.',
        ]);
    }

    public function test_checkout_payload_creates_pending_transaction(): void
    {
        $method = $this->makeLiqPayMethod();
        $order = $this->makeOrder([
            'order_number' => 'DM-1004',
            'payment_method' => 'liqpay',
            'payment_status' => 'pending',
            'total_cents' => 25000,
        ]);

        $payload = app(LiqPayService::class)->checkoutPayload($order, $method);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('signature', $payload);
        $this->assertSame('DM-1004', $payload['payload']['order_id']);
        $this->assertSame('250.00', $payload['payload']['amount']);
        $this->assertSame(1, $payload['payload']['sandbox']);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider' => 'liqpay',
            'external_order_id' => 'DM-1004',
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_cents' => 25000,
        ]);
    }

    private function makeLiqPayMethod(): PaymentMethod
    {
        app(SiteSettingsService::class)->set('payments', [
            'liqpay_enabled' => true,
            'liqpay_mode' => 'test',
            'liqpay_public_key' => 'public-test-key',
            'liqpay_private_key' => 'private-test-key',
            'liqpay_language' => 'uk',
            'liqpay_server_url' => route('payments.liqpay.callback'),
            'liqpay_result_url' => route('payments.liqpay.result'),
        ]);

        return PaymentMethod::query()->create([
            'name' => 'LiqPay',
            'code' => 'liqpay',
            'type' => 'liqpay',
            'is_active' => true,
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        User::factory()->create();

        return Order::query()->create([
            'order_number' => $overrides['order_number'] ?? 'DM-1000',
            'status' => $overrides['status'] ?? 'new',
            'payment_status' => $overrides['payment_status'] ?? 'unpaid',
            'payment_method' => $overrides['payment_method'] ?? 'cod',
            'customer_name' => $overrides['customer_name'] ?? 'Ірина Клименко',
            'customer_phone' => $overrides['customer_phone'] ?? '380931112233',
            'customer_email' => $overrides['customer_email'] ?? 'iryna@example.com',
            'currency' => $overrides['currency'] ?? 'UAH',
            'subtotal_cents' => $overrides['subtotal_cents'] ?? ($overrides['total_cents'] ?? 79900),
            'discount_total_cents' => $overrides['discount_total_cents'] ?? 0,
            'delivery_price_cents' => $overrides['delivery_price_cents'] ?? 0,
            'total_cents' => $overrides['total_cents'] ?? 79900,
        ]);
    }

    private function signedPayload(array $payload): array
    {
        $data = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = app(LiqPayService::class)->signature($data, 'private-test-key');

        return [$data, $signature];
    }
}
