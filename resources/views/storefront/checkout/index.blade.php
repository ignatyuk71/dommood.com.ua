<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>Оформлення замовлення - {{ $storeName }}</title>
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
            $selectedDelivery = old('delivery_method', $deliveryMethods[0]['code'] ?? null);
            $selectedPayment = old('payment_method', $paymentMethods[0]['code'] ?? null);
            $activeDelivery = collect($deliveryMethods)->firstWhere('code', $selectedDelivery) ?? ($deliveryMethods[0] ?? ['price_cents' => 0]);
            $checkoutTotalCents = (int) $cart['total_cents'] + (int) ($activeDelivery['price_cents'] ?? 0);
        @endphp

        <div class="storefront-page storefront-checkout-page">
            <header class="storefront-checkout-topbar">
                <a href="{{ route('home') }}" class="storefront-checkout-logo" aria-label="{{ $storeName }} - головна">
                    <img src="{{ asset('brand/dom-mood-wordmark-black.png') }}" alt="{{ $storeName }}" width="168" height="28">
                </a>
                <nav aria-label="Checkout кроки">
                    <a href="{{ route('cart.show') }}">Кошик</a>
                    <span class="is-active">Checkout</span>
                    <span>Підтвердження</span>
                </nav>
            </header>

            <main class="storefront-checkout-shell">
                <section class="storefront-checkout-main" aria-labelledby="checkout-title">
                    <a href="{{ route('cart.show') }}" class="storefront-checkout-back">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        До кошика
                    </a>

                    <div class="storefront-checkout-heading">
                        <h1 id="checkout-title">Оформлення замовлення</h1>
                        <p>Без зайвих кроків: контакти, доставка, оплата і підтвердження менеджером.</p>
                    </div>

                    @if ($errors->any())
                        <div class="storefront-cart-alert is-error" role="alert">
                            Перевірте поля форми: частина даних потребує уточнення.
                        </div>
                    @endif

                    <form method="post" action="{{ route('checkout.store') }}" class="storefront-checkout-form" id="checkout-form">
                        @csrf

                        <section class="storefront-checkout-card" aria-labelledby="checkout-contact-title">
                            <div class="storefront-checkout-card__head">
                                <span>1</span>
                                <div>
                                    <h2 id="checkout-contact-title">Контакти отримувача</h2>
                                    <p>Менеджер використає ці дані для підтвердження замовлення.</p>
                                </div>
                            </div>

                            <div class="storefront-checkout-grid">
                                <label>
                                    <span>Імʼя</span>
                                    <input type="text" name="customer_first_name" value="{{ old('customer_first_name') }}" autocomplete="given-name" required>
                                    @error('customer_first_name')<small>{{ $message }}</small>@enderror
                                </label>
                                <label>
                                    <span>Прізвище</span>
                                    <input type="text" name="customer_last_name" value="{{ old('customer_last_name') }}" autocomplete="family-name">
                                    @error('customer_last_name')<small>{{ $message }}</small>@enderror
                                </label>
                                <label>
                                    <span>Телефон</span>
                                    <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" inputmode="tel" required>
                                    @error('customer_phone')<small>{{ $message }}</small>@enderror
                                </label>
                                <label>
                                    <span>Email</span>
                                    <input type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email">
                                    @error('customer_email')<small>{{ $message }}</small>@enderror
                                </label>
                            </div>
                        </section>

                        <section class="storefront-checkout-card" aria-labelledby="checkout-delivery-title">
                            <div class="storefront-checkout-card__head">
                                <span>2</span>
                                <div>
                                    <h2 id="checkout-delivery-title">Доставка</h2>
                                    <p>Підтвердимо відділення, поштомат або адресу перед відправкою.</p>
                                </div>
                            </div>

                            <div class="storefront-checkout-options">
                                @foreach ($deliveryMethods as $method)
                                    <label class="storefront-checkout-option" data-delivery-option data-price-cents="{{ (int) $method['price_cents'] }}">
                                        <input type="radio" name="delivery_method" value="{{ $method['code'] }}" @checked($selectedDelivery === $method['code'])>
                                        <span>
                                            <strong>{{ $method['name'] }}</strong>
                                            @if ($method['description'])
                                                <small>{{ $method['description'] }}</small>
                                            @endif
                                        </span>
                                        <b>{{ $method['price_cents'] > 0 ? $formatMoney($method['price_cents'], $cart['currency']) : 'Безкоштовно' }}</b>
                                    </label>
                                @endforeach
                                @error('delivery_method')<small class="storefront-field-error">{{ $message }}</small>@enderror
                            </div>

                            <div class="storefront-checkout-grid">
                                <label>
                                    <span>Місто</span>
                                    <input type="text" name="delivery_city" value="{{ old('delivery_city') }}" autocomplete="address-level2" required>
                                    @error('delivery_city')<small>{{ $message }}</small>@enderror
                                </label>
                                <label>
                                    <span>Відділення / поштомат</span>
                                    <input type="text" name="delivery_branch" value="{{ old('delivery_branch') }}" placeholder="Наприклад: Відділення 12">
                                    @error('delivery_branch')<small>{{ $message }}</small>@enderror
                                </label>
                                <label class="is-wide">
                                    <span>Адреса для курʼєра</span>
                                    <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" autocomplete="street-address">
                                    @error('delivery_address')<small>{{ $message }}</small>@enderror
                                </label>
                            </div>
                        </section>

                        <section class="storefront-checkout-card" aria-labelledby="checkout-payment-title">
                            <div class="storefront-checkout-card__head">
                                <span>3</span>
                                <div>
                                    <h2 id="checkout-payment-title">Оплата</h2>
                                    <p>Показуємо тільки активні методи з адмінки.</p>
                                </div>
                            </div>

                            <div class="storefront-checkout-options">
                                @foreach ($paymentMethods as $method)
                                    <label class="storefront-checkout-option">
                                        <input type="radio" name="payment_method" value="{{ $method['code'] }}" @checked($selectedPayment === $method['code'])>
                                        <span>
                                            <strong>{{ $method['name'] }}</strong>
                                            @if ($method['description'])
                                                <small>{{ $method['description'] }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                                @error('payment_method')<small class="storefront-field-error">{{ $message }}</small>@enderror
                            </div>
                        </section>

                        <section class="storefront-checkout-card" aria-labelledby="checkout-comment-title">
                            <div class="storefront-checkout-card__head">
                                <span>4</span>
                                <div>
                                    <h2 id="checkout-comment-title">Коментар</h2>
                                    <p>Розмір, подарункове пакування або уточнення для менеджера.</p>
                                </div>
                            </div>
                            <label class="storefront-checkout-textarea">
                                <span>Коментар до замовлення</span>
                                <textarea name="comment" rows="4">{{ old('comment') }}</textarea>
                                @error('comment')<small>{{ $message }}</small>@enderror
                            </label>
                        </section>

                        <label class="storefront-checkout-terms">
                            <input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required>
                            <span>
                                Погоджуюсь з умовами покупки та політикою конфіденційності.
                                @if ($checkoutSettings['terms_url'] ?? null)
                                    <a href="{{ url($checkoutSettings['terms_url']) }}" target="_blank" rel="noopener">Умови</a>
                                @endif
                            </span>
                        </label>
                        @error('terms_accepted')<small class="storefront-field-error">{{ $message }}</small>@enderror
                    </form>
                </section>

                <aside class="storefront-checkout-summary" aria-label="Підсумок замовлення">
                    <div class="storefront-checkout-summary__card">
                        <h2>Ваше замовлення</h2>

                        <div class="storefront-checkout-summary__items">
                            @foreach ($cart['items'] as $item)
                                <article>
                                    @if ($item['image_url'])
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['image_alt'] }}" loading="lazy">
                                    @else
                                        <span class="storefront-image-placeholder">{{ mb_substr($item['name'], 0, 2) }}</span>
                                    @endif
                                    <div>
                                        <strong>{{ $item['name'] }}</strong>
                                        <span>{{ $item['quantity'] }} x {{ $formatMoney($item['price_cents'], $item['currency']) }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                            <div class="storefront-checkout-totals" data-checkout-summary data-currency="{{ $cart['currency'] }}">
                            @if ($cart['discount_total_cents'] > 0)
                                <div>
                                    <span>Знижка</span>
                                    <strong>-{{ $formatMoney($cart['discount_total_cents'], $cart['currency']) }}</strong>
                                </div>
                            @endif
                            <div>
                                <span>Доставка</span>
                                <strong data-delivery-total>{{ (int) ($activeDelivery['price_cents'] ?? 0) > 0 ? $formatMoney($activeDelivery['price_cents'], $cart['currency']) : 'За тарифом' }}</strong>
                            </div>
                            <div class="is-grand">
                                <span>До оплати</span>
                                <strong data-order-total data-base-cents="{{ (int) $cart['total_cents'] }}">{{ $formatMoney($checkoutTotalCents, $cart['currency']) }}</strong>
                            </div>
                        </div>

                        <button type="submit" form="checkout-form" class="storefront-checkout-btn storefront-checkout-btn--primary">
                            Підтвердити замовлення
                        </button>

                        <p>Після оформлення менеджер перевірить наявність, доставку й напише у месенджер або зателефонує.</p>
                    </div>
                </aside>
            </main>
        </div>

        <script>
            (() => {
                const summary = document.querySelector('[data-checkout-summary]');

                if (!summary) {
                    return;
                }

                const deliveryTotal = summary.querySelector('[data-delivery-total]');
                const orderTotal = summary.querySelector('[data-order-total]');
                const baseCents = Number(orderTotal?.dataset.baseCents || 0);
                const currency = summary.dataset.currency || 'UAH';

                const formatMoney = (cents) => {
                    const value = Math.round(Number(cents || 0) / 100).toLocaleString('uk-UA');

                    return currency === 'UAH' ? `${value} грн` : `${value} ${currency}`;
                };

                document.querySelectorAll('[data-delivery-option] input[type="radio"]').forEach((input) => {
                    input.addEventListener('change', () => {
                        const option = input.closest('[data-delivery-option]');
                        const priceCents = Number(option?.dataset.priceCents || 0);

                        if (deliveryTotal) {
                            deliveryTotal.textContent = priceCents > 0 ? formatMoney(priceCents) : 'За тарифом';
                        }

                        if (orderTotal) {
                            orderTotal.textContent = formatMoney(baseCents + priceCents);
                        }
                    });
                });
            })();
        </script>
    </body>
</html>
