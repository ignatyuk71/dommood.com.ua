<?php

namespace App\Support\Marketing;

use App\Models\MarketingIntegration;
use Illuminate\Http\Request;

class MarketingSourceRouter
{
    public const SOURCE_GOOGLE = 'google';

    public const SOURCE_META = 'meta';

    public const SOURCE_TIKTOK = 'tiktok';

    public const SOURCE_DIRECT = 'direct';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_UNKNOWN = 'unknown';

    public const CHANNEL_GOOGLE_ADS = 'google_ads';

    public const CHANNEL_GOOGLE_ORGANIC = 'google_organic';

    public const CHANNEL_META_ADS = 'meta_ads';

    public const CHANNEL_META_SOCIAL = 'meta_social';

    public const CHANNEL_TIKTOK_ADS = 'tiktok_ads';

    public const CHANNEL_TIKTOK_SOCIAL = 'tiktok_social';

    public const CHANNEL_DIRECT = 'direct';

    public const CHANNEL_REFERRAL = 'referral';

    public const CHANNEL_UNKNOWN = 'unknown';

    private const SESSION_SOURCE = 'marketing.source';

    private const SESSION_CHANNEL = 'marketing.channel';

    private const SESSION_UTM = 'marketing.utm';

    private const SESSION_CLICK_IDS = 'marketing.click_ids';

    private const SESSION_TOUCH = 'marketing.touch';

    private const SESSION_FBC = 'marketing.fbc';

    private const SESSION_TTCLID = 'marketing.ttclid';

    public function capture(Request $request): array
    {
        $previousSource = $this->normalizedSource($this->sessionValue($request, self::SESSION_SOURCE));
        $previousChannel = $this->normalizedChannel($this->sessionValue($request, self::SESSION_CHANNEL));
        $detected = $this->detectFromRequest($request);

        if ($this->shouldResetAttributionState($request, $previousSource, $detected['source'])) {
            $this->resetAttributionState($request);
            $previousSource = self::SOURCE_UNKNOWN;
            $previousChannel = self::CHANNEL_UNKNOWN;
        }

        $source = $detected['source'] !== self::SOURCE_UNKNOWN ? $detected['source'] : $previousSource;
        $channel = $detected['channel'] !== self::CHANNEL_UNKNOWN ? $detected['channel'] : $previousChannel;

        if ($source === self::SOURCE_UNKNOWN && $this->externalReferrerHost($request) === '' && ! $this->hasStoredAttribution($request)) {
            $source = self::SOURCE_DIRECT;
            $channel = self::CHANNEL_DIRECT;
        }

        if ($detected['source'] !== self::SOURCE_UNKNOWN) {
            $this->sessionPut($request, self::SESSION_SOURCE, $detected['source']);
            $this->sessionPut($request, self::SESSION_CHANNEL, $detected['channel']);
        }

        $this->storeClickIds($request);
        $this->storeUtm($request);
        $this->storeTouchpoint($request, $source, $channel, $detected['source'] !== self::SOURCE_UNKNOWN);

        $utm = $this->sanitizeUtm($this->sessionArray($request, self::SESSION_UTM));
        $clickIds = $this->sanitizeClickIds($this->sessionArray($request, self::SESSION_CLICK_IDS));
        $touch = $this->sanitizeTouchpoint($this->sessionArray($request, self::SESSION_TOUCH));

        return [
            'source' => $source,
            'channel' => $this->resolveChannel($source, $channel, $utm, $clickIds, $touch),
            'utm' => $utm,
            'click_ids' => $clickIds,
            'touch' => $touch,
        ];
    }

    public function shouldSendProvider(array|string|null $attribution, string $provider): bool
    {
        $source = is_array($attribution)
            ? $this->normalizedSource((string) ($attribution['source'] ?? ''))
            : $this->normalizedSource((string) $attribution);

        return match ($provider) {
            MarketingIntegration::PROVIDER_META => $source === self::SOURCE_META,
            MarketingIntegration::PROVIDER_TIKTOK => $source === self::SOURCE_TIKTOK,
            MarketingIntegration::PROVIDER_GOOGLE => $source === self::SOURCE_GOOGLE,
            default => false,
        };
    }

    public function shouldSendGoogleAds(array $attribution): bool
    {
        return ($attribution['channel'] ?? null) === self::CHANNEL_GOOGLE_ADS;
    }

