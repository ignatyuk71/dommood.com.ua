<?php

namespace App\Services\Marketing;

use App\Models\MarketingIntegration;
use Throwable;

class GoogleAnalyticsConfig
{
    public function storefront(): array
    {
        try {
            $integration = MarketingIntegration::query()
                ->with('settings')
                ->where('provider', MarketingIntegration::PROVIDER_GOOGLE)
                ->first();
        } catch (Throwable) {
            return $this->fallback();
        }

        $settings = $integration?->settings?->settings ?? [];
        $measurementId = $this->value($settings['measurement_id'] ?? config('services.google_analytics.measurement_id'));
        $gtmContainerId = $this->value($settings['gtm_container_id'] ?? config('services.google_analytics.gtm_container_id'));
        $isActive = $integration?->status === MarketingIntegration::STATUS_ACTIVE;
        $sendClient = (bool) ($settings['send_client'] ?? false);
        $hasConfiguredIntegration = $integration !== null;
        $enabled = $hasConfiguredIntegration
            ? $isActive && $sendClient && ($measurementId !== null || $gtmContainerId !== null)
            : $measurementId !== null || $gtmContainerId !== null;

        return [
            'enabled' => $enabled,
            'measurement_id' => $measurementId,
            'gtm_container_id' => $gtmContainerId,
            'direct_gtag' => $enabled && $measurementId !== null && $gtmContainerId === null,
            'gtm' => $enabled && $gtmContainerId !== null,
        ];
    }

    private function fallback(): array
    {
        $measurementId = $this->value(config('services.google_analytics.measurement_id'));
        $gtmContainerId = $this->value(config('services.google_analytics.gtm_container_id'));
        $enabled = $measurementId !== null || $gtmContainerId !== null;

        return [
            'enabled' => $enabled,
            'measurement_id' => $measurementId,
            'gtm_container_id' => $gtmContainerId,
            'direct_gtag' => $enabled && $measurementId !== null && $gtmContainerId === null,
            'gtm' => $enabled && $gtmContainerId !== null,
        ];
    }

    private function value(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
