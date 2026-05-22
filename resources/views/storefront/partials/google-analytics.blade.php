@php
    $googleAnalytics = $storefrontGoogleAnalytics ?? [];
    $gaMeasurementId = $googleAnalytics['measurement_id'] ?? null;
    $gtmContainerId = $googleAnalytics['gtm_container_id'] ?? null;
    $gaDirectEnabled = (bool) ($googleAnalytics['direct_gtag'] ?? false);
    $gtmEnabled = (bool) ($googleAnalytics['gtm'] ?? false);
@endphp

<script>
    window.dataLayer = window.dataLayer || [];
    window.StorefrontAnalytics = window.StorefrontAnalytics || {};
    window.StorefrontAnalytics.googleEnabled = @json($gaDirectEnabled);
    window.StorefrontAnalytics.pushEvent = function (eventName, parameters) {
        if (!eventName) {
            return;
        }

        const payload = parameters || {};

        window.dataLayer.push(Object.assign({ event: eventName }, payload));

        if (window.StorefrontAnalytics.googleEnabled && typeof window.gtag === 'function') {
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

        if (window.StorefrontAnalytics.googleEnabled && typeof window.gtag === 'function') {
            window.gtag('event', eventName, payload);
        }
    };
</script>

@if ($gtmEnabled && $gtmContainerId)
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            const f = d.getElementsByTagName(s)[0];
            const j = d.createElement(s);
            const dl = l !== 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', @json($gtmContainerId));
    </script>
@endif

@if ($gaDirectEnabled && $gaMeasurementId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($gaMeasurementId) }}"></script>
    <script>
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', @json($gaMeasurementId));
    </script>
@endif
