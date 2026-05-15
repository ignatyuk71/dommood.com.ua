<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteSettingsService
{
    private const CACHE_PREFIX = 'site_settings.';

    public function sections(): array
    {
        return [
            'store' => [
                'label' => 'Магазин',
                'description' => 'Базові дані бренду, контактів і режиму роботи.',
            ],
            'checkout' => [
                'label' => 'Checkout',
                'description' => 'Правила оформлення замовлення і дефолтні статуси.',
            ],
            'integrations' => [
                'label' => 'Інтеграції',
                'description' => 'Email, месенджери і зовнішні webhook підключення.',
            ],
            'payments' => [
                'label' => 'Платежі',
                'description' => 'Підключення платіжних провайдерів: LiqPay, Monobank та інші.',
            ],
            'security' => [
                'label' => 'Безпека',
                'description' => 'Обмеження для персоналу, сесій і входів.',
            ],
            'system' => [
                'label' => 'Система',
                'description' => 'Технічні прапорці, кеш, sitemap, feeds і retention.',
            ],
        ];
    }

    public function allowedSections(): array
    {
        return array_keys($this->sections());
    }

    public function defaults(string $section): array
    {
        return match ($section) {
            'store' => [
                'store_name' => 'DomMood',
                'legal_name' => '',
                'domain' => config('app.url'),
                'support_email' => '',
                'support_phone' => '',
                'currency' => 'UAH',
                'timezone' => config('app.timezone', 'Europe/Kyiv'),
                'maintenance_mode' => false,
                'maintenance_message' => '',
            ],
            'checkout' => [
                'guest_checkout' => true,
                'account_creation_mode' => 'optional',
                'require_phone' => true,
                'require_email' => false,
                'require_last_name' => false,
                'default_order_status' => 'awaiting_confirmation',
                'min_order_amount' => '0.00',
                'terms_url' => '/terms',
                'privacy_url' => '/privacy-policy',
                'one_click_enabled' => true,
            ],
            'integrations' => [
                'smtp_host' => '',
                'smtp_port' => '',
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'mail_from_email' => '',
                'mail_from_name' => 'DomMood',
                'telegram_bot_token' => '',
                'telegram_chat_id' => '',
                'google_sheets_webhook' => '',
            ],
            'payments' => [
                'liqpay_enabled' => false,
                'liqpay_mode' => 'test',
                'liqpay_public_key' => '',
                'liqpay_private_key' => '',
                'liqpay_language' => 'uk',
                'liqpay_server_url' => url('/payments/liqpay/callback'),
                'liqpay_result_url' => url('/payments/liqpay/result'),
                'monobank_enabled' => false,
                'monobank_mode' => 'test',
                'monobank_token' => '',
                'monobank_merchant_id' => '',
                'monobank_webhook_url' => url('/payments/monobank/callback'),
                'monobank_result_url' => url('/payments/monobank/result'),
            ],
            'payment_delivery' => [
                'free_shipping_threshold' => '1200.00',
            ],
            'security' => [
                'admin_2fa_required' => false,
                'manager_ip_allowlist' => '',
                'session_lifetime_minutes' => 120,
                'password_rotation_days' => '',
                'login_alerts' => true,
                'staff_invites_enabled' => true,
            ],
            'system' => [
                'cache_ttl_minutes' => 60,
                'log_retention_days' => 30,
                'sitemap_auto_refresh' => true,
                'feed_auto_refresh' => true,
                'image_cleanup_enabled' => true,
                'maintenance_contact' => '',
            ],
            default => [],
        };
    }

    public function get(string $section): array
    {
        return Cache::rememberForever($this->cacheKey($section), function () use ($section): array {
            $setting = SiteSetting::query()->where('section', $section)->first();

            $defaults = $this->defaults($section);

            if ($section === 'payments' && ! $setting) {
                $defaults = array_replace($defaults, $this->legacyPaymentProviderSettings());
            }

            return array_replace($defaults, $setting?->payload ?? []);
        });
    }

    public function set(string $section, array $payload): array
    {
        $settings = array_replace($this->defaults($section), $payload);

        SiteSetting::query()->updateOrCreate(
            ['section' => $section],
            ['payload' => $settings],
        );

        Cache::forget($this->cacheKey($section));

        return $this->get($section);
    }

    public function hasSecret(string $section, string $key): bool
    {
        $settings = $this->get($section);

        return filled($settings[$key] ?? null);
    }

    public function maskedSecret(string $section, string $key): string
    {
        $value = (string) ($this->get($section)[$key] ?? '');

        if ($value === '') {
            return '';
        }

        return '••••'.substr($value, -4);
    }

    private function cacheKey(string $section): string
    {
        return self::CACHE_PREFIX.$section;
    }

    private function legacyPaymentProviderSettings(): array
    {
        if (! Schema::hasTable('payment_methods')) {
            return [];
        }

        $liqPay = PaymentMethod::query()
            ->where('type', 'liqpay')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();

        if (! $liqPay) {
            return [];
        }

        $public = $liqPay->settings ?? [];
        $secret = $liqPay->secret_settings ?? [];

        return [
            'liqpay_enabled' => (bool) $liqPay->is_active,
            'liqpay_mode' => $public['mode'] ?? 'test',
            'liqpay_public_key' => $public['public_key'] ?? '',
            'liqpay_private_key' => $secret['private_key'] ?? '',
            'liqpay_language' => $public['language'] ?? 'uk',
            'liqpay_server_url' => $public['server_url'] ?? url('/payments/liqpay/callback'),
            'liqpay_result_url' => $public['result_url'] ?? url('/payments/liqpay/result'),
        ];
    }
}
