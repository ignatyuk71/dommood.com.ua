<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.site-noindex')
        @unless (config('seo.noindex_site'))
            <meta name="robots" content="noindex,nofollow">
        @endunless
        <title>Оформлення замовлення - {{ $storeName }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#29277f">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/storefront.css', 'resources/css/storefront-checkout.css'])
        @else
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront.css')])
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront-checkout.css')])
        @endif
    </head>
    <body>
        @php
            $formatMoney = static function (?int $amount, string $currency = 'UAH'): string {
                $value = number_format(((int) $amount) / 100, 0, '.', ' ');

                return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
            };

            $quantityLabel = static function (int $quantity): string {
                $lastTwo = abs($quantity) % 100;
                $last = abs($quantity) % 10;

                if ($last === 1 && $lastTwo !== 11) {
                    return $quantity.' товар';
                }

                if ($last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14)) {
                    return $quantity.' товари';
                }

                return $quantity.' товарів';
            };

            $selectedDelivery = old('delivery_method', $deliveryMethods[0]['code'] ?? null);
            $selectedPayment = old('payment_method', $paymentMethods[0]['code'] ?? null);
            $activeDelivery = collect($deliveryMethods)->firstWhere('code', $selectedDelivery) ?? ($deliveryMethods[0] ?? ['price_cents' => 0, 'type' => 'branch']);
            $activeDeliveryType = $activeDelivery['type'] ?? 'branch';
            $checkoutTotalCents = (int) $cart['total_cents'] + (int) ($activeDelivery['price_cents'] ?? 0);
            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => 'Кошик', 'url' => route('cart.show')],
                ['label' => 'Оформлення замовлення'],
            ];
            $itemsSummary = $quantityLabel((int) $cart['quantity_count']).' на суму '.$formatMoney((int) $cart['total_cents'], $cart['currency']);
        @endphp

        <div class="storefront-page storefront-checkout-page">
            @include('storefront.partials.site-header')

            <main class="storefront-checkout-main-page">
                <div class="container">
                    @include('storefront.partials.breadcrumbs', ['items' => $breadcrumbs])

                    <div class="storefront-checkout-title">
                        <a href="{{ route('cart.show') }}" class="storefront-checkout-back">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            До кошика
                        </a>
                        <h1 id="checkout-title">Оформлення замовлення</h1>
                    </div>

                    @php
                        $checkoutNotice = trim((string) ($checkoutSettings['notice_text'] ?? ''));
                    @endphp

                    @if ($errors->any())
                        <div class="storefront-cart-alert is-error" role="alert">
                            Перевірте поля форми: частина даних потребує уточнення.
                        </div>
                    @endif

                    <div class="storefront-checkout-layout" data-checkout-form>
                        <section class="storefront-checkout-flow" aria-labelledby="checkout-title">
                            @if ($checkoutNotice !== '')
                                <section class="storefront-checkout-notice" aria-labelledby="checkout-notice-title">
                                    <div class="storefront-checkout-notice__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 4.6 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z"/></svg>
                                    </div>
                                    <div>
                                        <h2 id="checkout-notice-title">Важлива інформація</h2>
                                        <p>{{ $checkoutNotice }}</p>
                                    </div>
                                </section>
                            @endif

                            <section class="storefront-checkout-card storefront-checkout-order-card" aria-labelledby="checkout-order-title">
                                <div class="storefront-checkout-card__head">
                                    <span>1</span>
                                    <div>
                                        <h2 id="checkout-order-title">Ваше замовлення</h2>
                                        <p>{{ $itemsSummary }}</p>
                                    </div>
                                </div>

                                <div class="storefront-checkout-order-list">
                                    @foreach ($cart['items'] as $item)
                                        <article class="storefront-checkout-order-item">
                                            <a href="{{ $item['url'] ?? '#' }}" class="storefront-checkout-order-item__image" aria-label="{{ $item['name'] }}">
                                                @if ($item['image_url'])
                                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['image_alt'] }}" loading="lazy">
                                                @else
                                                    <span class="storefront-image-placeholder">{{ mb_substr($item['name'], 0, 2) }}</span>
                                                @endif
                                            </a>
                                            <div class="storefront-checkout-order-item__body">
                                                <h3>{{ $item['name'] }}</h3>

                                                @if (count($item['variant_options'] ?? []) > 1)
                                                    @php
                                                        $hasCurrentVariantOption = collect($item['variant_options'])->contains('is_current', true);
                                                    @endphp
                                                    <form method="post" action="{{ route('cart.items.update', $item['id']) }}" class="storefront-checkout-order-variant" data-checkout-cart-action data-checkout-variant-form>
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="quantity" value="{{ $item['quantity'] }}">
                                                        <label>
                                                            <span>Розмір</span>
                                                            <select name="product_variant_id" aria-label="Вибрати розмір {{ $item['name'] }}" data-checkout-variant-select>
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
                                                @endif
                                            </div>
                                            <div class="storefront-checkout-order-item__actions">
                                                <div class="storefront-checkout-order-item__meta">
                                                    <strong>{{ $formatMoney($item['total_cents'], $item['currency']) }}</strong>
                                                </div>

                                                <form method="post" action="{{ route('cart.items.update', $item['id']) }}" class="storefront-cart-qty" aria-label="Кількість {{ $item['name'] }}" data-checkout-cart-action>
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

                                                <form method="post" action="{{ route('cart.items.destroy', $item['id']) }}" data-checkout-cart-action>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="storefront-cart-remove" aria-label="Видалити {{ $item['name'] }}">
                                                        <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>

                            <form method="post" action="{{ route('checkout.store') }}" class="storefront-checkout-form" id="checkout-form">
                                @csrf

                            <section class="storefront-checkout-card" aria-labelledby="checkout-contact-title">
                                <div class="storefront-checkout-card__head">
                                    <span>2</span>
                                    <div>
                                        <h2 id="checkout-contact-title">Отримувач</h2>
                                        <p>Вкажіть дані людини, яка отримає посилку.</p>
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
                                        <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" inputmode="tel" placeholder="+38 (0__) ___-__-__" maxlength="19" data-phone-mask required>
                                        @error('customer_phone')<small>{{ $message }}</small>@enderror
                                    </label>
                                    <label>
                                        <span>Email <em>не обовʼязково</em></span>
                                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" placeholder="Для чека або статусу замовлення">
                                        @error('customer_email')<small>{{ $message }}</small>@enderror
                                    </label>
                                </div>
                            </section>

                            <section class="storefront-checkout-card" aria-labelledby="checkout-delivery-title">
                                <div class="storefront-checkout-card__head">
                                    <span>3</span>
                                    <div>
                                        <h2 id="checkout-delivery-title">Доставка</h2>
                                        <p>Нова пошта: оберіть місто, потім відділення, поштомат або адресу курʼєра.</p>
                                    </div>
                                </div>

                                <div class="storefront-checkout-options storefront-checkout-delivery-options">
                                    @foreach ($deliveryMethods as $method)
                                        <label class="storefront-checkout-option" data-delivery-option data-delivery-type="{{ $method['type'] ?? 'branch' }}" data-provider="{{ $method['provider'] ?? 'manual' }}" data-price-cents="{{ (int) $method['price_cents'] }}">
                                            <input type="radio" name="delivery_method" value="{{ $method['code'] }}" @checked($selectedDelivery === $method['code'])>
                                            <span>
                                                <strong>{{ $method['name'] }}</strong>
                                                @if ($method['description'])
                                                    <small>{{ $method['description'] }}</small>
                                                @endif
                                            </span>
                                            <b>{{ $method['price_cents'] > 0 ? $formatMoney($method['price_cents'], $cart['currency']) : 'За тарифом' }}</b>
                                        </label>
                                    @endforeach
                                    @error('delivery_method')<small class="storefront-field-error">{{ $message }}</small>@enderror
                                </div>

                                <div class="storefront-checkout-grid storefront-checkout-delivery-fields">
                                    <label class="storefront-checkout-lookup" data-checkout-city>
                                        <span>Місто доставки</span>
                                        <input type="text" name="delivery_city" value="{{ old('delivery_city') }}" autocomplete="address-level2" placeholder="Почніть вводити місто" required>
                                        <input type="hidden" name="delivery_city_ref" value="{{ old('delivery_city_ref') }}">
                                        <div class="storefront-checkout-lookup__status" data-lookup-status hidden></div>
                                        <div class="storefront-checkout-lookup__results" data-lookup-results hidden></div>
                                        @error('delivery_city')<small>{{ $message }}</small>@enderror
                                    </label>

                                    <label class="storefront-checkout-lookup" data-checkout-warehouse @if ($activeDeliveryType === 'courier') hidden @endif>
                                        <span data-warehouse-label>{{ $activeDeliveryType === 'postomat' ? 'Поштомат' : 'Відділення' }}</span>
                                        <input type="text" name="delivery_branch" value="{{ old('delivery_branch') }}" placeholder="Номер або адреса відділення">
                                        <input type="hidden" name="delivery_branch_ref" value="{{ old('delivery_branch_ref') }}">
                                        <div class="storefront-checkout-lookup__status" data-lookup-status hidden></div>
                                        <div class="storefront-checkout-lookup__results" data-lookup-results hidden></div>
                                        @error('delivery_branch')<small>{{ $message }}</small>@enderror
                                    </label>

                                    <label class="is-wide" data-checkout-address @if ($activeDeliveryType !== 'courier') hidden @endif>
                                        <span>Адреса для курʼєра</span>
                                        <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" autocomplete="street-address" placeholder="Вулиця, будинок, квартира">
                                        @error('delivery_address')<small>{{ $message }}</small>@enderror
                                    </label>
                                </div>
                            </section>

                            <section class="storefront-checkout-card" aria-labelledby="checkout-payment-title">
                                <div class="storefront-checkout-card__head">
                                    <span>4</span>
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
                                    <span>5</span>
                                    <div>
                                        <h2 id="checkout-comment-title">Коментар</h2>
                                        <p>Розмір, подарункове пакування або уточнення для менеджера.</p>
                                        <button
                                            type="button"
                                            class="storefront-checkout-comment-toggle"
                                            data-checkout-comment-toggle
                                            aria-expanded="true"
                                            aria-controls="checkout-comment-body"
                                        >
                                            <span data-checkout-comment-toggle-text>Згорнути коментар</span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div id="checkout-comment-body" data-checkout-comment-body data-has-content="{{ old('comment') || $errors->has('comment') ? 'true' : 'false' }}">
                                    <label class="storefront-checkout-textarea">
                                        <span>Коментар до замовлення</span>
                                        <textarea name="comment" rows="4">{{ old('comment') }}</textarea>
                                        @error('comment')<small>{{ $message }}</small>@enderror
                                    </label>
                                </div>
                            </section>
                            </form>
                        </section>

                        <aside class="storefront-checkout-summary" aria-label="Підсумок замовлення">
                            <div class="storefront-checkout-summary__card">
                                <h2>Разом до сплати</h2>
                                <div class="storefront-checkout-summary__line">
                                    <span>{{ $quantityLabel((int) $cart['quantity_count']) }}</span>
                                    <strong>{{ $formatMoney($cart['subtotal_cents'], $cart['currency']) }}</strong>
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

                                <label class="storefront-checkout-terms">
                                    <input type="checkbox" name="terms_accepted" value="1" form="checkout-form" @checked(old('terms_accepted')) required>
                                    <span>
                                        Погоджуюсь з умовами покупки та політикою конфіденційності.
                                        @if ($checkoutSettings['terms_url'] ?? null)
                                            <a href="{{ url($checkoutSettings['terms_url']) }}" target="_blank" rel="noopener">Умови</a>
                                        @endif
                                    </span>
                                </label>
                                @error('terms_accepted')<small class="storefront-field-error">{{ $message }}</small>@enderror

                                <button type="submit" form="checkout-form" class="storefront-checkout-btn storefront-checkout-btn--primary">
                                    Підтвердити замовлення
                                </button>

                                <p>Після оформлення менеджер перевірить наявність, доставку й напише у месенджер або зателефонує.</p>
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
                const form = document.querySelector('[data-checkout-form]');
                const summary = document.querySelector('[data-checkout-summary]');

                if (!form || !summary) {
                    return;
                }

                const cityLookup = form.querySelector('[data-checkout-city]');
                const warehouseLookup = form.querySelector('[data-checkout-warehouse]');
                const addressField = form.querySelector('[data-checkout-address]');
                const warehouseLabel = form.querySelector('[data-warehouse-label]');
                const deliveryTotal = summary.querySelector('[data-delivery-total]');
                const orderTotal = summary.querySelector('[data-order-total]');
                const baseCents = Number(orderTotal?.dataset.baseCents || 0);
                const currency = summary.dataset.currency || 'UAH';
                const commentToggle = form.querySelector('[data-checkout-comment-toggle]');
                const commentToggleText = form.querySelector('[data-checkout-comment-toggle-text]');
                const commentBody = form.querySelector('[data-checkout-comment-body]');
                const endpoints = {
                    cities: @json(route('shipping.nova-poshta.cities')),
                    warehouses: @json(route('shipping.nova-poshta.warehouses')),
                };
                let cityTimer = null;
                let warehouseTimer = null;
                let activeCityRequest = null;
                let activeWarehouseRequest = null;
                const phoneInput = document.querySelector('[data-phone-mask]');
                const warehouseLimit = 30;
                const mobileCheckoutQuery = window.matchMedia('(max-width: 767.98px)');
                const warehouseState = {
                    query: '',
                    page: 1,
                    hasMore: false,
                    isLoading: false,
                    type: '',
                };

                const firstErrorMessage = (payload) => {
                    const errors = payload?.errors || {};
                    const firstField = Object.keys(errors)[0];

                    if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
                        return errors[firstField][0];
                    }

                    return payload?.message || 'Не вдалося оновити кошик. Спробуйте ще раз.';
                };

                const setCartActionState = (cartForm, isSubmitting) => {
                    cartForm.querySelectorAll('button, select').forEach((control) => {
                        control.toggleAttribute('disabled', isSubmitting);

                        if (isSubmitting) {
                            control.setAttribute('aria-busy', 'true');
                        } else {
                            control.removeAttribute('aria-busy');
                        }
                    });
                };

                const submitCheckoutCartAction = async (cartForm, submitter = null) => {
                    if (cartForm.dataset.checkoutCartState === 'submitting') {
                        return;
                    }

                    cartForm.dataset.checkoutCartState = 'submitting';
                    let formData;

                    try {
                        try {
                            formData = new FormData(cartForm, submitter);
                        } catch (error) {
                            formData = new FormData(cartForm);

                            if (submitter?.name) {
                                formData.append(submitter.name, submitter.value);
                            }
                        }

                        setCartActionState(cartForm, true);

                        const response = await fetch(cartForm.action, {
                            method: (cartForm.getAttribute('method') || 'post').toUpperCase(),
                            body: formData,
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            window.StorefrontFeedback?.showToast?.({ message: firstErrorMessage(data), type: 'error' });
                            return;
                        }

                        window.location.reload();
                    } catch (error) {
                        window.StorefrontFeedback?.showToast?.({ message: 'Кошик тимчасово не оновився. Перевірте зʼєднання і спробуйте ще раз.', type: 'error' });
                    } finally {
                        delete cartForm.dataset.checkoutCartState;
                        setCartActionState(cartForm, false);
                    }
                };

                const setCommentExpanded = (expanded) => {
                    if (!commentBody || !commentToggle || !commentToggleText) {
                        return;
                    }

                    commentBody.hidden = !expanded;
                    commentToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    commentToggleText.textContent = expanded ? 'Згорнути коментар' : 'Додати коментар';
                };

                const syncCommentVisibility = () => {
                    if (!commentBody || !commentToggle) {
                        return;
                    }

                    if (!mobileCheckoutQuery.matches) {
                        setCommentExpanded(true);
                        return;
                    }

                    setCommentExpanded(commentBody.dataset.hasContent === 'true');
                };

                const formatMoney = (cents) => {
                    const value = Math.round(Number(cents || 0) / 100).toLocaleString('uk-UA');

                    return currency === 'UAH' ? `${value} грн` : `${value} ${currency}`;
                };

                const formatPhone = (value) => {
                    const rawValue = String(value || '');
                    const hasVisiblePrefix = rawValue.includes('+38') || rawValue.includes('(0');
                    let digits = rawValue.replace(/\D/g, '');

                    if (!digits && !rawValue.trim()) {
                        return '';
                    }

                    if (digits.startsWith('380')) {
                        digits = digits.slice(3);
                    } else if (digits.startsWith('38')) {
                        digits = digits.slice(2);
                    }

                    digits = digits.replace(/^0+/, '');
                    digits = digits.slice(0, 9);

                    const operator = digits.slice(0, 2);
                    const first = digits.slice(2, 5);
                    const second = digits.slice(5, 7);
                    const third = digits.slice(7, 9);

                    let formatted = '+38';

                    if (digits.length > 0 || hasVisiblePrefix) {
                        formatted += ` (0${operator}`;
                    }

                    if (operator.length === 2) {
                        formatted += ')';
                    }

                    if (first.length > 0) {
                        formatted += ` ${first}`;
                    }

                    if (second.length > 0) {
                        formatted += `-${second}`;
                    }

                    if (third.length > 0) {
                        formatted += `-${third}`;
                    }

                    return formatted;
                };

                const selectedDelivery = () => form.querySelector('[data-delivery-option] input[type="radio"]:checked')?.closest('[data-delivery-option]');

                const lookupParts = (lookup) => ({
                    input: lookup?.querySelector('input[type="text"]') ?? null,
                    ref: lookup?.querySelector('input[type="hidden"]') ?? null,
                    status: lookup?.querySelector('[data-lookup-status]') ?? null,
                    results: lookup?.querySelector('[data-lookup-results]') ?? null,
                });

                const setStatus = (lookup, message = '', isError = false) => {
                    const { status } = lookupParts(lookup);

                    if (!status) {
                        return;
                    }

                    status.textContent = message;
                    status.hidden = message === '';
                    status.classList.toggle('is-error', isError);
                };

                const clearResults = (lookup) => {
                    const { results } = lookupParts(lookup);

                    if (!results) {
                        return;
                    }

                    results.replaceChildren();
                    results.hidden = true;
                };

                const resetWarehouseState = () => {
                    warehouseState.query = '';
                    warehouseState.page = 1;
                    warehouseState.hasMore = false;
                    warehouseState.isLoading = false;
                    warehouseState.type = '';
                };

                const renderResults = (lookup, items, onSelect, append = false) => {
                    const { results } = lookupParts(lookup);

                    if (!results) {
                        return;
                    }

                    if (!append) {
                        results.replaceChildren();
                    }

                    items.forEach((item) => {
                        const button = document.createElement('button');
                        const title = document.createElement('strong');
                        const meta = document.createElement('span');

                        button.type = 'button';
                        title.textContent = item.name;
                        meta.textContent = [item.address, item.area, item.region].filter(Boolean).join(', ');
                        button.append(title, meta);
                        button.addEventListener('click', () => onSelect(item));
                        results.append(button);
                    });

                    results.hidden = results.children.length === 0;
                };

                const resetWarehouse = () => {
                    const { input, ref } = lookupParts(warehouseLookup);

                    if (input) input.value = '';
                    if (ref) ref.value = '';
                    activeWarehouseRequest?.abort();
                    resetWarehouseState();
                    clearResults(warehouseLookup);
                    setStatus(warehouseLookup);
                };

                const loadWarehouses = async (query = '', page = 1, append = false) => {
                    const city = lookupParts(cityLookup);
                    const warehouse = lookupParts(warehouseLookup);
                    const type = selectedDelivery()?.dataset.deliveryType || 'branch';
                    const normalizedQuery = String(query || '').trim();

                    if (!city.ref?.value || type === 'courier') {
                        clearResults(warehouseLookup);
                        return;
                    }

                    if (warehouseState.isLoading) {
                        return;
                    }

                    if (append && (!warehouseState.hasMore || warehouseState.query !== normalizedQuery || warehouseState.type !== type)) {
                        return;
                    }

                    warehouseState.isLoading = true;
                    warehouseState.query = normalizedQuery;
                    warehouseState.type = type;

                    if (!append) {
                        warehouseState.hasMore = false;
                    }

                    activeWarehouseRequest?.abort();
                    activeWarehouseRequest = new AbortController();
                    setStatus(warehouseLookup, append ? 'Завантажуємо ще...' : (type === 'postomat' ? 'Завантажуємо поштомати...' : 'Завантажуємо відділення...'));

                    try {
                        const url = new URL(endpoints.warehouses, window.location.origin);
                        url.searchParams.set('city_ref', city.ref.value);
                        url.searchParams.set('q', normalizedQuery);
                        url.searchParams.set('type', type);
                        url.searchParams.set('page', String(page));
                        url.searchParams.set('limit', String(warehouseLimit));

                        const response = await fetch(url, { signal: activeWarehouseRequest.signal, headers: { Accept: 'application/json' } });
                        const data = await response.json();

                        if (!response.ok || data.error) {
                            throw new Error(data.error || 'Не вдалося знайти відділення.');
                        }

                        renderResults(warehouseLookup, data.items || [], (item) => {
                            if (warehouse.input) warehouse.input.value = item.name;
                            if (warehouse.ref) warehouse.ref.value = item.ref || '';
                            resetWarehouseState();
                            clearResults(warehouseLookup);
                            setStatus(warehouseLookup, type === 'postomat' ? 'Поштомат вибрано' : 'Відділення вибрано');
                        }, append);

                        warehouseState.page = page;
                        warehouseState.hasMore = (data.items || []).length >= warehouseLimit;
                        setStatus(warehouseLookup, (data.items || []).length || append ? '' : 'Для цього міста нічого не знайдено.');
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            if (!append) {
                                clearResults(warehouseLookup);
                            }
                            setStatus(warehouseLookup, error.message || 'Не вдалося знайти відділення.', true);
                        }
                    } finally {
                        warehouseState.isLoading = false;
                    }
                };

                const searchCities = () => {
                    const { input, ref } = lookupParts(cityLookup);
                    const query = input?.value.trim() ?? '';

                    if (ref) ref.value = '';
                    resetWarehouse();
                    window.clearTimeout(cityTimer);

                    if (query.length < 2) {
                        clearResults(cityLookup);
                        setStatus(cityLookup, query.length === 0 ? '' : 'Введіть мінімум 2 символи.');
                        return;
                    }

                    cityTimer = window.setTimeout(async () => {
                        activeCityRequest?.abort();
                        activeCityRequest = new AbortController();
                        setStatus(cityLookup, 'Шукаємо місто...');

                        try {
                            const url = new URL(endpoints.cities, window.location.origin);
                            url.searchParams.set('q', query);
                            url.searchParams.set('limit', '8');

                            const response = await fetch(url, { signal: activeCityRequest.signal, headers: { Accept: 'application/json' } });
                            const data = await response.json();

                            if (!response.ok || data.error) {
                                throw new Error(data.error || 'Не вдалося знайти місто.');
                            }

                            renderResults(cityLookup, data.items || [], (city) => {
                                input.value = [city.name, city.area].filter(Boolean).join(', ');
                                if (ref) ref.value = city.ref || '';
                                clearResults(cityLookup);
                                setStatus(cityLookup, 'Місто вибрано');
                                loadWarehouses('', 1, false);
                            });
                            setStatus(cityLookup, (data.items || []).length ? '' : 'Нічого не знайдено.');
                        } catch (error) {
                            if (error.name !== 'AbortError') {
                                clearResults(cityLookup);
                                setStatus(cityLookup, error.message || 'Не вдалося знайти місто.', true);
                            }
                        }
                    }, 320);
                };

                const searchWarehouses = () => {
                    const city = lookupParts(cityLookup);
                    const warehouse = lookupParts(warehouseLookup);
                    const query = warehouse.input?.value.trim() ?? '';

                    if (warehouse.ref) warehouse.ref.value = '';
                    window.clearTimeout(warehouseTimer);

                    if (!city.ref?.value) {
                        clearResults(warehouseLookup);
                        setStatus(warehouseLookup, 'Спочатку оберіть місто зі списку.', true);
                        return;
                    }

                    if (query.length < 1) {
                        warehouseTimer = window.setTimeout(() => loadWarehouses('', 1, false), 180);
                        return;
                    }

                    warehouseTimer = window.setTimeout(() => loadWarehouses(query, 1, false), 320);
                };

                const syncDelivery = () => {
                    const option = selectedDelivery();
                    const type = option?.dataset.deliveryType || 'branch';
                    const priceCents = Number(option?.dataset.priceCents || 0);
                    const showAddress = type === 'courier';
                    const warehouseInput = lookupParts(warehouseLookup).input;
                    const addressInput = addressField?.querySelector('input');

                    if (deliveryTotal) {
                        deliveryTotal.textContent = priceCents > 0 ? formatMoney(priceCents) : 'За тарифом';
                    }

                    if (orderTotal) {
                        orderTotal.textContent = formatMoney(baseCents + priceCents);
                    }

                    if (warehouseLookup) {
                        warehouseLookup.hidden = showAddress;
                    }

                    if (addressField) {
                        addressField.hidden = !showAddress;
                    }

                    if (warehouseLabel) {
                        warehouseLabel.textContent = type === 'postomat' ? 'Поштомат' : 'Відділення';
                    }

                    if (warehouseInput) {
                        warehouseInput.required = !showAddress;
                        warehouseInput.placeholder = type === 'postomat' ? 'Номер або адреса поштомата' : 'Номер або адреса відділення';
                    }

                    if (addressInput) {
                        addressInput.required = showAddress;
                    }

                    if (showAddress) {
                        resetWarehouse();
                    } else {
                        addressInput && (addressInput.value = '');
                        const city = lookupParts(cityLookup);

                        if (city.ref?.value) {
                            resetWarehouse();
                            loadWarehouses('', 1, false);
                        }
                    }
                };

                cityLookup?.querySelector('input[type="text"]')?.addEventListener('input', searchCities);
                warehouseLookup?.querySelector('input[type="text"]')?.addEventListener('input', searchWarehouses);
                phoneInput?.addEventListener('input', () => {
                    phoneInput.value = formatPhone(phoneInput.value);
                });
                phoneInput?.addEventListener('focus', () => {
                    if (phoneInput.value.trim() === '') {
                        phoneInput.value = '+38 (0';
                    }
                });
                phoneInput?.addEventListener('blur', () => {
                    if (phoneInput.value.replace(/\D/g, '').length <= 3) {
                        phoneInput.value = '';
                    }
                });
                warehouseLookup?.querySelector('input[type="text"]')?.addEventListener('focus', () => {
                    const warehouse = lookupParts(warehouseLookup);

                    if (!warehouse.ref?.value) {
                        loadWarehouses(warehouse.input?.value.trim() ?? '', 1, false);
                    }
                });
                warehouseLookup?.querySelector('[data-lookup-results]')?.addEventListener('scroll', (event) => {
                    const results = event.currentTarget;

                    if (results.scrollTop + results.clientHeight < results.scrollHeight - 28) {
                        return;
                    }

                    loadWarehouses(warehouseState.query, warehouseState.page + 1, true);
                });
                commentToggle?.addEventListener('click', () => {
                    setCommentExpanded(commentBody?.hidden === true);
                });
                mobileCheckoutQuery.addEventListener?.('change', syncCommentVisibility);
                document.addEventListener('submit', (event) => {
                    const cartForm = event.target.closest('form[data-checkout-cart-action]');

                    if (!cartForm) {
                        return;
                    }

                    event.preventDefault();
                    submitCheckoutCartAction(cartForm, event.submitter || null);
                });
                document.addEventListener('change', (event) => {
                    const select = event.target.closest('[data-checkout-variant-select]');
                    const cartForm = select?.closest('form[data-checkout-variant-form]');

                    if (!cartForm) {
                        return;
                    }

                    submitCheckoutCartAction(cartForm);
                });
                form.querySelectorAll('[data-delivery-option] input[type="radio"]').forEach((input) => {
                    input.addEventListener('change', syncDelivery);
                });
                document.addEventListener('click', (event) => {
                    if (!cityLookup?.contains(event.target)) clearResults(cityLookup);
                    if (!warehouseLookup?.contains(event.target)) clearResults(warehouseLookup);
                });

                syncDelivery();
                syncCommentVisibility();
            })();
        </script>
    </body>
</html>
