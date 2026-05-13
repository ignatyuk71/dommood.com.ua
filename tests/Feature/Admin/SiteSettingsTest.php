<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_store_settings(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.settings.site.show', 'store'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/SiteSettings')
                ->where('section', 'store')
                ->where('meta.title', 'Магазин')
                ->where('settings.store_name', 'DomMood')
                ->has('tabs', 6)
                ->has('schema.groups', 2)
            );
    }

    public function test_admin_can_save_store_settings_and_cache_is_refreshed(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $service = app(SiteSettingsService::class);
        $this->assertSame('DomMood', $service->get('store')['store_name']);

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update', 'store'), [
                'store_name' => 'DomMood UA',
                'legal_name' => 'ФОП DomMood',
                'domain' => 'https://dommood.com.ua',
                'support_email' => 'support@dommood.com.ua',
                'support_phone' => '+380991110000',
                'currency' => 'UAH',
                'timezone' => 'Europe/Kyiv',
                'maintenance_mode' => true,
                'maintenance_message' => 'Проводимо технічні роботи.',
            ])
            ->assertRedirect(route('admin.settings.site.show', 'store'));

        $settings = $service->get('store');

        $this->assertSame('DomMood UA', $settings['store_name']);
        $this->assertTrue($settings['maintenance_mode']);
        $this->assertSame('support@dommood.com.ua', $settings['support_email']);
    }

    public function test_integration_secrets_are_masked_and_empty_value_does_not_replace_existing_secret(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update', 'integrations'), [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_username' => 'mailer@example.com',
                'smtp_password' => 'smtp-secret-1234',
                'smtp_encryption' => 'tls',
                'mail_from_email' => 'hello@dommood.com.ua',
                'mail_from_name' => 'DomMood',
                'telegram_bot_token' => 'telegram-secret-5678',
                'telegram_chat_id' => '-100123',
                'google_sheets_webhook' => 'https://example.com/webhook',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.settings.site.show', 'integrations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.smtp_password', '')
                ->where('settings.telegram_bot_token', '')
                ->where('schema.groups.0.description', 'Підключення поштової скриньки для системних листів: реєстрація, відновлення пароля, статуси замовлень.')
                ->where('schema.groups.0.fields.0.hint', 'Адреса SMTP-сервера пошти. Наприклад: smtp.gmail.com, smtp.sendgrid.net або сервер від хостингу.')
                ->where('schema.groups.1.fields.2.hint', 'URL webhook/Apps Script, якщо треба передавати замовлення або звіти в Google Sheets. Можна залишити порожнім.')
                ->where('schema.groups.0.fields.3.secret.exists', true)
                ->where('schema.groups.0.fields.3.secret.masked', '••••1234')
                ->where('schema.groups.1.fields.0.secret.exists', true)
                ->where('schema.groups.1.fields.0.secret.masked', '••••5678')
            );

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update', 'integrations'), [
                'smtp_host' => 'smtp2.example.com',
                'smtp_port' => 465,
                'smtp_username' => 'mailer2@example.com',
                'smtp_password' => '',
                'smtp_encryption' => 'ssl',
                'mail_from_email' => 'hello@dommood.com.ua',
                'mail_from_name' => 'DomMood',
                'telegram_bot_token' => '',
                'telegram_chat_id' => '-100123',
                'google_sheets_webhook' => 'https://example.com/webhook',
            ])
            ->assertRedirect();

        $payload = SiteSetting::query()->where('section', 'integrations')->firstOrFail()->payload;

        $this->assertSame('smtp-secret-1234', $payload['smtp_password']);
        $this->assertSame('telegram-secret-5678', $payload['telegram_bot_token']);
        $this->assertSame('smtp2.example.com', $payload['smtp_host']);
    }

    public function test_payment_provider_connections_are_configured_in_settings(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update', 'payments'), [
                'liqpay_enabled' => true,
                'liqpay_mode' => 'test',
                'liqpay_public_key' => 'public-key',
                'liqpay_private_key' => 'private-key-1234',
                'liqpay_language' => 'uk',
                'liqpay_server_url' => route('payments.liqpay.callback'),
                'liqpay_result_url' => route('payments.liqpay.result'),
                'monobank_enabled' => true,
                'monobank_mode' => 'test',
                'monobank_token' => 'mono-token-5678',
                'monobank_merchant_id' => 'merchant-1',
                'monobank_webhook_url' => url('/payments/monobank/callback'),
                'monobank_result_url' => url('/payments/monobank/result'),
            ])
            ->assertRedirect(route('admin.settings.site.show', 'payments'));

        $this->actingAs($admin)
            ->get(route('admin.settings.site.show', 'payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/SiteSettings')
                ->where('section', 'payments')
                ->where('settings.liqpay_private_key', '')
                ->where('settings.monobank_token', '')
                ->where('schema.summary.0.value', 'Підключено')
                ->where('schema.summary.1.value', 'Підключено')
                ->where('schema.groups.0.fields.4.secret.exists', true)
                ->where('schema.groups.0.fields.4.secret.masked', '••••1234')
                ->where('schema.groups.1.fields.3.secret.exists', true)
                ->where('schema.groups.1.fields.3.secret.masked', '••••5678')
            );

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update', 'payments'), [
                'liqpay_enabled' => true,
                'liqpay_mode' => 'live',
                'liqpay_public_key' => 'public-key-live',
                'liqpay_private_key' => '',
                'liqpay_language' => 'uk',
                'liqpay_server_url' => route('payments.liqpay.callback'),
                'liqpay_result_url' => route('payments.liqpay.result'),
                'monobank_enabled' => false,
                'monobank_mode' => 'test',
                'monobank_token' => '',
                'monobank_merchant_id' => 'merchant-2',
                'monobank_webhook_url' => url('/payments/monobank/callback'),
                'monobank_result_url' => url('/payments/monobank/result'),
            ])
            ->assertRedirect();

        $payload = SiteSetting::query()->where('section', 'payments')->firstOrFail()->payload;

        $this->assertSame('private-key-1234', $payload['liqpay_private_key']);
        $this->assertSame('mono-token-5678', $payload['monobank_token']);
        $this->assertSame('public-key-live', $payload['liqpay_public_key']);
        $this->assertFalse($payload['monobank_enabled']);
    }

    public function test_manager_cannot_manage_site_settings_without_permission(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.settings.site.show', 'store'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('admin.settings.site.update', 'store'), [
                'store_name' => 'Blocked',
                'currency' => 'UAH',
                'timezone' => 'Europe/Kyiv',
            ])
            ->assertForbidden();
    }

    public function test_public_home_uses_store_maintenance_mode(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('storefront.home');

        app(SiteSettingsService::class)->set('store', [
            'maintenance_mode' => true,
            'maintenance_message' => 'Тестове повідомлення для клієнтів.',
            'store_name' => 'DomMood Test',
        ]);

        $this->get('/')
            ->assertStatus(503)
            ->assertViewIs('storefront.maintenance')
            ->assertViewHas('storeName', 'DomMood Test')
            ->assertViewHas('message', 'Тестове повідомлення для клієнтів.');
    }

    public function test_staff_can_preview_public_home_during_maintenance(): void
    {
        app(SiteSettingsService::class)->set('store', [
            'maintenance_mode' => true,
            'maintenance_message' => 'Тестове повідомлення для клієнтів.',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertViewIs('storefront.home');

        $this->actingAs($manager)
            ->get('/')
            ->assertOk()
            ->assertViewIs('storefront.home');

        $this->actingAs($customer)
            ->get('/')
            ->assertStatus(503)
            ->assertViewIs('storefront.maintenance');
    }
}
