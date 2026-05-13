<?php

namespace App\Support\Admin;

use App\Models\MarketingEventOutbox;
use App\Models\MarketingIntegration;
use App\Models\MarketingIntegrationCredential;
use App\Models\MarketingIntegrationSetting;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class MarketingIntegrationConfig
{
    public const CHANNELS = [
        'google' => [
            'label' => 'Google',
            'badge' => 'GA4 / Google Ads',
            'color' => '#ef4444',
            'description' => 'Події GA4, Google Ads конверсії, UTM і ROAS по пошуку та Performance Max.',
            'events' => ['page_view', 'view_item', 'add_to_cart', 'begin_checkout', 'purchase'],
            'metrics' => ['Сесії', 'Конверсії', 'Дохід', 'CPA', 'ROAS'],
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'badge' => 'Pixel / Events API',
            'color' => '#ec4899',
            'description' => 'TikTok Pixel, server-side Events API, ttclid і атрибуція замовлень з реклами.',
            'events' => ['ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase'],
            'metrics' => ['Кліки', 'ATC', 'Покупки', 'CPA', 'ROAS'],
        ],
        'meta' => [
            'label' => 'Meta',
            'badge' => 'Pixel / CAPI',
            'color' => '#2563eb',
            'description' => 'Meta Pixel, Conversions API, fbc/fbp, deduplication event_id і якість CAPI подій.',
            'events' => ['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase'],
            'metrics' => ['Події', 'Match quality', 'Покупки', 'CPA', 'ROAS'],
        ],
    ];

    public function providers(): array
    {
        return array_keys(self::CHANNELS);
    }

    public function channel(string $provider): array
    {
        return self::CHANNELS[$provider];
    }

    public function ensureIntegration(string $provider, ?int $actorId): MarketingIntegration
    {
        $integration = MarketingIntegration::query()->firstOrCreate(
            ['provider' => $provider],
            [
                'status' => MarketingIntegration::STATUS_DISABLED,
                'mode' => MarketingIntegration::MODE_PROD,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );

        $integration->loadMissing(['settings', 'credentials']);

        return $integration;
    }

    public function integrationPayload(string $provider, ?MarketingIntegration $integration): array
    {
        $settings = array_replace($this->defaultSettings($provider), $integration?->settings?->settings ?? []);
        $credentials = $this->credentialsPayload($integration);
        $snapshot = $this->snapshot($provider, $integration, $settings, $credentials);

        return [
            'provider' => $provider,
            'status' => $integration?->status ?? MarketingIntegration::STATUS_DISABLED,
            'mode' => $integration?->mode ?? MarketingIntegration::MODE_PROD,
            'status_label' => $snapshot['configured'] ? 'Підключено' : 'Не підключено',
            'configured' => $snapshot['configured'],
            'browser_ready' => $snapshot['browser_ready'],
            'server_ready' => $snapshot['server_ready'],
            'last_error' => $snapshot['last_error'],
            'last_event' => $snapshot['last_event'],
            'events_summary' => $snapshot['events_summary'],
            'settings' => $settings,
            'credentials' => $credentials,
            'next_step' => $snapshot['configured']
                ? 'Наступний крок: підключити storefront-події й server-side відправку через outbox.'
                : 'Заповни дані підключення, щоб активувати browser tracking і server-side events.',
        ];
    }

    public function rules(string $provider): array
    {
        $rules = [
            'status' => ['required', Rule::in(MarketingIntegration::STATUSES)],
            'mode' => ['required', Rule::in(MarketingIntegration::MODES)],
            'send_client' => ['nullable', 'boolean'],
            'send_server' => ['nullable', 'boolean'],
        ];

        if ($provider === MarketingIntegration::PROVIDER_GOOGLE) {
            return $rules + [
                'measurement_id' => ['nullable', 'string', 'max:120'],
                'gtm_container_id' => ['nullable', 'string', 'max:120'],
                'ads_conversion_id' => ['nullable', 'string', 'max:120'],
                'ads_conversion_label' => ['nullable', 'string', 'max:120'],
                'ads_api_customer_id' => ['nullable', 'string', 'max:80'],
                'ads_api_login_customer_id' => ['nullable', 'string', 'max:80'],
                'ads_api_conversion_action_id' => ['nullable', 'string', 'max:80'],
                'api_secret' => ['nullable', 'string', 'max:2048'],
                'api_secret_clear' => ['nullable', 'boolean'],
                'ads_developer_token' => ['nullable', 'string', 'max:2048'],
                'ads_developer_token_clear' => ['nullable', 'boolean'],
                'ads_service_account_json' => ['nullable', 'json', 'max:65535'],
                'ads_service_account_json_clear' => ['nullable', 'boolean'],
            ];
        }

        return $rules + [
            'pixel_id' => ['nullable', 'string', 'max:120'],
            'access_token' => ['nullable', 'string', 'max:4096'],
            'access_token_clear' => ['nullable', 'boolean'],
            'test_event_code' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function settingsPayload(string $provider, array $data): array
    {
        $base = [
            'send_client' => (bool) ($data['send_client'] ?? false),
            'send_server' => (bool) ($data['send_server'] ?? false),
        ];

        if ($provider === MarketingIntegration::PROVIDER_GOOGLE) {
            return $base + [
                'measurement_id' => $this->blankToNull($data['measurement_id'] ?? null),
                'gtm_container_id' => $this->blankToNull($data['gtm_container_id'] ?? null),
                'ads_conversion_id' => $this->blankToNull($data['ads_conversion_id'] ?? null),
                'ads_conversion_label' => $this->blankToNull($data['ads_conversion_label'] ?? null),
                'ads_api_customer_id' => $this->blankToNull($data['ads_api_customer_id'] ?? null),
                'ads_api_login_customer_id' => $this->blankToNull($data['ads_api_login_customer_id'] ?? null),
                'ads_api_conversion_action_id' => $this->blankToNull($data['ads_api_conversion_action_id'] ?? null),
            ];
        }

        return $base + [
            'pixel_id' => $this->blankToNull($data['pixel_id'] ?? null),
            'test_event_code' => $this->blankToNull($data['test_event_code'] ?? null),
        ];
    }

    public function persistSettings(MarketingIntegration $integration, string $provider, array $data, ?int $actorId): array
    {
        $settings = $this->settingsPayload($provider, $data);
        $currentSettings = $integration->settings?->settings ?? [];
        $settingsChanged = $this->normalizeForCompare($currentSettings) !== $this->normalizeForCompare($settings);
        $statusChanged = $integration->status !== $data['status'];
        $modeChanged = $integration->mode !== $data['mode'];

        $integration->fill([
            'status' => $data['status'],
            'mode' => $data['mode'],
            'updated_by' => $actorId,
        ])->save();

        MarketingIntegrationSetting::query()->updateOrCreate(
            ['marketing_integration_id' => $integration->id],
            ['settings' => $settings],
        );

        return [
            'settings_changed' => $settingsChanged,
            'status_changed' => $statusChanged,
            'mode_changed' => $modeChanged,
            'credentials' => $this->persistCredentials($integration, $provider, $data, $actorId),
        ];
    }

    public function formSchema(string $provider): array
    {
        $common = [
            ['name' => 'status', 'label' => 'API активне', 'type' => 'toggle'],
            ['name' => 'mode', 'label' => 'Тестовий режим', 'type' => 'mode'],
            ['name' => 'send_client', 'label' => 'Browser tracking', 'type' => 'toggle'],
            ['name' => 'send_server', 'label' => 'Server API', 'type' => 'toggle'],
        ];

        if ($provider === MarketingIntegration::PROVIDER_GOOGLE) {
            return [
                'fields' => [
                    ...$common,
                    ['name' => 'measurement_id', 'label' => 'GA4 Measurement ID', 'type' => 'text', 'placeholder' => 'G-XXXXXXXXXX'],
                    ['name' => 'gtm_container_id', 'label' => 'GTM Container ID', 'type' => 'text', 'placeholder' => 'GTM-XXXXXXX'],
                    ['name' => 'ads_conversion_id', 'label' => 'Google Ads Conversion ID', 'type' => 'text', 'placeholder' => 'AW-000000000'],
                    ['name' => 'ads_conversion_label', 'label' => 'Conversion Label', 'type' => 'text', 'placeholder' => 'xxxxxxxxxxxx'],
                    ['name' => 'api_secret', 'label' => 'GA4 API Secret', 'type' => 'secret', 'placeholder' => 'Встав API secret'],
                    ['name' => 'ads_api_customer_id', 'label' => 'Ads Customer ID', 'type' => 'text', 'placeholder' => '1234567890'],
                    ['name' => 'ads_api_login_customer_id', 'label' => 'Login Customer ID', 'type' => 'text', 'placeholder' => '1234567890'],
                    ['name' => 'ads_api_conversion_action_id', 'label' => 'Conversion Action ID', 'type' => 'text', 'placeholder' => '987654321'],
                    ['name' => 'ads_developer_token', 'label' => 'Developer Token', 'type' => 'secret', 'placeholder' => 'Встав developer token'],
                    ['name' => 'ads_service_account_json', 'label' => 'Service Account JSON', 'type' => 'textarea_secret', 'placeholder' => '{"type":"service_account",...}'],
                ],
            ];
        }

        return [
            'fields' => [
                ...$common,
                ['name' => 'pixel_id', 'label' => 'Pixel ID', 'type' => 'text', 'placeholder' => 'Встав Pixel ID'],
                ['name' => 'access_token', 'label' => 'Access Token', 'type' => 'secret', 'placeholder' => 'Встав access token'],
                ['name' => 'test_event_code', 'label' => 'Test Event Code', 'type' => 'text', 'placeholder' => 'TEST1234'],
            ],
        ];
    }

    public function defaultSettings(string $provider): array
    {
        if ($provider === MarketingIntegration::PROVIDER_GOOGLE) {
            return [
                'send_client' => false,
                'send_server' => false,
                'measurement_id' => null,
                'gtm_container_id' => null,
                'ads_conversion_id' => null,
                'ads_conversion_label' => null,
                'ads_api_customer_id' => null,
                'ads_api_login_customer_id' => null,
                'ads_api_conversion_action_id' => null,
            ];
        }

        return [
            'send_client' => false,
            'send_server' => false,
            'pixel_id' => null,
            'test_event_code' => null,
        ];
    }

    public function sourceAliases(string $provider): array
    {
        return array_values(array_filter(array_unique([
            $provider,
            self::CHANNELS[$provider]['label'],
            mb_strtolower(self::CHANNELS[$provider]['label']),
            $provider === MarketingIntegration::PROVIDER_META ? 'facebook' : null,
        ])));
    }

    private function snapshot(string $provider, ?MarketingIntegration $integration, array $settings, array $credentials): array
    {
        $isActive = $integration?->status === MarketingIntegration::STATUS_ACTIVE;
        $sendClient = (bool) ($settings['send_client'] ?? false);
        $sendServer = (bool) ($settings['send_server'] ?? false);
        $hasMainId = match ($provider) {
            MarketingIntegration::PROVIDER_GOOGLE => trim((string) ($settings['measurement_id'] ?? '')) !== ''
                || trim((string) ($settings['gtm_container_id'] ?? '')) !== ''
                || trim((string) ($settings['ads_conversion_id'] ?? '')) !== '',
            default => trim((string) ($settings['pixel_id'] ?? '')) !== '',
        };
        $hasServerSecret = match ($provider) {
            MarketingIntegration::PROVIDER_GOOGLE => ($credentials['api_secret']['exists'] ?? false)
                || (($credentials['ads_developer_token']['exists'] ?? false) && ($credentials['ads_service_account_json']['exists'] ?? false)),
            default => $credentials['access_token']['exists'] ?? false,
        };

        $browserReady = $isActive && $sendClient && $hasMainId;
        $serverReady = $isActive && $sendServer && $hasMainId && $hasServerSecret;
        $configured = $browserReady || $serverReady;
        $eventsSummary = ['total' => 0, 'sent' => 0, 'queued' => 0, 'processing' => 0, 'failed' => 0];
        $lastEvent = null;
        $lastError = null;

        if ($integration) {
            $counts = MarketingEventOutbox::query()
                ->where('marketing_integration_id', $integration->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $eventsSummary = [
                'total' => (int) $counts->sum(),
                'sent' => (int) ($counts['sent'] ?? 0),
                'queued' => (int) ($counts['queued'] ?? 0),
                'processing' => (int) ($counts['processing'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
            ];

            $lastEvent = MarketingEventOutbox::query()
                ->where('marketing_integration_id', $integration->id)
                ->latest('id')
                ->first(['event_name', 'event_id', 'status', 'last_error', 'created_at', 'sent_at'])
                ?->toArray();

            $lastError = MarketingEventOutbox::query()
                ->where('marketing_integration_id', $integration->id)
                ->whereNotNull('last_error')
                ->latest('id')
                ->value('last_error');
        }

        return [
            'configured' => $configured,
            'browserReady' => $browserReady,
            'serverReady' => $serverReady,
            'browser_ready' => $browserReady,
            'server_ready' => $serverReady,
            'events_summary' => $eventsSummary,
            'last_event' => $lastEvent,
            'last_error' => $lastError,
        ];
    }

    private function credentialsPayload(?MarketingIntegration $integration): array
    {
        $payload = [];

        foreach ($integration?->credentials ?? [] as $credential) {
            $payload[$credential->secret_type] = [
                'exists' => (bool) $credential->secret_value,
                'masked' => $credential->secret_last_four ? '••••'.$credential->secret_last_four : '',
                'updated_at' => $credential->updated_at?->toDateTimeString(),
            ];
        }

        return $payload;
    }

    private function persistCredentials(MarketingIntegration $integration, string $provider, array $data, ?int $actorId): array
    {
        $types = $provider === MarketingIntegration::PROVIDER_GOOGLE
            ? ['api_secret', 'ads_developer_token', 'ads_service_account_json']
            : ['access_token'];
        $actions = [];

        foreach ($types as $type) {
            $actions[$type] = 'unchanged';

            if ((bool) ($data[$type.'_clear'] ?? false)) {
                MarketingIntegrationCredential::query()
                    ->where('marketing_integration_id', $integration->id)
                    ->where('secret_type', $type)
                    ->delete();
                $actions[$type] = 'cleared';
                continue;
            }

            $value = trim((string) ($data[$type] ?? ''));
            if ($value === '') {
                continue;
            }

            MarketingIntegrationCredential::query()->updateOrCreate(
                [
                    'marketing_integration_id' => $integration->id,
                    'secret_type' => $type,
                ],
                [
                    'secret_value' => $value,
                    'secret_last_four' => mb_substr($value, -4),
                    'last_rotated_at' => now(),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ],
            );

            $actions[$type] = 'updated';
        }

        return $actions;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeForCompare(array $value): array
    {
        Arr::sortRecursive($value);

        return $value;
    }
}
