<?php

namespace App\Services\Marketing;

use App\Jobs\Marketing\SendMarketingEventJob;
use App\Models\AnalyticsEvent;
use App\Models\MarketingEventOutbox;
use App\Models\MarketingIntegration;
use App\Models\Order;
use App\Services\Marketing\Clients\GoogleAdsApiClient;
use App\Services\Marketing\Clients\MetaCapiClient;
use App\Services\Marketing\Clients\TikTokEventsApiClient;
use App\Support\Marketing\MarketingPayloadNormalizer;
use App\Support\Marketing\MarketingSourceRouter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class MarketingEventService
{
    public function __construct(
        private readonly MarketingSourceRouter $sourceRouter,
        private readonly MarketingPayloadNormalizer $normalizer,
        private readonly MetaCapiClient $metaClient,
        private readonly TikTokEventsApiClient $tikTokClient,
        private readonly GoogleAdsApiClient $googleAdsClient,
    ) {}

    public function trackViewItem(Request $request, array $ecommerce, ?int $productId = null, ?string $eventId = null): string
    {
        return $this->trackEcommerce($request, 'view_item', $ecommerce, $eventId ?: $this->eventId('view', $productId));
    }

    public function trackAddToCart(Request $request, array $ecommerce, ?int $productId = null, ?string $eventId = null): string
    {
        return $this->trackEcommerce($request, 'add_to_cart', $ecommerce, $eventId ?: $this->eventId('atc', $productId));
    }

    public function trackBeginCheckout(Request $request, array $ecommerce, ?string $eventId = null): string
    {
        $eventId ??= 'checkout_'.substr(hash('sha256', $request->session()->getId().'|'.json_encode($ecommerce['items'] ?? [])), 0, 24);

        return $this->trackEcommerce($request, 'begin_checkout', $ecommerce, $eventId);
    }

    public function trackPurchase(Request $request, Order $order): string
    {
        $payload = $this->normalizer->order($order);
        $eventId = (string) ($payload['event_id'] ?? 'order_'.$order->order_number);
        $attribution = is_array($order->attribution) ? $order->attribution : $this->sourceRouter->capture($request);

        $this->recordAnalyticsEvent(
            eventName: 'purchase',
            eventId: $eventId,
            payload: $payload,
            attribution: $attribution,
            orderId: $order->id,
        );

        $eventSourceUrl = route('checkout.thank-you', $order->order_number);
        $userContext = [
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'first_name' => Str::before((string) $order->customer_name, ' '),
            'last_name' => Str::after((string) $order->customer_name, ' '),
            'external_id' => $order->customer_id ? 'customer_'.$order->customer_id : $order->customer_phone,
        ];

        $this->queueMetaTikTokIfAllowed($request, 'purchase', $eventId, $payload, $attribution, $userContext, $eventSourceUrl);
        $this->queueGoogleAdsIfAllowed($eventId, $payload, $attribution, $order);

        return $eventId;
    }

    public function dispatchOutbox(int $outboxId): void
    {
        $outbox = MarketingEventOutbox::query()
            ->with(['integration.settings', 'integration.credentials'])
            ->find($outboxId);

        if (! $outbox || $outbox->status === 'sent') {
            return;
        }

        $outbox->forceFill([
            'status' => 'processing',
            'attempts' => ((int) $outbox->attempts) + 1,
            'last_attempt_at' => now(),
        ])->save();

        try {
            $payload = is_array($outbox->payload) ? $outbox->payload : [];
            $config = $this->providerConfig((string) $outbox->provider);
            if (! $config['server_ready']) {
                $this->markOutboxAsFailed($outboxId, 'Інтеграція вимкнена або server-side ключі не заповнені.');

                return;
            }

            $response = match ($outbox->provider) {
                MarketingIntegration::PROVIDER_META => $this->metaClient->send(
                    (string) ($payload['pixel_id'] ?? $config['pixel_id']),
                    (string) $config['access_token'],
                    (array) data_get($payload, 'event'),
                    $payload['test_event_code'] ?? $config['test_event_code'] ?? null
                ),
                MarketingIntegration::PROVIDER_TIKTOK => $this->tikTokClient->send(
                    (string) ($payload['pixel_id'] ?? $config['pixel_id']),
                    (string) $config['access_token'],
                    (array) data_get($payload, 'event'),
                    $payload['test_event_code'] ?? $config['test_event_code'] ?? null
                ),
                MarketingIntegration::PROVIDER_GOOGLE => $this->googleAdsClient->uploadClickConversion(
                    $config,
                    (array) data_get($payload, 'click_conversion')
                ),
                default => [],
            };

            $payload['response'] = $response;
            $outbox->forceFill([
                'payload' => $payload,
                'status' => 'sent',
                'last_error' => null,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $outbox->forceFill([
                'status' => 'queued',
                'last_error' => $this->limitError($exception->getMessage()),
            ])->save();

            throw $exception;
        }
    }

    public function markOutboxAsFailed(int $outboxId, string $reason): void
    {
        MarketingEventOutbox::query()
            ->whereKey($outboxId)
            ->update([
                'status' => 'failed',
                'last_error' => $this->limitError($reason),
            ]);
    }

    private function trackEcommerce(Request $request, string $eventName, array $ecommerce, string $eventId): string
    {
        $attribution = $this->sourceRouter->capture($request);
        $payload = $this->normalizer->ecommerce($ecommerce + [
            'event_id' => $eventId,
            'source_channel' => $attribution['channel'] ?? null,
        ]);

        $this->recordAnalyticsEvent(
            eventName: $eventName,
            eventId: $eventId,
            payload: $payload,
            attribution: $attribution,
            productId: $this->resolveProductId($ecommerce),
        );

        $this->queueMetaTikTokIfAllowed($request, $eventName, $eventId, $payload, $attribution, [
            'external_id' => $request->hasSession() ? $request->session()->getId() : null,
        ]);

        return $eventId;
    }

    private function queueMetaTikTokIfAllowed(
        Request $request,
        string $eventName,
        string $eventId,
        array $payload,
        array $attribution,
        array $userContext = [],
        ?string $eventSourceUrl = null,
    ): void {
        if ($this->sourceRouter->shouldSendProvider($attribution, MarketingIntegration::PROVIDER_META)) {
            $this->queueProviderEvent(
                provider: MarketingIntegration::PROVIDER_META,
                eventName: $this->metaEventName($eventName),
                eventId: $eventId,
                payload: $this->metaPayload($request, $eventName, $eventId, $payload, $userContext, $eventSourceUrl),
            );
        }

        if ($this->sourceRouter->shouldSendProvider($attribution, MarketingIntegration::PROVIDER_TIKTOK)) {
            $this->queueProviderEvent(
                provider: MarketingIntegration::PROVIDER_TIKTOK,
                eventName: $this->tikTokEventName($eventName),
                eventId: $eventId,
                payload: $this->tikTokPayload($request, $eventName, $eventId, $payload, $userContext, $eventSourceUrl),
            );
        }
    }

    private function queueGoogleAdsIfAllowed(string $eventId, array $payload, array $attribution, Order $order): void
    {
        if (! $this->sourceRouter->shouldSendGoogleAds($attribution)) {
            return;
        }

        $config = $this->providerConfig(MarketingIntegration::PROVIDER_GOOGLE);
        if (! $config['server_ready']) {
            return;
        }

        $clickIds = is_array($attribution['click_ids'] ?? null) ? $attribution['click_ids'] : [];
        $clickId = $clickIds['gclid'] ?? $clickIds['wbraid'] ?? $clickIds['gbraid'] ?? null;

        if (! is_string($clickId) || trim($clickId) === '') {
            return;
        }

        $conversion = [
            'conversionAction' => sprintf(
                'customers/%s/conversionActions/%s',
                $config['ads_api_customer_id'],
                $config['ads_api_conversion_action_id']
            ),
            'conversionDateTime' => $this->googleConversionDate($order->created_at),
            'conversionValue' => (float) ($payload['value'] ?? 0),
            'currencyCode' => (string) ($payload['currency'] ?? 'UAH'),
            'orderId' => (string) $order->order_number,
            'conversionEnvironment' => 'WEB',
        ];

        if (! empty($clickIds['gclid'])) {
            $conversion['gclid'] = $clickIds['gclid'];
        } elseif (! empty($clickIds['wbraid'])) {
            $conversion['wbraid'] = $clickIds['wbraid'];
        } else {
            $conversion['gbraid'] = $clickIds['gbraid'];
        }

        if (($cartData = $this->googleCartData($payload)) !== []) {
            $conversion['cartData'] = $cartData;
        }

        $this->queueProviderEvent(
            provider: MarketingIntegration::PROVIDER_GOOGLE,
            eventName: 'conversion',
            eventId: $eventId,
            payload: [
                'event' => [
                    'name' => 'conversion',
                    'params' => [
                        'value' => $payload['value'] ?? null,
                        'currency' => $payload['currency'] ?? 'UAH',
                        'transaction_id' => $payload['transaction_id'] ?? $order->order_number,
                        'event_id' => $eventId,
                    ],
                ],
                'click_conversion' => $conversion,
            ],
        );
    }

    private function queueProviderEvent(string $provider, string $eventName, string $eventId, array $payload): void
    {
        $config = $this->providerConfig($provider);
        if (! $config['server_ready']) {
            return;
        }

        $outboxPayload = $payload + [
            'pixel_id' => $config['pixel_id'] ?? null,
            'test_event_code' => $config['test_event_code'] ?? null,
        ];

        $outbox = MarketingEventOutbox::query()
            ->where('provider', $provider)
            ->where('event_name', $eventName)
            ->where('event_id', $eventId)
            ->where('transport', 'server')
            ->first();

        if (! $outbox) {
            $outbox = new MarketingEventOutbox([
                'marketing_integration_id' => $config['integration_id'],
                'provider' => $provider,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'transport' => 'server',
                'attempts' => 0,
            ]);
        }

        if ($outbox->status === 'sent') {
            return;
        }

        $outbox->forceFill([
            'marketing_integration_id' => $config['integration_id'],
            'payload' => $outboxPayload,
            'status' => 'queued',
            'last_error' => null,
            'sent_at' => null,
        ])->save();

        $this->dispatchJob((int) $outbox->id);
    }

    private function dispatchJob(int $outboxId): void
    {
        try {
            if (app()->environment(['local', 'testing'])) {
                SendMarketingEventJob::dispatchSync($outboxId);

                return;
            }

            SendMarketingEventJob::dispatch($outboxId)->onQueue('integrations');
        } catch (Throwable $exception) {
            $this->markOutboxAsFailed($outboxId, $exception->getMessage());
        }
    }

    private function recordAnalyticsEvent(
        string $eventName,
        string $eventId,
        array $payload,
        array $attribution,
        ?int $orderId = null,
        ?int $productId = null,
    ): void {
        AnalyticsEvent::query()->updateOrCreate(
            [
                'event_name' => $eventName,
                'event_id' => $eventId,
            ],
            [
                'source' => $attribution['source'] ?? MarketingSourceRouter::SOURCE_UNKNOWN,
                'channel' => $attribution['channel'] ?? MarketingSourceRouter::CHANNEL_UNKNOWN,
                'session_id' => session()->getId(),
                'order_id' => $orderId,
                'product_id' => $productId,
                'currency' => (string) ($payload['currency'] ?? 'UAH'),
                'value_cents' => (int) ($payload['value_cents'] ?? 0),
                'utm' => $attribution['utm'] ?? [],
                'click_ids' => $attribution['click_ids'] ?? [],
                'context' => [
                    'content_ids' => $payload['content_ids'] ?? [],
                    'num_items' => $payload['num_items'] ?? null,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                ],
                'occurred_at' => now(),
            ]
        );
    }

    private function providerConfig(string $provider): array
    {
        $integration = MarketingIntegration::query()
            ->with(['settings', 'credentials'])
            ->where('provider', $provider)
            ->first();

        if (! $integration) {
            return ['server_ready' => false];
        }

        $settings = $integration->settings?->settings ?? [];
        $credentials = $integration->credentials;
        $isActive = $integration->status === MarketingIntegration::STATUS_ACTIVE;
        $sendServer = (bool) ($settings['send_server'] ?? false);

        if ($provider === MarketingIntegration::PROVIDER_GOOGLE) {
            $developerToken = trim((string) ($credentials->firstWhere('secret_type', 'ads_developer_token')?->secret_value ?? ''));
            $serviceAccountJson = trim((string) ($credentials->firstWhere('secret_type', 'ads_service_account_json')?->secret_value ?? ''));

            return [
                'integration_id' => $integration->id,
                'server_ready' => $isActive
                    && $sendServer
                    && $this->digits((string) ($settings['ads_api_customer_id'] ?? '')) !== ''
                    && $this->digits((string) ($settings['ads_api_conversion_action_id'] ?? '')) !== ''
                    && $developerToken !== ''
                    && $serviceAccountJson !== '',
                'mode' => $integration->mode === MarketingIntegration::MODE_TEST ? 'test' : 'prod',
                'ads_api_customer_id' => $this->digits((string) ($settings['ads_api_customer_id'] ?? '')),
                'ads_api_login_customer_id' => $this->digits((string) ($settings['ads_api_login_customer_id'] ?? '')),
                'ads_api_conversion_action_id' => $this->digits((string) ($settings['ads_api_conversion_action_id'] ?? '')),
                'ads_developer_token' => $developerToken,
                'ads_service_account_json' => $serviceAccountJson,
            ];
        }

        $pixelId = trim((string) ($settings['pixel_id'] ?? ''));
        $accessToken = trim((string) ($credentials->firstWhere('secret_type', 'access_token')?->secret_value ?? ''));

        return [
            'integration_id' => $integration->id,
            'server_ready' => $isActive && $sendServer && $pixelId !== '' && $accessToken !== '',
            'pixel_id' => $pixelId,
            'access_token' => $accessToken,
            'test_event_code' => $integration->mode === MarketingIntegration::MODE_TEST
                ? trim((string) ($settings['test_event_code'] ?? ''))
                : null,
        ];
    }

    private function metaPayload(
        Request $request,
        string $eventName,
        string $eventId,
        array $payload,
        array $userContext,
        ?string $eventSourceUrl,
    ): array {
        return [
            'event' => $this->clean([
                'event_name' => $this->metaEventName($eventName),
                'event_time' => now()->timestamp,
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl ?: $this->eventSourceUrl($request),
                'user_data' => $this->metaUserData($request, $userContext),
                'custom_data' => $this->clean([
                    'currency' => $payload['currency'] ?? 'UAH',
                    'value' => $payload['value'] ?? null,
                    'content_type' => $payload['content_type'] ?? null,
                    'content_ids' => $payload['content_ids'] ?? null,
                    'contents' => array_map(static fn (array $item): array => array_filter([
                        'id' => $item['id'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'item_price' => $item['item_price'] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== ''), $payload['contents'] ?? []),
                    'num_items' => $payload['num_items'] ?? null,
                    'content_name' => $payload['content_name'] ?? null,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                ]),
            ]),
        ];
    }

    private function tikTokPayload(
        Request $request,
        string $eventName,
        string $eventId,
        array $payload,
        array $userContext,
        ?string $eventSourceUrl,
    ): array {
        $ttclid = $this->clickId($request, 'ttclid');

        return [
            'event' => $this->clean([
                'event' => $this->tikTokEventName($eventName),
                'event_time' => now()->timestamp,
                'event_id' => $eventId,
                'context' => $this->clean([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'ad' => $ttclid !== '' ? ['callback' => $ttclid] : null,
                    'page' => [
                        'url' => $eventSourceUrl ?: $this->eventSourceUrl($request),
                        'referrer' => trim((string) $request->headers->get('referer')),
                    ],
                    'user' => $this->tikTokUserData($request, $userContext),
                ]),
                'properties' => $this->clean([
                    'currency' => $payload['currency'] ?? 'UAH',
                    'value' => $payload['value'] ?? null,
                    'content_type' => $payload['content_type'] ?? null,
                    'content_ids' => $payload['content_ids'] ?? null,
                    'contents' => array_map(static fn (array $item): array => array_filter([
                        'content_id' => $item['id'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'price' => $item['item_price'] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== ''), $payload['contents'] ?? []),
                    'num_items' => $payload['num_items'] ?? null,
                    'content_name' => $payload['content_name'] ?? null,
                ]),
            ]),
        ];
    }

    private function metaUserData(Request $request, array $context): array
    {
        $fbclid = $this->clickId($request, 'fbclid');
        $fbc = trim((string) $request->cookie('_fbc'));

        if ($fbc === '' && $fbclid !== '') {
            $fbc = sprintf('fb.1.%s.%s', now()->getTimestampMs(), $fbclid);
        }

        return $this->clean([
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => trim((string) $request->cookie('_fbp')) ?: null,
            'fbc' => $fbc ?: null,
            'em' => $this->hash($context['email'] ?? null, 'email'),
            'ph' => $this->hash($context['phone'] ?? null, 'phone'),
            'fn' => $this->hash($context['first_name'] ?? null, 'name'),
            'ln' => $this->hash($context['last_name'] ?? null, 'name'),
            'external_id' => $this->hash($context['external_id'] ?? null, 'text'),
        ]);
    }

    private function tikTokUserData(Request $request, array $context): array
    {
        return $this->clean([
            'email' => $this->hash($context['email'] ?? null, 'email'),
            'phone_number' => $this->hash($context['phone'] ?? null, 'phone'),
            'external_id' => $this->hash($context['external_id'] ?? null, 'text'),
            'ttp' => trim((string) ($request->cookie('_ttp') ?: $request->cookie('ttp'))) ?: null,
        ]);
    }

    private function metaEventName(string $eventName): string
    {
        return match ($eventName) {
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'purchase' => 'Purchase',
            default => Str::studly($eventName),
        };
    }

    private function tikTokEventName(string $eventName): string
    {
        return match ($eventName) {
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'purchase' => 'CompletePayment',
            default => Str::studly($eventName),
        };
    }

    private function googleCartData(array $payload): array
    {
        $items = [];

        foreach (($payload['contents'] ?? []) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $items[] = [
                'productId' => (string) $item['id'],
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unitPrice' => round((float) ($item['item_price'] ?? 0), 2),
            ];
        }

        return $items !== [] ? ['items' => $items] : [];
    }

    private function clickId(Request $request, string $key): string
    {
        $value = trim((string) $request->query($key, ''));
        if ($value !== '') {
            return $value;
        }

        $clickIds = (array) $request->session()->get('marketing.click_ids', []);

        return trim((string) ($clickIds[$key] ?? ''));
    }

    private function hash(mixed $value, string $type): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = match ($type) {
            'email' => mb_strtolower($value),
            'phone' => preg_replace('/\D+/', '', $value) ?? '',
            'name' => preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? '',
            default => $value,
        };

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }

    private function eventId(string $prefix, int|string|null $key = null): string
    {
        return sprintf('%s_%s_%s', $prefix, $key ?: 'na', Str::uuid()->toString());
    }

    private function eventSourceUrl(Request $request): string
    {
        return trim((string) $request->headers->get('referer')) ?: $request->fullUrl();
    }

    private function googleConversionDate(mixed $date): string
    {
        try {
            return Carbon::parse($date ?: now())
                ->timezone((string) config('app.timezone', 'Europe/Kyiv'))
                ->format('Y-m-d H:i:sP');
        } catch (Throwable) {
            return now()->timezone((string) config('app.timezone', 'Europe/Kyiv'))->format('Y-m-d H:i:sP');
        }
    }

    private function resolveProductId(array $payload): ?int
    {
        $value = $payload['product_id'] ?? $payload['items'][0]['product_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function limitError(string $message): string
    {
        return mb_substr(trim($message), 0, 60000);
    }

    private function clean(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->clean($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
