@php
    $drawerOpen = (bool) ($drawerOpen ?? false);
    $cartPage = (bool) ($cartPage ?? false);
    $formatMoney = static function (?int $amount, string $currency = 'UAH'): string {
        $value = number_format(((int) $amount) / 100, 0, '.', ' ');

        return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
    };
    $freeShippingThreshold = max(1, (int) ($cart['free_shipping_threshold_cents'] ?? \App\Services\Storefront\DeliveryPolicyService::DEFAULT_FREE_SHIPPING_THRESHOLD_CENTS));
    $cartTotalCents = max(0, (int) ($cart['total_cents'] ?? 0));
    $freeShippingRemaining = max(0, (int) ($cart['free_shipping_remaining_cents'] ?? ($freeShippingThreshold - $cartTotalCents)));
    $freeShippingProgress = min(100, max(0, (int) ($cart['free_shipping_progress_percent'] ?? floor(($cartTotalCents / $freeShippingThreshold) * 100))));
    $freeShippingLabel = $cart['free_shipping_label'] ?? ($freeShippingRemaining > 0
        ? 'Додайте ще '.$formatMoney($freeShippingRemaining, $cart['currency'] ?? 'UAH').', щоб отримати безкоштовну доставку'
        : 'Безкоштовна доставка доступна для цього замовлення');
@endphp

<div
    @class(['storefront-cart-drawer-shell', 'is-open' => $drawerOpen])
    data-cart-drawer
    data-cart-drawer-url="{{ route('cart.drawer') }}"
    aria-hidden="{{ $drawerOpen ? 'false' : 'true' }}"
    @unless ($drawerOpen) inert @endunless
