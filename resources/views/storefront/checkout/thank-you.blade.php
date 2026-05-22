<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.site-noindex')
        @unless (config('seo.noindex_site'))
            <meta name="robots" content="noindex,nofollow">
        @endunless
        <title>Дякуємо за замовлення - {{ $storeName }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#29277f">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/storefront.css', 'resources/css/storefront-checkout.css'])
        @else
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront.css')])
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront-checkout.css')])
        @endif
        @include('storefront.partials.google-analytics')
    </head>
    <body>
        @php
            $formatMoney = static function (?int $amount, string $currency = 'UAH'): string {
                $value = number_format(((int) $amount) / 100, 0, '.', ' ');

                return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
            };

            $paymentLabels = [
                'cod' => 'Оплата при отриманні',
                'cash_on_delivery' => 'Оплата при отриманні',
                'card' => 'Оплата карткою',
                'liqpay' => 'LiqPay',
                'monobank' => 'Monobank',
                'mono' => 'Monobank',
                'iban' => 'Оплата на рахунок',
            ];
            $deliveryLabels = [
                'nova_poshta_branch' => 'Нова пошта: відділення',
                'nova_poshta_postomat' => 'Нова пошта: поштомат',
                'nova_poshta_courier' => 'Нова пошта: курʼєр',
                'ukrposhta' => 'Укрпошта',
                'courier' => 'Курʼєр',
                'pickup' => 'Самовивіз',
            ];
            $deliveryAddress = collect([$order->delivery_city, $order->delivery_branch ?: $order->delivery_address])
                ->filter()
                ->implode(', ');
            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => 'Оформлення замовлення', 'url' => route('checkout.index')],
                ['label' => 'Дякуємо за замовлення'],
            ];
            $purchaseAnalytics = \App\Support\Storefront\EcommerceAnalytics::purchase($order);
        @endphp

        <div class="storefront-page storefront-checkout-page">
            @include('storefront.partials.site-header')

            <main class="storefront-thankyou-page">
                <div class="container">
                    @include('storefront.partials.breadcrumbs', ['items' => $breadcrumbs])

                    <section class="storefront-thankyou-hero" aria-labelledby="thankyou-title">
                        <span class="storefront-thankyou-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <div>
                            <p>Замовлення #{{ $order->order_number }}</p>
                            <h1 id="thankyou-title">Дякуємо, замовлення прийнято</h1>
                            <span>Менеджер перевірить наявність, доставку та звʼяжеться з вами найближчим робочим часом.</span>
                        </div>
                    </section>

                    <div class="storefront-thankyou-layout">
                        <section class="storefront-thankyou-card storefront-thankyou-details" aria-labelledby="thankyou-details-title">
                            <h2 id="thankyou-details-title">Деталі замовлення</h2>

                            <div class="storefront-thankyou-meta">
                                <div class="storefront-thankyou-meta__item">
                                    <span>Сума</span>
                                    <strong>{{ $formatMoney($order->total_cents, $order->currency) }}</strong>
                                </div>
                                <div class="storefront-thankyou-meta__item">
                                    <span>Отримувач</span>
                                    <strong>{{ $order->customer_name }}</strong>
                                </div>
                                <div class="storefront-thankyou-meta__item">
                                    <span>Телефон</span>
                                    <strong>{{ $order->customer_phone }}</strong>
                                </div>
                                <div class="storefront-thankyou-meta__item storefront-thankyou-meta__item--wide">
                                    <span>Доставка</span>
                                    <strong>{{ $deliveryLabels[$order->delivery_method] ?? $order->delivery_method ?? 'Уточнюється' }}</strong>
                                </div>
                                <div class="storefront-thankyou-meta__item storefront-thankyou-meta__item--wide">
                                    <span>Адреса</span>
                                    <strong>{{ $deliveryAddress !== '' ? $deliveryAddress : 'Уточнюється' }}</strong>
                                </div>
                                <div class="storefront-thankyou-meta__item storefront-thankyou-meta__item--wide">
                                    <span>Оплата</span>
                                    <strong>{{ $paymentLabels[$order->payment_method] ?? $order->payment_method ?? 'Уточнюється' }}</strong>
                                </div>
                            </div>

                            <div class="storefront-thankyou-next">
                                <h2>Що далі</h2>
                                <ol>
                                    <li>Ми перевіримо наявність товарів і коректність даних доставки.</li>
                                    <li>Менеджер підтвердить замовлення телефоном або в месенджері.</li>
                                    <li>Після відправки ви отримаєте номер ТТН Нової пошти.</li>
                                </ol>
                            </div>
                        </section>

                        <aside class="storefront-thankyou-card storefront-thankyou-summary" aria-labelledby="thankyou-summary-title">
                            <h2 id="thankyou-summary-title">Ваше замовлення</h2>

                            <div class="storefront-thankyou-items">
                                @foreach ($order->items as $item)
                                    @php($snapshot = $item->product_snapshot ?? [])
                                    <article>
                                        @if ($snapshot['image_url'] ?? null)
                                            <img src="{{ $snapshot['image_url'] }}" alt="{{ $snapshot['image_alt'] ?? $item->product_name }}" loading="lazy">
                                        @else
                                            <span class="storefront-image-placeholder">{{ mb_substr($item->product_name, 0, 2) }}</span>
                                        @endif
                                        <div>
                                            <h3>{{ $item->product_name }}</h3>
                                            <p>
                                                @if ($item->variant_name)
                                                    <span>{{ $item->variant_name }}</span>
                                                @endif
                                                @if ($item->sku)
                                                    <span>Арт. {{ $item->sku }}</span>
                                                @endif
                                            </p>
                                            <strong>{{ $item->quantity }} шт. · {{ $formatMoney($item->total_cents, $order->currency) }}</strong>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if ($liqPayPayload)
                                <form method="post" action="https://www.liqpay.ua/api/3/checkout" accept-charset="utf-8" class="storefront-thankyou-payment">
                                    <input type="hidden" name="data" value="{{ $liqPayPayload['data'] }}">
                                    <input type="hidden" name="signature" value="{{ $liqPayPayload['signature'] }}">
                                    <button type="submit" class="storefront-checkout-btn storefront-checkout-btn--primary">Оплатити онлайн</button>
                                </form>
                            @endif

                            <div class="storefront-thankyou-actions">
                                <a href="{{ url('/catalog') }}" class="storefront-checkout-btn storefront-checkout-btn--primary">Повернутися в каталог</a>
                                @if ($supportPhone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" class="storefront-checkout-btn storefront-checkout-btn--ghost">Звʼязатися з магазином</a>
                                @endif
                            </div>
                        </aside>
                    </div>
                </div>
            </main>

            @include('storefront.partials.site-footer')
        </div>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.storefront-ui-scripts')
        @include('storefront.partials.cart-drawer-scripts')

        <script>
            (() => {
                const payload = @json($purchaseAnalytics);
                const eventId = payload.event_id || payload.transaction_id || '';
                const storageKey = eventId ? `dommood_purchase_${eventId}` : '';

                try {
                    if (storageKey && window.sessionStorage?.getItem(storageKey)) {
                        return;
                    }
                } catch (error) {}

                window.StorefrontAnalytics?.pushEcommerce?.('purchase', payload);

                try {
                    if (storageKey) {
                        window.sessionStorage?.setItem(storageKey, '1');
                    }
                } catch (error) {}
            })();
        </script>
    </body>
</html>
