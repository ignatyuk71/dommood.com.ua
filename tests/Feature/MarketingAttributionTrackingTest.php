<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MarketingIntegration;
use App\Models\MarketingIntegrationCredential;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingAttributionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiktok_add_to_cart_sends_only_tiktok_server_event(): void
    {
        Http::fake([
            'https://business-api.tiktok.com/*' => Http::response(['code' => 0, 'message' => 'OK'], 200),
        ]);

        $this->enablePixelProvider('meta');
        $this->enablePixelProvider('tiktok');
        $product = $this->makeProduct();

        $response = $this
            ->postJson(route('cart.items.store').'?ttclid=tt-click&utm_source=tiktok&utm_medium=cpc', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->assertSame('add_to_cart', $response->json('analytics_event.event'));
        $this->assertNotEmpty($response->json('analytics_event.ecommerce.event_id'));

        $this->assertDatabaseHas('marketing_event_outbox', [
            'provider' => 'tiktok',
            'event_name' => 'AddToCart',
            'status' => 'sent',
        ]);

        $this->assertDatabaseMissing('marketing_event_outbox', [
            'provider' => 'meta',
            'event_name' => 'AddToCart',
        ]);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'add_to_cart',
            'source' => 'tiktok',
            'channel' => 'tiktok_ads',
        ]);
    }

    public function test_google_ads_purchase_sends_only_google_ads_conversion(): void
    {
        Cache::flush();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
            ], 200),
            'https://googleads.googleapis.com/*' => Http::response([
                'results' => [['gclid' => 'google-click']],
            ], 200, ['request-id' => 'google-request-1']),
        ]);

        $this->enablePixelProvider('meta');
        $this->enablePixelProvider('tiktok');
        $this->enableGoogleAdsServer();
        $product = $this->makeProduct();

        $this->post(route('cart.items.store').'?gclid=google-click&utm_source=google&utm_medium=cpc', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.show'));

        $this->post(route('checkout.store'), [
            'customer_first_name' => 'Ірина',
            'customer_last_name' => 'Клименко',
            'customer_phone' => '+38 (093) 111-22-33',
            'customer_email' => 'iryna@example.com',
            'delivery_method' => 'nova_poshta_branch',
            'delivery_city' => 'Київ',
            'delivery_branch' => 'Відділення 12',
            'payment_method' => 'cod',
            'terms_accepted' => '1',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame('google', $order->source);
        $this->assertSame('google_ads', $order->channel);
        $this->assertSame('google-click', $order->click_ids['gclid'] ?? null);

        $this->assertDatabaseHas('marketing_event_outbox', [
            'provider' => 'google',
            'event_name' => 'conversion',
            'event_id' => 'order_'.$order->order_number,
            'status' => 'sent',
        ]);

        $this->assertDatabaseMissing('marketing_event_outbox', [
            'provider' => 'meta',
            'event_name' => 'Purchase',
        ]);

        $this->assertDatabaseMissing('marketing_event_outbox', [
            'provider' => 'tiktok',
            'event_name' => 'CompletePayment',
        ]);
    }

    private function enablePixelProvider(string $provider): void
    {
        $integration = MarketingIntegration::query()->create([
            'provider' => $provider,
            'status' => MarketingIntegration::STATUS_ACTIVE,
            'mode' => MarketingIntegration::MODE_TEST,
        ]);

        $integration->settings()->create([
            'settings' => [
                'send_client' => true,
                'send_server' => true,
                'pixel_id' => $provider.'-pixel',
                'test_event_code' => 'TEST123',
            ],
        ]);

        MarketingIntegrationCredential::query()->create([
            'marketing_integration_id' => $integration->id,
            'secret_type' => 'access_token',
            'secret_value' => $provider.'-token',
            'secret_last_four' => 'oken',
        ]);
    }

    private function enableGoogleAdsServer(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKey);

        $integration = MarketingIntegration::query()->create([
            'provider' => 'google',
            'status' => MarketingIntegration::STATUS_ACTIVE,
            'mode' => MarketingIntegration::MODE_PROD,
        ]);

        $integration->settings()->create([
            'settings' => [
                'send_client' => true,
                'send_server' => true,
                'measurement_id' => 'G-TEST',
                'ads_conversion_id' => 'AW-TEST',
                'ads_conversion_label' => 'label',
                'ads_api_customer_id' => '123-456-7890',
                'ads_api_conversion_action_id' => '9876543210',
            ],
        ]);

        MarketingIntegrationCredential::query()->create([
            'marketing_integration_id' => $integration->id,
            'secret_type' => 'ads_developer_token',
            'secret_value' => 'developer-token',
            'secret_last_four' => 'oken',
        ]);

        MarketingIntegrationCredential::query()->create([
            'marketing_integration_id' => $integration->id,
            'secret_type' => 'ads_service_account_json',
            'secret_value' => json_encode([
                'client_email' => 'service@dommood.test',
                'private_key' => $privateKey,
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ], JSON_UNESCAPED_SLASHES),
            'secret_last_four' => 'json',
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
}