    public function allowedProviders(array $attribution): array
    {
        return [
            'meta' => $this->shouldSendProvider($attribution, MarketingIntegration::PROVIDER_META),
            'tiktok' => $this->shouldSendProvider($attribution, MarketingIntegration::PROVIDER_TIKTOK),
            'google' => $this->shouldSendProvider($attribution, MarketingIntegration::PROVIDER_GOOGLE),
            'google_ads' => $this->shouldSendGoogleAds($attribution),
        ];
    }

    public function hasMarketingSignal(Request $request): bool
    {
        return $this->extractUtm($request) !== []
            || $this->extractClickIds($request) !== []
            || $this->externalReferrerHost($request) !== '';
    }

    private function detectFromRequest(Request $request): array
    {
        if (trim((string) $request->query('fbclid', '')) !== '') {
            return ['source' => self::SOURCE_META, 'channel' => self::CHANNEL_META_ADS];
        }

        if (trim((string) $request->query('ttclid', '')) !== '') {
            return ['source' => self::SOURCE_TIKTOK, 'channel' => self::CHANNEL_TIKTOK_ADS];
        }

        if (
            trim((string) $request->query('gclid', '')) !== ''
            || trim((string) $request->query('gbraid', '')) !== ''
            || trim((string) $request->query('wbraid', '')) !== ''
        ) {
            return ['source' => self::SOURCE_GOOGLE, 'channel' => self::CHANNEL_GOOGLE_ADS];
        }

        $utmSource = mb_strtolower(trim((string) $request->query('utm_source', '')));
        $utmMedium = mb_strtolower(trim((string) $request->query('utm_medium', '')));
        $utmCampaign = mb_strtolower(trim((string) $request->query('utm_campaign', '')));

        if ($utmSource !== '') {
            if ($this->isMetaAlias($utmSource)) {
                return [
                    'source' => self::SOURCE_META,
                    'channel' => $this->isPaidMedium($utmMedium) ? self::CHANNEL_META_ADS : self::CHANNEL_META_SOCIAL,
                ];
            }

            if ($this->isTikTokAlias($utmSource)) {
                return [
                    'source' => self::SOURCE_TIKTOK,
                    'channel' => $this->isPaidMedium($utmMedium) ? self::CHANNEL_TIKTOK_ADS : self::CHANNEL_TIKTOK_SOCIAL,
                ];
            }

            if ($this->isGoogleAlias($utmSource)) {
                return [
                    'source' => self::SOURCE_GOOGLE,
                    'channel' => $this->isPaidMedium($utmMedium) || str_contains($utmCampaign, 'pmax') || str_contains($utmCampaign, 'shopping')
                        ? self::CHANNEL_GOOGLE_ADS
                        : self::CHANNEL_GOOGLE_ORGANIC,
                ];
            }
        }

        $referrerHost = $this->externalReferrerHost($request);
        if ($referrerHost !== '') {
            if ($this->isMetaHost($referrerHost)) {
                return ['source' => self::SOURCE_META, 'channel' => self::CHANNEL_META_SOCIAL];
            }

            if ($this->isTikTokHost($referrerHost)) {
                return ['source' => self::SOURCE_TIKTOK, 'channel' => self::CHANNEL_TIKTOK_SOCIAL];
            }

            if ($this->isGoogleHost($referrerHost)) {
                return ['source' => self::SOURCE_GOOGLE, 'channel' => self::CHANNEL_GOOGLE_ORGANIC];
            }

            return ['source' => self::SOURCE_REFERRAL, 'channel' => self::CHANNEL_REFERRAL];
        }

        return ['source' => self::SOURCE_UNKNOWN, 'channel' => self::CHANNEL_UNKNOWN];
    }

    private function shouldResetAttributionState(Request $request, string $previousSource, string $detectedSource): bool
    {
        if ($detectedSource === self::SOURCE_UNKNOWN || $detectedSource === self::SOURCE_DIRECT) {
            return false;
        }

        if ($previousSource !== self::SOURCE_UNKNOWN && $previousSource !== $detectedSource) {
            return true;
        }

        $currentClickIds = $this->extractClickIds($request);
        $storedClickIds = $this->sanitizeClickIds($this->sessionArray($request, self::SESSION_CLICK_IDS));
        if ($currentClickIds !== [] && $currentClickIds !== $storedClickIds) {
            return true;
        }

        $currentUtm = $this->extractUtm($request);
        $storedUtm = $this->sanitizeUtm($this->sessionArray($request, self::SESSION_UTM));

        return $currentUtm !== [] && $currentUtm !== $storedUtm;
    }

