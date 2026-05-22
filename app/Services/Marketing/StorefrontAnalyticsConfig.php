<?php

namespace App\Services\Marketing;

use App\Models\MarketingIntegration;
use App\Support\Marketing\MarketingSourceRouter;
use Illuminate\Http\Request;
use Throwable;

class StorefrontAnalyticsConfig
{
    public function __construct(private readonly MarketingSourceRouter $sourceRouter) {}

    public function storefront(Request $request, ?array $attribution = null): array
    {
        $attribution ??= $this->sourceRouter->capture($request);
        $providers = $this->providers();

        return [
            'google' => $this->google($providers[MarketingIntegration::PROVIDER_GOOGLE] ?? null),
            'meta' => $this->pixelProvider($providers[MarketingIntegration::PROVIDER_META] ?? null),
            'tiktok' => $this->pixelProvider($providers[MarketingIntegration::PROVIDER_TIKTOK] ?? null),
            'routing' => [
                'strict' => true,
                'source' => $attribution['source'],
                'channel' => $attribution['channel'],
                'allowed' => $this->sourceRouter->allowedProviders($attribution),
            ],
        ];
    }

    private function providers(): array
    {
        try {
            return MarketingIntegration::query()
                ->with(['settings', 'credentials'])
                ->whereIn('provider', MarketingIntegration::providers())
                ->get()
                ->keyBy('provider')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function google(?MarketingIntegration $integration): array
    {
        $settings = $integration?->settings?->settings ?? [];
        $measurementId = $this->value($settings['measurement_id'] ?? config('services.google_analytics.measurement_id'));
        $gtmContainerId = $this->value($settings['gtm_container_id'] ?? config('services.google_analytics.gtm_container_id'));
        $adsConversionId = $this->value($settings['ads_conversion_id'] ?? null);
        $adsConversionLabel = $this->value($settings['ads_conversion_label'] ?? null);
        $isActive = $integration?->status === MarketingIntegration::STATUS_ACTIVE;
        $credentials = $integration?->credentials;
        $hasAdsServerCredentials = $credentials
            && (bool) $credentials->firstWhere('secret_type', 'ads_developer_token')?->secret_value
            && (bool) $credentials->firstWhere('secret_type', 'ads_service_account_json')?->secret_value;
        $serverAdsReady = $isActive
            && (bool) ($settings['send_server'] ?? false)
            && $this->digits((string) ($settings['ads_api_customer_id'] ?? '')) !== ''
            && $this->digits((string) ($settings['ads_api_conversion_action_id'] ?? '')) !== ''
            && $hasAdsServerCredentials;
        $hasIntegration = $integration !== null;
        $sendClient = (bool) ($settings['send_client'] ?? false);
        $enabled = $hasIntegration
            ? $isActive && $sendClient && ($measurementId !== null || $gtmContainerId !== null || $adsConversionId !== null)
            : $measurementId !== null || $gtmContainerId !== null;

        return [
            'enabled' => $enabled,
            'measurement_id' => $measurementId,
            'gtm_container_id' => $gtmContainerId,
            'ads_conversion_id' => $adsConversionId,
            'ads_conversion_label' => $adsConversionLabel,
            'send_client' => $enabled,
            'send_server' => (bool) ($settings['send_server'] ?? false),
            'server_ads_enabled' => $serverAdsReady,
            'direct_gtag' => $enabled && $gtmContainerId === null && ($measurementId !== null || $adsConversionId !== null),
            'gtm' => $enabled && $gtmContainerId !== null,
            'mode' => $integration?->mode === MarketingIntegration::MODE_TEST ? 'test' : 'prod',
        ];
    }

    private function pixelProvider(?MarketingIntegration $integration): array
    {
        $settings = $integration?->settings?->settings ?? [];
        $pixelId = $this->value($settings['pixel_id'] ?? null);
        $enabled = $integration?->status === MarketingIntegration::STATUS_ACTIVE
            && (bool) ($settings['send_client'] ?? false)
            && $pixelId !== null;

        return [
            'enabled' => $enabled,
            'pixel_id' => $pixelId,
            'test_event_code' => $integration?->mode === MarketingIntegration::MODE_TEST
                ? $this->value($settings['test_event_code'] ?? null)
                : null,
            'send_client' => $enabled,
            'send_server' => (bool) ($settings['send_server'] ?? false),
            'mode' => $integration?->mode === MarketingIntegration::MODE_TEST ? 'test' : 'prod',
        ];
    }

    private function value(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
