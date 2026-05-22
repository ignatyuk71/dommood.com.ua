@php
    $analyticsConfig = $storefrontAnalyticsConfig ?? [
        'google' => $storefrontGoogleAnalytics ?? [],
        'meta' => ['enabled' => false],
        'tiktok' => ['enabled' => false],
        'routing' => ['strict' => true, 'source' => 'unknown', 'channel' => 'unknown', 'allowed' => []],
    ];
    $googleTagId = $analyticsConfig['google']['measurement_id']
        ?? $analyticsConfig['google']['ads_conversion_id']
        ?? null;
@endphp

@if (($analyticsConfig['google']['direct_gtag'] ?? false) && $googleTagId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($googleTagId) }}"></script>
@endif

<script>
    (() => {
        const config = @json($analyticsConfig);
        const googleConfig = config.google || {};
        const metaConfig = config.meta || {};
        const tiktokConfig = config.tiktok || {};
        const routing = config.routing || {};
        const allowed = routing.allowed || {};

        window.dataLayer = window.dataLayer || [];
        window.StorefrontAnalytics = window.StorefrontAnalytics || {};
        window.StorefrontAnalytics.googleEnabled = Boolean(googleConfig.enabled);
        window.StorefrontAnalytics.routing = routing;

        const cleanObject = (value) => {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return value;
            }

            return Object.fromEntries(Object.entries(value).filter(([, item]) => {
                return item !== undefined && item !== null && item !== '' && !(Array.isArray(item) && item.length === 0);
            }));
        };

        const loadScript = (src) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                return;
            }

            const script = document.createElement('script');
            script.async = true;
            script.src = src;
            document.head.appendChild(script);
        };

        const providerAllowed = (provider) => {
            if (provider === 'google') {
                return Boolean(googleConfig.enabled);
            }

            if (Object.prototype.hasOwnProperty.call(allowed, provider)) {
                return Boolean(allowed[provider]);
            }

            return routing.strict === false;
        };

        const normalizeItems = (payload) => {
            const source = Array.isArray(payload.items) ? payload.items : (Array.isArray(payload.contents) ? payload.contents : []);

            return source.map((item) => {
                if (!item || typeof item !== 'object') {
                    return null;
                }

                const id = String(item.item_id || item.id || item.content_id || '').trim();
                if (!id) {
                    return null;
                }

                const quantity = Math.max(1, Number(item.quantity || item.qty || 1));
                const price = Number(item.price || item.item_price || 0);

                return cleanObject({
                    id,
                    name: item.item_name || item.name || item.content_name || undefined,
                    quantity: Number.isFinite(quantity) ? quantity : 1,
                    item_price: Number.isFinite(price) ? price : undefined,
                });
            }).filter(Boolean);
        };

        const normalizePayload = (payload = {}) => {
            const items = normalizeItems(payload);
            const contentIds = Array.from(new Set(
                (Array.isArray(payload.content_ids) && payload.content_ids.length ? payload.content_ids : items.map((item) => item.id))
                    .map((id) => String(id || '').trim())
                    .filter(Boolean)
            ));
            let value = Number(payload.value);

            if (!Number.isFinite(value) && items.length) {
                value = items.reduce((sum, item) => sum + Number(item.item_price || 0) * Number(item.quantity || 1), 0);
            }

            const numItems = items.length
                ? items.reduce((sum, item) => sum + Number(item.quantity || 1), 0)
                : Number(payload.num_items || 0);

            return cleanObject({
                currency: String(payload.currency || 'UAH').trim().toUpperCase(),
                value: Number.isFinite(value) ? Number(value.toFixed(2)) : undefined,
                content_type: items.length ? 'product' : undefined,
                content_ids: contentIds,
                contents: items,
                num_items: Number.isFinite(numItems) && numItems > 0 ? numItems : undefined,
                content_name: payload.content_name || items[0]?.name || undefined,
                transaction_id: payload.transaction_id,
                event_id: payload.event_id,
                source_channel: payload.source_channel,
            });
        };

        const providerEventName = (provider, eventName) => {
            const map = {
                page_view: { meta: 'PageView', tiktok: 'PageView' },
                view_item: { meta: 'ViewContent', tiktok: 'ViewContent' },
                add_to_cart: { meta: 'AddToCart', tiktok: 'AddToCart' },
                begin_checkout: { meta: 'InitiateCheckout', tiktok: 'InitiateCheckout' },
                purchase: { meta: 'Purchase', tiktok: 'CompletePayment' },
            };

            return map[eventName]?.[provider] || null;
        };

        const initGoogle = () => {
            if (!googleConfig.enabled) {
                return;
            }

            const gtmContainerId = String(googleConfig.gtm_container_id || '').trim();
            const measurementId = String(googleConfig.measurement_id || '').trim();
            const adsConversionId = String(googleConfig.ads_conversion_id || '').trim();

            if (gtmContainerId) {
                window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                loadScript(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtmContainerId)}`);
                return;
            }

            const tagId = measurementId || adsConversionId;
            if (!tagId) {
                return;
            }

            loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(tagId)}`);
            window.gtag = window.gtag || function () {
                window.dataLayer.push(arguments);
            };
            window.gtag('js', new Date());

            if (measurementId) {
                window.gtag('config', measurementId, cleanObject({
                    debug_mode: googleConfig.mode === 'test' ? true : undefined,
                }));
            }

            if (adsConversionId) {
                window.gtag('config', adsConversionId, cleanObject({
                    debug_mode: googleConfig.mode === 'test' ? true : undefined,
                }));
            }
        };

        const initMeta = () => {
            const pixelId = String(metaConfig.pixel_id || '').trim();
            if (!metaConfig.enabled || !pixelId || !providerAllowed('meta')) {
                return;
            }

            if (!window.fbq) {
                ((f, b, e, v, n, t, s) => {
                    if (f.fbq) return;
                    n = f.fbq = function () {
                        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
                    };
                    if (!f._fbq) f._fbq = n;
                    n.push = n;
                    n.loaded = true;
                    n.version = '2.0';
                    n.queue = [];
                    t = b.createElement(e);
                    t.async = true;
                    t.src = v;
                    s = b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t, s);
                })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            }

            window.fbq('init', pixelId);
            window.fbq('track', 'PageView');
        };

        const initTikTok = () => {
            const pixelId = String(tiktokConfig.pixel_id || '').trim();
            if (!tiktokConfig.enabled || !pixelId || !providerAllowed('tiktok')) {
                return;
            }

            window.TiktokAnalyticsObject = 'ttq';
            window.ttq = window.ttq || [];
            window.ttq.methods = window.ttq.methods || ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
            window.ttq.setAndDefer = (target, method) => {
                target[method] = function () {
                    target.push([method].concat(Array.prototype.slice.call(arguments, 0)));
                };
            };
            window.ttq.methods.forEach((method) => {
                if (typeof window.ttq[method] !== 'function') {
                    window.ttq.setAndDefer(window.ttq, method);
                }
            });
            window.ttq._i = window.ttq._i || {};
            window.ttq._o = window.ttq._o || {};
            window.ttq._t = window.ttq._t || {};
            window.ttq.load = window.ttq.load || ((id, options = {}) => {
                const base = 'https://analytics.tiktok.com/i18n/pixel/events.js';
                window.ttq._i[id] = window.ttq._i[id] || [];
                window.ttq._i[id]._u = base;
                window.ttq._t[id] = +new Date();
                window.ttq._o[id] = options;
                loadScript(`${base}?sdkid=${encodeURIComponent(id)}&lib=ttq`);
            });
            window.ttq.load(pixelId);
            window.ttq.page();
        };

        const trackMeta = (eventName, payload) => {
            const mapped = providerEventName('meta', eventName);
            if (!mapped || !metaConfig.enabled || !providerAllowed('meta') || typeof window.fbq !== 'function') {
                return;
            }

            const normalized = normalizePayload(payload);
            window.fbq('track', mapped, normalized, cleanObject({
                eventID: normalized.event_id,
            }));
        };

        const trackTikTok = (eventName, payload) => {
            const mapped = providerEventName('tiktok', eventName);
            if (!mapped || !tiktokConfig.enabled || !providerAllowed('tiktok') || !window.ttq || typeof window.ttq.track !== 'function') {
                return;
            }

            const normalized = normalizePayload(payload);
            const properties = cleanObject({
                currency: normalized.currency,
                value: normalized.value,
                content_type: normalized.content_type,
                content_ids: normalized.content_ids,
                contents: normalized.contents?.map((item) => cleanObject({
                    content_id: item.id,
                    quantity: item.quantity,
                    price: item.item_price,
                })),
                num_items: normalized.num_items,
                content_name: normalized.content_name,
                event_id: normalized.event_id,
            });

            if (['ViewContent', 'AddToCart', 'InitiateCheckout', 'CompletePayment'].includes(mapped) && (!properties.content_ids || !properties.content_ids.length)) {
                return;
            }

            window.ttq.track(mapped, properties);
        };

        const trackGoogleAdsConversion = (eventName, payload) => {
            if (eventName !== 'purchase' || googleConfig.server_ads_enabled || typeof window.gtag !== 'function') {
                return;
            }

            const normalized = normalizePayload(payload);
            if (String(normalized.source_channel || '').toLowerCase() !== 'google_ads') {
                return;
            }

            const conversionId = String(googleConfig.ads_conversion_id || '').trim();
            const conversionLabel = String(googleConfig.ads_conversion_label || '').trim();
            if (!conversionId || !conversionLabel) {
                return;
            }

            window.gtag('event', 'conversion', cleanObject({
                send_to: `${conversionId}/${conversionLabel}`,
                value: normalized.value,
                currency: normalized.currency,
                transaction_id: normalized.transaction_id,
                event_id: normalized.event_id,
            }));
        };

        window.StorefrontAnalytics.pushEvent = function (eventName, parameters) {
            if (!eventName) {
                return;
            }

            const payload = parameters || {};
            window.dataLayer.push(Object.assign({ event: eventName }, payload));

            if (googleConfig.enabled && typeof window.gtag === 'function') {
                window.gtag('event', eventName, payload);
            }
        };

        window.StorefrontAnalytics.pushEcommerce = function (eventName, ecommerce) {
            if (!eventName) {
                return;
            }

            const payload = ecommerce || {};
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({ event: eventName, ecommerce: payload });

            if (googleConfig.enabled && typeof window.gtag === 'function') {
                window.gtag('event', eventName, payload);
                trackGoogleAdsConversion(eventName, payload);
            }

            trackMeta(eventName, payload);
            trackTikTok(eventName, payload);
        };

        initGoogle();
        initMeta();
        initTikTok();
        window.dispatchEvent(new CustomEvent('storefront-analytics:ready'));
    })();
</script>