    private function resetAttributionState(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        // При новому рекламному джерелі не змішуємо старі click ID з новою атрибуцією.
        $request->session()->forget([
            self::SESSION_CLICK_IDS,
            self::SESSION_UTM,
            self::SESSION_TOUCH,
            self::SESSION_FBC,
            self::SESSION_TTCLID,
        ]);
    }

    private function storeClickIds(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $stored = $this->sessionArray($request, self::SESSION_CLICK_IDS);
        $updated = false;

        foreach (['fbclid', 'ttclid', 'gclid', 'gbraid', 'wbraid'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value === '') {
                continue;
            }

            $stored[$key] = $value;
            $stored[$key.'_ts'] = now()->getTimestampMs();
            $updated = true;
        }

        if ($updated) {
            $request->session()->put(self::SESSION_CLICK_IDS, $stored);
        }
    }

    private function storeUtm(Request $request): void
    {
        if ($request->hasSession() && ($utm = $this->extractUtm($request)) !== []) {
            $request->session()->put(self::SESSION_UTM, $utm);
        }
    }

    private function storeTouchpoint(Request $request, string $source, string $channel, bool $hasFreshSignal): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $touch = $this->sanitizeTouchpoint($this->sessionArray($request, self::SESSION_TOUCH));
        $currentUrl = $this->sanitizeUrl($request->fullUrl());
        $referrer = $this->sanitizeUrl((string) $request->headers->get('referer', ''));

        if ($currentUrl !== '' && ($touch['entry_url'] ?? '') === '') {
            $touch['entry_url'] = $currentUrl;
        }

        if ($referrer !== '' && ($touch['entry_referrer'] ?? '') === '') {
            $touch['entry_referrer'] = $referrer;
        }

        if ($source !== self::SOURCE_UNKNOWN && ($touch['entry_source'] ?? '') === '') {
            $touch['entry_source'] = $source;
        }

        if ($channel !== self::CHANNEL_UNKNOWN && ($touch['entry_channel'] ?? '') === '') {
            $touch['entry_channel'] = $channel;
        }

        if ($hasFreshSignal) {
            if ($currentUrl !== '') {
                $touch['last_attributed_url'] = $currentUrl;
            }

            if ($referrer !== '') {
                $touch['last_attributed_referrer'] = $referrer;
            }

            $touch['last_attributed_source'] = $source;
            $touch['last_attributed_channel'] = $channel;
            $touch['updated_at'] = now()->toAtomString();
        }

