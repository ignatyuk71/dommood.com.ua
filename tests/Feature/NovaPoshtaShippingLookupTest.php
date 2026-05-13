<?php

namespace Tests\Feature;

use App\Models\ShippingProviderSetting;
use App\Services\Shipping\NovaPoshtaApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NovaPoshtaShippingLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.nova_poshta.api_key', 'test-api-key');
        config()->set('services.nova_poshta.api_url', 'https://api.novaposhta.ua/v2.0/json/');
        config()->set('services.nova_poshta.sender_city_ref', 'sender-city-ref');
        $this->app->forgetInstance(NovaPoshtaApi::class);
    }

    public function test_can_search_nova_poshta_cities(): void
    {
        Http::fake([
            'api.novaposhta.ua/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'Ref' => 'city-ref',
                        'Description' => 'Київ',
                        'AreaDescription' => 'Київська',
                        'RegionDescription' => 'Київ',
                    ],
                ],
            ]),
        ]);

        $this->getJson(route('shipping.nova-poshta.cities', [
            'q' => 'Київ',
        ]))
            ->assertOk()
            ->assertJsonPath('items.0.ref', 'city-ref')
            ->assertJsonPath('items.0.name', 'Київ')
            ->assertJsonPath('error', null);

        Http::assertSent(fn ($request): bool => $request['apiKey'] === 'test-api-key'
            && $request['modelName'] === 'Address'
            && $request['calledMethod'] === 'getCities'
            && $request['methodProperties']['FindByString'] === 'Київ');
    }

    public function test_can_search_and_filter_nova_poshta_warehouses(): void
    {
        Http::fake([
            'api.novaposhta.ua/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'Ref' => 'branch-ref',
                        'Description' => 'Відділення №1',
                        'ShortAddress' => 'вул. Центральна, 1',
                    ],
                    [
                        'Ref' => 'postomat-ref',
                        'Description' => 'Поштомат №22',
                        'ShortAddress' => 'вул. Центральна, 2',
                    ],
                ],
            ]),
        ]);

        $this->getJson(route('shipping.nova-poshta.warehouses', [
            'city_ref' => 'city-ref',
            'type' => 'postomat',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.ref', 'postomat-ref')
            ->assertJsonPath('items.0.type', 'postomat');
    }

    public function test_can_calculate_nova_poshta_delivery_price(): void
    {
        Http::fake([
            'api.novaposhta.ua/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'Cost' => 95,
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('shipping.nova-poshta.price'), [
            'recipient_city_ref' => 'recipient-city-ref',
            'service_type' => 'WarehouseWarehouse',
            'weight' => 1.2,
            'cost' => 799,
            'seats_amount' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('price', 95)
            ->assertJsonPath('price_cents', 9500)
            ->assertJsonPath('currency', 'UAH');

        Http::assertSent(fn ($request): bool => $request['modelName'] === 'InternetDocument'
            && $request['calledMethod'] === 'getDocumentPrice'
            && $request['methodProperties']['CitySender'] === 'sender-city-ref'
            && $request['methodProperties']['CityRecipient'] === 'recipient-city-ref'
            && $request['methodProperties']['Cost'] === '799');
    }

    public function test_nova_poshta_lookup_uses_saved_database_settings_before_env(): void
    {
        ShippingProviderSetting::query()->create([
            'code' => 'nova_poshta',
            'name' => 'Нова пошта',
            'settings' => [
                'api_key' => 'database-api-key',
                'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
                'sender_city_ref' => 'database-sender-city',
                'default_weight' => 2.4,
            ],
            'is_active' => true,
        ]);
        $this->app->forgetInstance(NovaPoshtaApi::class);

        Http::fake([
            'api.novaposhta.ua/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'Cost' => 120,
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('shipping.nova-poshta.price'), [
            'recipient_city_ref' => 'recipient-city-ref',
            'cost' => 799,
        ])
            ->assertOk()
            ->assertJsonPath('price_cents', 12000);

        Http::assertSent(fn ($request): bool => $request['apiKey'] === 'database-api-key'
            && $request['methodProperties']['CitySender'] === 'database-sender-city'
            && $request['methodProperties']['Weight'] === '2.4');
    }

    public function test_lookup_returns_clear_error_when_api_key_is_missing(): void
    {
        config()->set('services.nova_poshta.api_key', null);
        $this->app->forgetInstance(NovaPoshtaApi::class);

        $this->getJson(route('shipping.nova-poshta.cities', [
            'q' => 'Київ',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('items', [])
            ->assertJsonPath('error', 'Не налаштовано API ключ Нової пошти.');
    }
}
