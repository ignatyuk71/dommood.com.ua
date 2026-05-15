<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>Замовлення оформлено - {{ $storeName }}</title>
        @if (file_exists(public_path('hot')))
            @vite('resources/css/storefront.css')
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
        @endif
    </head>
    <body>
        @php
            $formatMoney = static function (?int $amount, string $currency = 'UAH'): string {
                $value = number_format(((int) $amount) / 100, 0, '.', ' ');

                return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
            };
            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => 'Оформлення замовлення', 'url' => route('checkout.index')],
                ['label' => 'Дякуємо за замовлення'],
            ];
        @endphp

        <div class="storefront-page storefront-checkout-page">
            <header class="storefront-checkout-topbar">
                <a href="{{ route('home') }}" class="storefront-checkout-logo" aria-label="{{ $storeName }} - головна">
                    <img src="{{ asset('brand/dom-mood-wordmark-black.png') }}" alt="{{ $storeName }}" width="168" height="28">
                </a>
                <nav aria-label="Checkout кроки">
                    <span>Кошик</span>
                    <span>Checkout</span>
                    <span class="is-active">Підтвердження</span>
                </nav>
            </header>

            <main class="storefront-thankyou">
                @include('storefront.partials.breadcrumbs', ['items' => $breadcrumbs])

                <section class="storefront-thankyou-card" aria-labelledby="thankyou-title">
                    <span class="storefront-thankyou-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <h1 id="thankyou-title">Замовлення оформлено</h1>
                    <p>Номер замовлення <strong>#{{ $order->order_number }}</strong>. Менеджер підтвердить деталі й наявність найближчим робочим часом.</p>

                    <div class="storefront-thankyou-meta">
                        <div>
                            <span>Сума</span>
                            <strong>{{ $formatMoney($order->total_cents, $order->currency) }}</strong>
                        </div>
                        <div>
                            <span>Отримувач</span>
                            <strong>{{ $order->customer_name }}</strong>
                        </div>
                        <div>
                            <span>Доставка</span>
                            <strong>{{ $order->delivery_city ?: 'Уточнюється' }}</strong>
                        </div>
                    </div>

                    @if ($liqPayPayload)
                        <form method="post" action="https://www.liqpay.ua/api/3/checkout" accept-charset="utf-8" class="storefront-thankyou-payment">
                            <input type="hidden" name="data" value="{{ $liqPayPayload['data'] }}">
                            <input type="hidden" name="signature" value="{{ $liqPayPayload['signature'] }}">
                            <button type="submit" class="storefront-checkout-btn storefront-checkout-btn--primary">Оплатити онлайн</button>
                        </form>
                    @endif

                    <div class="storefront-thankyou-actions">
                        <a href="{{ url('/catalog') }}" class="storefront-checkout-btn storefront-checkout-btn--ghost">Повернутися в каталог</a>
                        @if ($supportPhone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" class="storefront-checkout-btn storefront-checkout-btn--ghost">Звʼязатися з магазином</a>
                        @endif
                    </div>
                </section>
            </main>
        </div>

        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'purchase',
                ecommerce: {
                    transaction_id: @json($order->order_number),
                    value: {{ number_format(((int) $order->total_cents) / 100, 2, '.', '') }},
                    currency: @json($order->currency ?: 'UAH'),
                    items: @json($order->items->map(fn ($item) => [
                        'item_id' => $item->sku ?: (string) $item->product_id,
                        'item_name' => $item->product_name,
                        'item_variant' => $item->variant_name,
                        'price' => round(((int) $item->price_cents) / 100, 2),
                        'quantity' => (int) $item->quantity,
                    ])->values())
                }
            });
        </script>
    </body>
</html>