>
    @if ($cartPage)
        <a href="{{ url('/catalog') }}" class="storefront-cart-backdrop" aria-label="Повернутися до каталогу"></a>
    @else
        <button type="button" class="storefront-cart-backdrop" data-cart-close aria-label="Закрити кошик"></button>
    @endif

    <aside class="storefront-cart-panel" data-cart-drawer-panel role="dialog" aria-modal="true" aria-label="Кошик" tabindex="-1">
        <div class="storefront-cart-panel__head">
            @if ($cartPage)
                <a href="{{ url('/catalog') }}" aria-label="Повернутися до покупок">
                    <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <button type="button" data-cart-close aria-label="Закрити кошик">
                    <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            @endif
            <strong>Кошик</strong>
            <span aria-hidden="true"></span>

            @unless ($cart['is_empty'])
                <section
                    class="storefront-cart-free-shipping"
                    aria-label="Прогрес безкоштовної доставки"
                    data-free-shipping-progress
                    data-free-shipping-threshold="{{ $freeShippingThreshold }}"
                    data-free-shipping-current="{{ $cartTotalCents }}"
                    style="--free-progress-percent: {{ $freeShippingProgress }}%;"
                >
                    <p data-free-shipping-label>{{ $freeShippingLabel }}</p>
                    <div aria-hidden="true">
                        <span data-free-shipping-percent hidden>{{ $freeShippingProgress }}%</span>
                    </div>
                </section>
            @endunless
        </div>

        @if ($cart['is_empty'])
            <section class="storefront-cart-empty">
                <h1>Кошик порожній</h1>
                <p>Додайте товари з каталогу для швидкого оформлення.</p>
                <a href="{{ url('/catalog') }}" class="storefront-checkout-btn storefront-checkout-btn--primary">Перейти в каталог</a>
            </section>
        @else
            <section class="storefront-cart-list" aria-label="Товари в кошику">
                @foreach ($cart['items'] as $item)
                    <article class="storefront-cart-item">
                        <a href="{{ $item['url'] ?? url('/catalog') }}" class="storefront-cart-item__image" aria-label="{{ $item['name'] }}">
                            @if ($item['image_url'])
                                <img src="{{ $item['image_url'] }}" alt="{{ $item['image_alt'] }}" loading="lazy">
                            @else
                                <span class="storefront-image-placeholder">{{ mb_substr($item['name'], 0, 2) }}</span>
                            @endif
                        </a>

                        <div class="storefront-cart-item__body">
                            <h2>{{ $item['name'] }}</h2>
                            <div class="storefront-cart-item__meta">
                                @if (count($item['variant_options'] ?? []) > 1)
                                    @php
                                        $hasCurrentVariantOption = collect($item['variant_options'])->contains('is_current', true);
                                    @endphp
                                    <form method="post" action="{{ route('cart.items.update', $item['id']) }}" class="storefront-cart-variant" data-cart-action data-cart-variant-form>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $item['quantity'] }}">
                                        <label>
                                            <span>Розмір</span>
                                            <select name="product_variant_id" aria-label="Вибрати варіант {{ $item['name'] }}" data-cart-variant-select>
                                                @unless ($hasCurrentVariantOption)
                                                    <option value="" selected disabled>Оберіть</option>
                                                @endunless
                                                @foreach ($item['variant_options'] as $variantOption)
                                                    <option
                                                        value="{{ $variantOption['id'] }}"
                                                        @selected($variantOption['is_current'])
                                                        @disabled(! ($variantOption['is_available'] ?? true) && ! $variantOption['is_current'])
                                                    >
                                                        {{ $variantOption['label'] }}@if (! ($variantOption['is_available'] ?? true)) — немає@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </form>
                                @elseif ($item['variant_name'])
                                    <p>{{ $item['variant_name'] }}</p>
                                @endif
                                <div class="storefront-cart-item__price">
                                    <span>{{ $formatMoney($item['price_cents'], $item['currency']) }}</span>
                                </div>
                            </div>

                            <div class="storefront-cart-item__controls">
                                <form method="post" action="{{ route('cart.items.update', $item['id']) }}" class="storefront-cart-qty" aria-label="Кількість {{ $item['name'] }}" data-cart-action>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" name="quantity" value="{{ max(0, $item['quantity'] - 1) }}" aria-label="Зменшити кількість">
                                        <svg viewBox="0 0 24 24"><path d="M5 12h14"/></svg>
                                    </button>
                                    <span>{{ $item['quantity'] }}</span>
                                    <button type="submit" name="quantity" value="{{ min(99, $item['quantity'] + 1) }}" aria-label="Збільшити кількість">
                                        <svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                    </button>
                                </form>

                                <form method="post" action="{{ route('cart.items.destroy', $item['id']) }}" data-cart-action>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="storefront-cart-remove" aria-label="Видалити {{ $item['name'] }}">
                                        <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <details class="storefront-cart-coupon" @if ($errors->has('code') || filled($cart['promocode_code'])) open @endif>
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9V5h18v4a3 3 0 0 0 0 6v4H3v-4a3 3 0 0 0 0-6Z"/><path d="M13 5v14"/></svg>
                    Є купон зі знижкою?
                </summary>
                @if ($cart['promocode_code'])
                    <div class="storefront-cart-coupon__active">
                        <span>Активний купон: <strong>{{ $cart['promocode_code'] }}</strong></span>
                        <form method="post" action="{{ route('cart.promocode.clear') }}" data-cart-action>
                            @csrf
                            @method('DELETE')
                            <button type="submit">Прибрати</button>
                        </form>
                    </div>
                @else
                    <form method="post" action="{{ route('cart.promocode.apply') }}" data-cart-action>
                        @csrf
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="Код купона" aria-label="Код купона">
                        <button type="submit">Застосувати</button>
                    </form>
                    @error('code')
                        <p class="storefront-cart-coupon__error">{{ $message }}</p>
                    @enderror
                @endif
            </details>

            <section class="storefront-cart-total" aria-label="Підсумок кошика">
                @if ($cart['discount_total_cents'] > 0)
                    <div>
                        <span>Знижка</span>
                        <strong>-{{ $formatMoney($cart['discount_total_cents'], $cart['currency']) }}</strong>
                    </div>
                @endif
                <div class="is-grand">
                    <span>Разом</span>
                    <strong>{{ $formatMoney($cart['total_cents'], $cart['currency']) }}</strong>
                </div>
            </section>

            <div class="storefront-cart-actions">
                <a href="{{ route('checkout.index') }}" class="storefront-checkout-btn storefront-checkout-btn--primary">Оформити замовлення</a>
                @if ($cartPage)
                    <a href="{{ url('/catalog') }}" class="storefront-checkout-btn storefront-checkout-btn--ghost">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        Продовжити покупки
                    </a>
                @else
                    <button type="button" class="storefront-checkout-btn storefront-checkout-btn--ghost" data-cart-close>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        Продовжити покупки
                    </button>
                @endif
            </div>
        @endif

        @if (count($recommendedProducts) > 0)
            <section class="storefront-cart-recommendations" aria-labelledby="cart-recommendations-title">
                <h2 id="cart-recommendations-title">Рекомендуємо придбати</h2>
                <div class="storefront-cart-recommendations__rail">
                    @foreach ($recommendedProducts as $product)
                        <article>
                            <a href="{{ $product['url'] }}">
                                @if ($product['image_url'])
                                    <img src="{{ $product['image_url'] }}" alt="{{ $product['image_alt'] }}" loading="lazy">
                                @else
                                    <span class="storefront-image-placeholder">{{ mb_substr($product['name'], 0, 2) }}</span>
                                @endif
                                <span>{{ $product['name'] }}</span>
                            </a>
                            <form method="post" action="{{ route('cart.items.store') }}" data-cart-form data-cart-add>
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit">Додати</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </aside>
</div>
