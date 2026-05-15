<?php

namespace App\Services\Storefront;

use App\Services\SiteSettingsService;

class DeliveryPolicyService
{
    public const DEFAULT_FREE_SHIPPING_THRESHOLD_CENTS = 120000;

    public function __construct(private readonly SiteSettingsService $settings) {}

    public function freeShippingThresholdCents(): int
    {
        $paymentDeliverySettings = $this->settings->get('payment_delivery');
        $threshold = $this->moneyToCents($paymentDeliverySettings['free_shipping_threshold'] ?? null);

        return $threshold > 0 ? $threshold : self::DEFAULT_FREE_SHIPPING_THRESHOLD_CENTS;
    }

    public function freeShippingThresholdValue(): string
    {
        return number_format($this->freeShippingThresholdCents() / 100, 2, '.', '');
    }

    public function freeShippingThresholdLabel(): string
    {
        return $this->formatMoney($this->freeShippingThresholdCents());
    }

    public function formatMoney(int $cents, string $currency = 'UAH'): string
    {
        $amount = $cents / 100;
        $formatted = abs($amount - round($amount)) < 0.01
            ? number_format($amount, 0, '.', ' ')
            : number_format($amount, 2, '.', ' ');

        return trim($formatted.' '.$this->currencyLabel($currency));
    }

    private function moneyToCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = str_replace(["\xc2\xa0", ' '], '', (string) $value);

        return max(0, (int) round(((float) str_replace(',', '.', $normalized)) * 100));
    }

    private function currencyLabel(string $currency): string
    {
        return match (strtoupper($currency)) {
            'UAH' => 'грн',
            default => strtoupper($currency),
        };
    }
}
