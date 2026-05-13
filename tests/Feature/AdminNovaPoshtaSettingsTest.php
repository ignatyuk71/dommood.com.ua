<?php

namespace Tests\Feature;

use App\Models\ShippingProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminNovaPoshtaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_nova_poshta_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.nova-poshta.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/NovaPoshta', false)
                ->where('settings.has_api_key', false)
                ->where('settings.api_url', 'https://api.novaposhta.ua/v2.0/json/')
                ->missing('settings.api_key'));
    }

    public function test_admin_can_save_nova_poshta_connection_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.settings.nova-poshta.update'), [
                'is_active' => true,
                'api_key' => 'secret-np-token',
                'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
                'sender_phone' => '380680000000',
                'sender_city_ref' => 'sender-city-ref',
                'sender_city_name' => 'Івано-Франківськ',
                'sender_warehouse_ref' => 'sender-warehouse-ref',
                'sender_warehouse_name' => 'Відділення №1',
                'sender_ref' => 'sender-ref',
                'contact_ref' => 'contact-ref',
                'default_weight' => '1.5',
            ])
            ->assertRedirect(route('admin.settings.nova-poshta.edit'));

        $provider = ShippingProviderSetting::query()
            ->where('code', 'nova_poshta')
            ->firstOrFail();

        $this->assertTrue($provider->is_active);
        $this->assertSame('secret-np-token', $provider->settings['api_key']);
        $this->assertSame('380680000000', $provider->settings['sender_phone']);
        $this->assertSame('sender-city-ref', $provider->settings['sender_city_ref']);
        $this->assertSame('sender-warehouse-ref', $provider->settings['sender_warehouse_ref']);
        $this->assertSame('sender-ref', $provider->settings['sender_ref']);
        $this->assertSame('contact-ref', $provider->settings['contact_ref']);
        $this->assertSame(1.5, $provider->settings['default_weight']);
    }

    public function test_empty_api_key_does_not_overwrite_saved_token(): void
    {
        $user = User::factory()->create();
        $provider = ShippingProviderSetting::query()->create([
            'code' => 'nova_poshta',
            'name' => 'Нова пошта',
            'settings' => [
                'api_key' => 'secret-np-token',
                'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
                'sender_city_ref' => 'old-city-ref',
                'default_weight' => 1,
            ],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.settings.nova-poshta.update'), [
                'is_active' => true,
                'api_key' => '',
                'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
                'sender_city_ref' => 'updated-city-ref',
                'sender_city_name' => 'Київ',
                'sender_warehouse_ref' => 'sender-warehouse-ref',
                'sender_warehouse_name' => 'Відділення №1',
                'default_weight' => '2',
            ])
            ->assertRedirect(route('admin.settings.nova-poshta.edit'));

        $provider->refresh();

        $this->assertSame('secret-np-token', $provider->settings['api_key']);
        $this->assertSame('updated-city-ref', $provider->settings['sender_city_ref']);
        $this->assertEquals(2.0, $provider->settings['default_weight']);

        $this->actingAs($user)
            ->get(route('admin.settings.nova-poshta.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/NovaPoshta', false)
                ->where('settings.has_api_key', true)
                ->where('settings.sender_city_name', 'Київ')
                ->missing('settings.api_key'));
    }

    public function test_admin_can_sync_sender_and_contact_refs_from_nova_poshta_api(): void
    {
        $user = User::factory()->create();
        $provider = ShippingProviderSetting::query()->create([
            'code' => 'nova_poshta',
            'name' => 'Нова пошта',
            'settings' => [
                'api_key' => 'secret-np-token',
                'api_url' => 'https://api.novaposhta.ua/v2.0/json/',
            ],
            'is_active' => true,
        ]);

        Http::fake([
            'api.novaposhta.ua/*' => Http::sequence()
                ->push([
                    'success' => true,
                    'data' => [
                        [
                            'Ref' => 'sender-ref-from-api',
                            'Description' => 'ФОП Тест',
                        ],
                    ],
                ])
                ->push([
                    'success' => true,
                    'data' => [
                        [
                            'Ref' => 'contact-ref-from-api',
                            'Description' => 'Іван Тестовий',
                            'Phones' => '380680000000',
                        ],
                    ],
                ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.settings.nova-poshta.sync-sender'))
            ->assertOk()
            ->assertJsonPath('sender_ref', 'sender-ref-from-api')
            ->assertJsonPath('contact_ref', 'contact-ref-from-api')
            ->assertJsonPath('sender_phone', '380680000000');

        $provider->refresh();

        $this->assertSame('sender-ref-from-api', $provider->settings['sender_ref']);
        $this->assertSame('contact-ref-from-api', $provider->settings['contact_ref']);
        $this->assertSame('380680000000', $provider->settings['sender_phone']);

        Http::assertSent(fn ($request): bool => $request['apiKey'] === 'secret-np-token'
            && $request['modelName'] === 'Counterparty'
            && $request['calledMethod'] === 'getCounterparties'
            && $request['methodProperties']['CounterpartyProperty'] === 'Sender');

        Http::assertSent(fn ($request): bool => $request['modelName'] === 'Counterparty'
            && $request['calledMethod'] === 'getCounterpartyContactPersons'
            && $request['methodProperties']['Ref'] === 'sender-ref-from-api');
    }
}
