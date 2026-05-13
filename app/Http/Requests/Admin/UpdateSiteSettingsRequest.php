<?php

namespace App\Http\Requests\Admin;

use App\Services\SiteSettingsService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->sectionPermission((string) $this->route('section'))) ?? false;
    }

    public function rules(): array
    {
        $section = (string) $this->route('section');

        return match ($section) {
            'store' => [
                'store_name' => ['required', 'string', 'max:120'],
                'legal_name' => ['nullable', 'string', 'max:160'],
                'domain' => ['nullable', 'url', 'max:255'],
                'support_email' => ['nullable', 'email', 'max:160'],
                'support_phone' => ['nullable', 'string', 'max:40'],
                'currency' => ['required', Rule::in(['UAH', 'PLN', 'USD', 'EUR'])],
                'timezone' => ['required', 'string', 'max:80'],
                'maintenance_mode' => ['boolean'],
                'maintenance_message' => ['nullable', 'string', 'max:500'],
            ],
            'checkout' => [
                'guest_checkout' => ['boolean'],
                'account_creation_mode' => ['required', Rule::in(['optional', 'after_order', 'required'])],
                'require_phone' => ['boolean'],
                'require_email' => ['boolean'],
                'require_last_name' => ['boolean'],
                'default_order_status' => ['required', Rule::in(['awaiting_confirmation', 'pending_payment', 'processing', 'new'])],
                'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
                'terms_url' => ['nullable', 'string', 'max:255'],
                'privacy_url' => ['nullable', 'string', 'max:255'],
                'one_click_enabled' => ['boolean'],
            ],
            'integrations' => [
                'smtp_host' => ['nullable', 'string', 'max:160'],
                'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'smtp_username' => ['nullable', 'string', 'max:190'],
                'smtp_password' => ['nullable', 'string', 'max:500'],
                'smtp_password_clear' => ['boolean'],
                'smtp_encryption' => ['nullable', Rule::in(['none', 'tls', 'ssl'])],
                'mail_from_email' => ['nullable', 'email', 'max:160'],
                'mail_from_name' => ['nullable', 'string', 'max:120'],
                'telegram_bot_token' => ['nullable', 'string', 'max:500'],
                'telegram_bot_token_clear' => ['boolean'],
                'telegram_chat_id' => ['nullable', 'string', 'max:120'],
                'google_sheets_webhook' => ['nullable', 'url', 'max:500'],
            ],
            'payments' => [
                'liqpay_enabled' => ['boolean'],
                'liqpay_mode' => ['required', Rule::in(['test', 'live'])],
                'liqpay_public_key' => ['nullable', 'string', 'max:255'],
                'liqpay_private_key' => ['nullable', 'string', 'max:500'],
                'liqpay_private_key_clear' => ['boolean'],
                'liqpay_language' => ['required', Rule::in(['uk', 'en'])],
                'liqpay_server_url' => ['nullable', 'url', 'max:500'],
                'liqpay_result_url' => ['nullable', 'url', 'max:500'],
                'monobank_enabled' => ['boolean'],
                'monobank_mode' => ['required', Rule::in(['test', 'live'])],
                'monobank_token' => ['nullable', 'string', 'max:1000'],
                'monobank_token_clear' => ['boolean'],
                'monobank_merchant_id' => ['nullable', 'string', 'max:255'],
                'monobank_webhook_url' => ['nullable', 'url', 'max:500'],
                'monobank_result_url' => ['nullable', 'url', 'max:500'],
            ],
            'security' => [
                'admin_2fa_required' => ['boolean'],
                'manager_ip_allowlist' => ['nullable', 'string', 'max:2000'],
                'session_lifetime_minutes' => ['required', 'integer', 'min:15', 'max:43200'],
                'password_rotation_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'login_alerts' => ['boolean'],
                'staff_invites_enabled' => ['boolean'],
            ],
            'system' => [
                'cache_ttl_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
                'log_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
                'sitemap_auto_refresh' => ['boolean'],
                'feed_auto_refresh' => ['boolean'],
                'image_cleanup_enabled' => ['boolean'],
                'maintenance_contact' => ['nullable', 'string', 'max:160'],
            ],
            default => [
                'section' => ['prohibited'],
            ],
        };
    }

    public function validatedSettings(SiteSettingsService $settings): array
    {
        $section = (string) $this->route('section');
        abort_unless(in_array($section, $settings->allowedSections(), true), 404);

        $data = $this->validated();

        foreach ($this->booleanFields($section) as $field) {
            $data[$field] = $this->boolean($field);
        }

        if (in_array($section, ['integrations', 'payments'], true)) {
            $current = $settings->get($section);

            foreach ($this->secretFields($section) as $secret) {
                if ($this->boolean("{$secret}_clear")) {
                    $data[$secret] = '';
                    continue;
                }

                if (($data[$secret] ?? '') === '') {
                    $data[$secret] = $current[$secret] ?? '';
                }
            }

            foreach ($this->secretFields($section) as $secret) {
                unset($data["{$secret}_clear"]);
            }
        }

        return $data;
    }

    private function booleanFields(string $section): array
    {
        return match ($section) {
            'store' => ['maintenance_mode'],
            'checkout' => ['guest_checkout', 'require_phone', 'require_email', 'require_last_name', 'one_click_enabled'],
            'payments' => ['liqpay_enabled', 'monobank_enabled'],
            'security' => ['admin_2fa_required', 'login_alerts', 'staff_invites_enabled'],
            'system' => ['sitemap_auto_refresh', 'feed_auto_refresh', 'image_cleanup_enabled'],
            default => [],
        };
    }

    private function secretFields(string $section): array
    {
        return match ($section) {
            'integrations' => ['smtp_password', 'telegram_bot_token'],
            'payments' => ['liqpay_private_key', 'monobank_token'],
            default => [],
        };
    }

    private function sectionPermission(string $section): string
    {
        return match ($section) {
            'store' => AdminPermissions::SETTINGS_STORE_MANAGE,
            'checkout' => AdminPermissions::SETTINGS_CHECKOUT_MANAGE,
            'integrations' => AdminPermissions::SETTINGS_INTEGRATIONS_MANAGE,
            'payments' => AdminPermissions::SETTINGS_PAYMENTS_MANAGE,
            'security' => AdminPermissions::SETTINGS_SECURITY_MANAGE,
            'system' => AdminPermissions::SETTINGS_SYSTEM_MANAGE,
            default => AdminPermissions::SETTINGS_STORE_MANAGE,
        };
    }
}