        $request->session()->put(self::SESSION_TOUCH, $touch);
    }

    private function hasStoredAttribution(Request $request): bool
    {
        return $this->sessionValue($request, self::SESSION_SOURCE) !== ''
            || $this->sessionArray($request, self::SESSION_UTM) !== []
            || $this->sessionArray($request, self::SESSION_CLICK_IDS) !== [];
    }

    private function resolveChannel(string $source, string $channel, array $utm, array $clickIds, array $touch): string
    {
        if ($source === self::SOURCE_GOOGLE) {
            if (! empty($clickIds['gclid']) || ! empty($clickIds['gbraid']) || ! empty($clickIds['wbraid'])) {
                return self::CHANNEL_GOOGLE_ADS;
            }

            if ($channel !== self::CHANNEL_UNKNOWN) {
                return $channel;
            }

            return $this->isPaidMedium($utm['utm_medium'] ?? null) ? self::CHANNEL_GOOGLE_ADS : self::CHANNEL_GOOGLE_ORGANIC;
        }

        if ($source === self::SOURCE_META) {
            return ! empty($clickIds['fbclid']) || $this->isPaidMedium($utm['utm_medium'] ?? null)
                ? self::CHANNEL_META_ADS
                : ($channel !== self::CHANNEL_UNKNOWN ? $channel : self::CHANNEL_META_SOCIAL);
        }

        if ($source === self::SOURCE_TIKTOK) {
            return ! empty($clickIds['ttclid']) || $this->isPaidMedium($utm['utm_medium'] ?? null)
                ? self::CHANNEL_TIKTOK_ADS
                : ($channel !== self::CHANNEL_UNKNOWN ? $channel : self::CHANNEL_TIKTOK_SOCIAL);
        }

        if ($source === self::SOURCE_DIRECT) {
            return self::CHANNEL_DIRECT;
        }

        if ($source === self::SOURCE_REFERRAL) {
            return self::CHANNEL_REFERRAL;
        }

        return $touch['last_attributed_channel'] ?? self::CHANNEL_UNKNOWN;
    }

    private function extractUtm(Request $request): array
    {
        $utm = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $utm[$key] = $value;
            }
        }

        return $utm;
    }

    private function extractClickIds(Request $request): array
    {
        $clickIds = [];

        foreach (['fbclid', 'ttclid', 'gclid', 'gbraid', 'wbraid'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $clickIds[$key] = $value;
            }
        }

        return $clickIds;
    }

    private function sanitizeUtm(array $utm): array
    {
        return $this->onlyNonEmpty($utm, ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
    }

    private function sanitizeClickIds(array $clickIds): array
    {
        return $this->onlyNonEmpty($clickIds, ['fbclid', 'ttclid', 'gclid', 'gbraid', 'wbraid']);
    }

    private function sanitizeTouchpoint(array $touch): array
    {
        return $this->onlyNonEmpty($touch, [
            'entry_url',
            'entry_referrer',
            'entry_source',
            'entry_channel',
            'last_attributed_url',
            'last_attributed_referrer',
            'last_attributed_source',
            'last_attributed_channel',
            'updated_at',
        ]);
    }

    private function onlyNonEmpty(array $payload, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function normalizedSource(string $source): string
    {
        $source = mb_strtolower(trim($source));

        return in_array($source, [
            self::SOURCE_GOOGLE,
            self::SOURCE_META,
            self::SOURCE_TIKTOK,
            self::SOURCE_DIRECT,
            self::SOURCE_REFERRAL,
        ], true) ? $source : self::SOURCE_UNKNOWN;
    }

    private function normalizedChannel(string $channel): string
    {
        $channel = mb_strtolower(trim($channel));

        return in_array($channel, [
            self::CHANNEL_GOOGLE_ADS,
            self::CHANNEL_GOOGLE_ORGANIC,
            self::CHANNEL_META_ADS,
            self::CHANNEL_META_SOCIAL,
            self::CHANNEL_TIKTOK_ADS,
            self::CHANNEL_TIKTOK_SOCIAL,
            self::CHANNEL_DIRECT,
            self::CHANNEL_REFERRAL,
        ], true) ? $channel : self::CHANNEL_UNKNOWN;
    }

    private function sessionValue(Request $request, string $key): string
    {
        return $request->hasSession() ? trim((string) $request->session()->get($key, '')) : '';
    }

    private function sessionPut(Request $request, string $key, string $value): void
    {
        if ($request->hasSession()) {
            $request->session()->put($key, $value);
        }
    }

    private function sessionArray(Request $request, string $key): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $value = $request->session()->get($key, []);

        return is_array($value) ? $value : [];
    }

    private function isMetaAlias(string $source): bool
    {
        return in_array($source, ['facebook', 'fb', 'instagram', 'ig', 'meta', 'fb_ads', 'facebook_ads', 'instagram_ads'], true);
    }

    private function isTikTokAlias(string $source): bool
    {
        return in_array($source, ['tiktok', 'tt', 'tiktok_ads'], true);
    }

    private function isGoogleAlias(string $source): bool
    {
        return in_array($source, ['google', 'google_ads', 'adwords', 'gads', 'shopping'], true);
    }

    private function isPaidMedium(?string $medium): bool
    {
        $medium = mb_strtolower(trim((string) $medium));

        return $medium !== '' && (
            in_array($medium, ['cpc', 'ppc', 'paid', 'paid_social', 'paid_search', 'display', 'shopping', 'banner', 'remarketing'], true)
            || str_contains($medium, 'paid')
            || str_contains($medium, 'cpc')
            || str_contains($medium, 'ppc')
        );
    }

    private function externalReferrerHost(Request $request): string
    {
        $referrer = trim((string) $request->headers->get('referer', ''));
        if ($referrer === '') {
            return '';
        }

        $host = $this->normalizedHost((string) (parse_url($referrer, PHP_URL_HOST) ?? ''));
        $currentHost = $this->normalizedHost((string) $request->getHost());

        return $host !== '' && $host !== $currentHost ? $host : '';
    }

    private function normalizedHost(string $host): string
    {
        return preg_replace('/^www\./', '', mb_strtolower(trim($host))) ?? '';
    }

    private function isMetaHost(string $host): bool
    {
        return $this->hostMatches($host, ['facebook.com', 'fb.com', 'instagram.com']);
    }

    private function isTikTokHost(string $host): bool
    {
        return $this->hostMatches($host, ['tiktok.com', 'tiktokv.com']);
    }

    private function isGoogleHost(string $host): bool
    {
        return (bool) preg_match('/(^|\.)google\.[a-z.]+$/i', $host)
            || $this->hostMatches($host, ['googleadservices.com', 'googleusercontent.com']);
    }

    private function hostMatches(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}
