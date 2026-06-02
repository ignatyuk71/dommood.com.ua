<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.site-noindex')
        <title>{{ $seo['title'] ?? ($product['name'].' - '.$storeName) }}</title>
        <meta name="description" content="{{ $seo['meta_description'] ?? ($product['short_description'] ?: trim(strip_tags((string) ($product['description'] ?? ''))) ?: $product['name']) }}">
        <link rel="canonical" href="{{ $seo['canonical_url'] ?? url('/catalog/'.$category->slug.'/'.$product['slug']) }}">
        @include('partials.site-hreflang')
        <meta property="og:type" content="product">
        <meta property="og:site_name" content="{{ $storeName }}">
        <meta property="og:locale" content="uk_UA">
        <meta property="og:title" content="{{ $seo['title'] ?? $product['name'] }}">
        <meta property="og:description" content="{{ $seo['meta_description'] ?? ($product['short_description'] ?: trim(strip_tags((string) ($product['description'] ?? ''))) ?: $product['name']) }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] ?? url('/catalog/'.$category->slug.'/'.$product['slug']) }}">
        @if ($product['image_url'])
            <meta property="og:image" content="{{ $product['image_url'] }}">
            <meta property="og:image:width" content="600">
            <meta property="og:image:height" content="600">
            <meta property="og:image:alt" content="{{ $product['name'] }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#7b1a25">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/storefront.css', 'resources/css/storefront-product.css'])
        @else
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront.css')])
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront-product.css')])
        @endif
        @include('storefront.partials.preconnect')
        @include('storefront.partials.google-analytics')
    </head>
    <body>
        @php
            $formatMoney = static function (?int $amount, string $currency = 'UAH'): string {
                $value = number_format(((int) $amount) / 100, ((int) $amount) % 100 === 0 ? 0 : 2, ',', ' ');

                return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
            };

            $productUrl = static function (array $product): string {
                return $product['url'] ?? url('/catalog/'.($product['category']['slug'] ?? 'catalog').'/'.$product['slug']);
            };

            $variants = collect($product['variants'] ?? []);
            $initialVariant = $variants->first(fn (array $variant): bool => (bool) ($variant['is_available'] ?? false)) ?: $variants->first();
            $initialVariantAvailable = $initialVariant ? (bool) ($initialVariant['is_available'] ?? false) : (($product['stock_status'] ?? 'in_stock') !== \App\Models\Product::STOCK_OUT_OF_STOCK);
            $initialColorKey = $initialVariant ? mb_strtolower(($initialVariant['color_name'] ?? '').'|'.($initialVariant['color_hex'] ?? '')) : '';
            $initialSizeKey = $initialVariant ? mb_strtolower((string) ($initialVariant['size'] ?? '')) : '';
            $initialStockLabel = $initialVariant['stock_status_label'] ?? ($initialVariantAvailable ? $product['stock_status_label'] : 'Немає в наявності');
            $currentPrice = (int) ($initialVariant['price_cents'] ?? $product['price_cents']);
            $currentOldPrice = (int) ($initialVariant['old_price_cents'] ?? $product['old_price_cents']);
            $hasDiscount = $currentOldPrice > $currentPrice && $currentPrice > 0;
            $discountPercent = $hasDiscount ? (int) round((1 - ($currentPrice / $currentOldPrice)) * 100) : 0;
            $isPurchasable = (int) ($product['id'] ?? 0) > 0 && ($product['stock_status'] ?? 'in_stock') !== \App\Models\Product::STOCK_OUT_OF_STOCK;
            $cleanSupportPhone = preg_replace('/[^\d+]+/', '', (string) ($supportPhone ?? '')) ?: '';
            $messengerPhone = ltrim($cleanSupportPhone, '+');
            $outOfStockMessage = 'Вітаю! Повідомте, будь ласка, коли товар "'.$product['name'].'" буде в наявності.';
            $notifyAvailabilityHref = $messengerPhone !== ''
                ? 'https://wa.me/'.$messengerPhone.'?text='.rawurlencode($outOfStockMessage)
                : 'https://www.instagram.com/dommood.store/';
            $managerContactHref = $messengerPhone !== ''
                ? 'tg://resolve?phone='.$messengerPhone
                : 'https://www.instagram.com/dommood.store/';
            $galleryImages = collect($product['images'] ?? [])->filter(fn (array $image): bool => filled($image['url'] ?? null))->values();

            if ($galleryImages->isEmpty() && filled($product['image_url'] ?? null)) {
                $galleryImages = collect([[
                    'id' => 'main',
                    'url' => $product['image_url'],
                    'thumb_url' => $product['image_url'],
                    'alt' => $product['image_alt'] ?? $product['name'],
                    'is_main' => true,
                ]]);
            }

            $colors = $variants
                ->filter(fn (array $variant): bool => filled($variant['color_name'] ?? null) || filled($variant['color_hex'] ?? null))
                ->sortByDesc(fn (array $variant): bool => (bool) ($variant['is_available'] ?? false))
                ->unique(fn (array $variant): string => mb_strtolower(($variant['color_name'] ?? '').'|'.($variant['color_hex'] ?? '')))
                ->values();
            $colorProducts = collect($product['color_options'] ?? []);
            $sizes = $variants
                ->filter(fn (array $variant): bool => filled($variant['size'] ?? null))
                ->sortByDesc(fn (array $variant): bool => (bool) ($variant['is_available'] ?? false))
                ->unique(fn (array $variant): string => mb_strtolower($variant['size']))
                ->values();
            $rating = (float) ($product['rating_average'] ?? 0);
            $reviewCount = (int) ($product['reviews_count'] ?? 0);
            $productAttributes = collect($product['attributes'] ?? [])
                ->filter(fn (array $attribute): bool => filled($attribute['name'] ?? null) && filled($attribute['value'] ?? null))
                ->values();
            $productReviews = collect($product['reviews'] ?? [])
                ->filter(fn (array $review): bool => filled($review['title'] ?? null) || filled($review['body'] ?? null))
                ->values();
            $reviewRatingAverage = $productReviews->isNotEmpty()
                ? round((float) ($rating ?: $productReviews->avg(fn (array $review): int => (int) ($review['rating'] ?? 0))), 1)
                : 0.0;
            $reviewHasErrors = $errors->getBag('review')->any();
            $reviewSubmitted = session()->has('review_status');
            $activeProductTab = ($reviewHasErrors || $reviewSubmitted) ? 'reviews' : 'description';
            $reviewFormAction = route('catalog.product.reviews.store', [
                'categorySlug' => $category->slug,
                'productSlug' => $product['slug'],
            ]);
            $productDescriptionHtml = trim((string) ($product['description'] ?? ''));
            $productSeoTextHtml = trim((string) ($product['seo_text'] ?? ''));

            if ($productDescriptionHtml === '' && $productSeoTextHtml !== '') {
                $productDescriptionHtml = $productSeoTextHtml;
                $productSeoTextHtml = '';
            }

            $productDescriptionText = trim(strip_tags($productDescriptionHtml));

            if ($productDescriptionText === '') {
                $productDescriptionText = trim((string) ($product['short_description'] ?? ''));
            }

            if ($productDescriptionText === '') {
                $productDescriptionText = 'Продумана модель для щоденного комфорту: мʼяка посадка, практичні матеріали та швидке оформлення замовлення з доставкою по Україні.';
            }

            $productSummaryIntro = trim((string) ($product['short_description'] ?? ''));

            if ($productSummaryIntro === '') {
                $productSummaryIntro = \Illuminate\Support\Str::limit($productDescriptionText, 220);
            }

            $productSummaryIntro = \Illuminate\Support\Str::limit($productSummaryIntro, 120, '...');

            $schemas = collect($schemas ?? [])->filter()->values();
            $faqItems = collect($faqItems ?? [])->filter(fn (array $item): bool => filled($item['question'] ?? null) && filled($item['answer'] ?? null))->values();

            if ($faqItems->isNotEmpty()) {
                $schemas->push([
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqItems
                        ->map(fn (array $item): array => [
                            '@type' => 'Question',
                            'name' => $item['question'],
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $item['answer'],
                            ],
                        ])
                        ->all(),
                ]);
            }

            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => 'Каталог', 'url' => url('/catalog')],
                ['label' => $category->name, 'url' => url('/catalog/'.$category->slug)],
                ['label' => $product['name']],
            ];
            $shortMonths = [1 => 'січ', 2 => 'лют', 3 => 'бер', 4 => 'кві', 5 => 'тра', 6 => 'чер', 7 => 'лип', 8 => 'сер', 9 => 'вер', 10 => 'жов', 11 => 'лис', 12 => 'гру'];
            $formatShortDate = static fn ($date): string => $date->format('j').' '.($shortMonths[(int) $date->format('n')] ?? $date->format('M'));
            $deliveryStart = \App\Support\DateTime\KyivDateTime::now()->addDays(2);
            $deliveryEnd = \App\Support\DateTime\KyivDateTime::now()->addDays(3);
            $freeDeliveryThreshold = max(1, (int) ($freeShippingThresholdCents ?? \App\Services\Storefront\DeliveryPolicyService::DEFAULT_FREE_SHIPPING_THRESHOLD_CENTS));
            $cartTotalCents = max(0, (int) ($headerCartSummary['total_cents'] ?? 0));
            $freeDeliveryLeft = max(0, $freeDeliveryThreshold - $cartTotalCents);
            $freeDeliveryProgress = min(100, (int) floor(($cartTotalCents / $freeDeliveryThreshold) * 100));
            $freeDeliveryLabel = $freeDeliveryLeft > 0
                ? 'Додайте ще '.$formatMoney($freeDeliveryLeft, 'UAH').', щоб отримати безкоштовну доставку'
                : 'Безкоштовна доставка доступна для цього замовлення';

            $productPayload = [
                'id' => $product['id'],
                'name' => $product['name'],
                'url' => $productUrl($product),
                'image_url' => $product['image_url'],
                'image_alt' => $product['image_alt'] ?? $product['name'],
                'currency' => $product['currency'],
                'base_price_cents' => $product['price_cents'],
                'base_old_price_cents' => $product['old_price_cents'],
                'sku' => $product['sku'],
                'view_item_event_id' => $viewItemEventId ?? null,
                'brand' => $product['brand']['name'] ?? null,
                'category' => $product['category']['name'] ?? $category->name,
                'stock_status' => $product['stock_status'],
                'stock_status_label' => $product['stock_status_label'],
                'is_new' => (bool) ($product['is_new'] ?? false),
                'is_bestseller' => (bool) ($product['is_bestseller'] ?? false),
                'is_featured' => (bool) ($product['is_featured'] ?? false),
                'variants' => $variants->values()->all(),
            ];
            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => 'Каталог', 'url' => url('/catalog')],
                ['label' => $category->name, 'url' => url('/catalog/'.$category->slug)],
                ['label' => $product['name']],
            ];
        @endphp

        <div class="storefront-page storefront-product-page" data-product-page>
            @include('storefront.partials.site-header')

            <main>
                <section class="product-pdp">
                    <div class="container">
                        @include('storefront.partials.breadcrumbs', ['items' => $breadcrumbs])

                        <div class="product-pdp__layout">
                            <section class="product-gallery" aria-label="Фото товару">
                                <div class="product-gallery__track" data-product-gallery-track>
                                    @forelse ($galleryImages as $image)
                                        <figure class="product-gallery__item @if ($loop->first) is-active @endif" data-product-gallery-item data-media-index="{{ $loop->index }}" data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] ?? $product['name'] }}">
                                            @if ($loop->first && $hasDiscount)
                                                <span class="product-sale-badge">-{{ $discountPercent }}%</span>
                                            @endif
                                            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? $product['name'] }}" width="920" height="1120" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                                        </figure>
                                    @empty
                                        <figure class="product-gallery__item product-gallery__item--placeholder is-active" data-product-gallery-item data-media-index="0">
                                            <span>{{ mb_substr($product['name'] ?: 'DM', 0, 2) }}</span>
                                        </figure>
                                    @endforelse
                                </div>

                                @if ($galleryImages->count() > 1)
                                    <div class="product-gallery__dots" aria-label="Перемикання фото">
                                        @foreach ($galleryImages as $image)
                                            <button type="button" class="@if ($loop->first) is-active @endif" data-product-dot data-media-index="{{ $loop->index }}" aria-label="Фото {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>
                                        @endforeach
                                    </div>
                                @endif
                            </section>

                            <aside class="product-summary" aria-labelledby="product-title">
                                <div class="product-summary__head">
                                    <div class="product-summary__eyebrow">{{ $category->name }}</div>
                                    <h1 id="product-title">{{ $product['name'] }}</h1>
                                    @if ($productSummaryIntro !== '')
                                        <p class="product-summary__intro">{{ $productSummaryIntro }}</p>
                                    @endif
                                    <div class="product-meta-row">
                                        <span class="product-rating" aria-label="{{ $rating > 0 ? 'Рейтинг '.$rating.' з 5' : 'Відгуків ще немає' }}">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <svg viewBox="0 0 20 20" aria-hidden="true" @class(['is-filled' => $rating >= $star])><path d="m10 1.9 2.5 5.1 5.6.8-4 4 1 5.6-5-2.7-5 2.7 1-5.6-4-4 5.5-.8L10 1.9Z"/></svg>
                                            @endfor
                                            <span>{{ $reviewCount > 0 ? $reviewCount.' відгуків' : '0 відгуків' }}</span>
                                        </span>
                                        @if ($product['sku'] || ($initialVariant['sku'] ?? null))
                                            <span class="product-sku">Код: <b data-product-sku>{{ $initialVariant['sku'] ?? $product['sku'] }}</b></span>
                                        @endif
                                    </div>
                                </div>

                                <div class="product-price-line">
                                    <div class="product-price">
                                        <strong data-product-price>{{ $formatMoney($currentPrice, $product['currency']) }}</strong>
                                        <del data-product-old-price @if (! $hasDiscount) hidden @endif>{{ $formatMoney($currentOldPrice, $product['currency']) }}</del>
                                    </div>
                                    <span @class(['product-stock', 'is-warning' => $initialVariantAvailable && $product['stock_status'] === 'preorder', 'is-muted' => ! $initialVariantAvailable || $product['stock_status'] === 'out_of_stock']) data-product-stock>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m7.8 12.2 2.8 2.8 5.8-6.1"/></svg>
                                        <span data-product-stock-label>{{ $initialStockLabel }}</span>
                                    </span>
                                </div>

                                @if ($colorProducts->isNotEmpty())
                                    <section class="product-color-gallery" aria-label="Кольори товару">
                                        <h2>Колір</h2>
                                        <div class="product-color-gallery__grid">
                                            @foreach ($colorProducts as $colorProduct)
                                                <a
                                                    href="{{ $colorProduct['url'] }}"
                                                    @class(['product-color-card', 'is-active' => $colorProduct['is_active']])
                                                    aria-label="Колір: {{ $colorProduct['color_name'] ?? $colorProduct['name'] }}"
                                                    aria-current="{{ $colorProduct['is_active'] ? 'true' : 'false' }}"
                                                >
                                                    @if ($colorProduct['image_url'])
                                                        <img src="{{ $colorProduct['image_url'] }}" alt="{{ $colorProduct['image_alt'] ?? $colorProduct['name'] }}" loading="lazy" decoding="async" width="96" height="96">
                                                    @else
                                                        <span>{{ mb_substr($colorProduct['color_name'] ?? $colorProduct['name'], 0, 2) }}</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif

                                @if ($isPurchasable)
                                    <form method="post" action="{{ route('cart.items.store') }}" class="product-buybox" data-cart-form data-cart-add data-product-form>
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                        <input type="hidden" name="quantity" value="1" data-product-quantity-field>
                                        @if ($variants->isNotEmpty())
                                            <input type="hidden" name="product_variant_id" value="{{ $initialVariant['id'] }}" data-product-variant-field>
                                        @endif

                                        @if ($colorProducts->isEmpty() && $colors->isNotEmpty())
                                            <fieldset class="product-option-group">
                                                <legend>Колір: <span data-product-color-label>{{ $colors->first()['color_name'] ?: 'оберіть відтінок' }}</span></legend>
                                                <div class="product-color-options">
                                                    @foreach ($colors as $variant)
                                                        @php
                                                            $colorLabel = $variant['color_name'] ?: 'Колір '.$loop->iteration;
                                                            $colorKey = mb_strtolower(($variant['color_name'] ?? '').'|'.($variant['color_hex'] ?? ''));
                                                            $isVariantAvailable = (bool) ($variant['is_available'] ?? false);
                                                            $isActiveColor = $colorKey !== '' && $colorKey === $initialColorKey;
                                                        @endphp
                                                        <label @class(['is-active' => $isActiveColor, 'is-disabled' => ! $isVariantAvailable]) data-product-color-option>
                                                            <input type="radio" name="product_color" value="{{ $colorKey }}" @checked($isActiveColor) @disabled(! $isVariantAvailable)>
                                                            <span class="product-color-options__swatch @if ($variant['image_url']) has-image @endif" style="--product-color: {{ $variant['color_hex'] ?: '#f4ece6' }}; @if ($variant['image_url']) --product-swatch-image: url('{{ $variant['image_url'] }}'); @endif"></span>
                                                            <span class="product-color-options__label">{{ $colorLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @endif

                                        @if ($sizes->isNotEmpty())
                                            <fieldset class="product-option-group">
                                                <legend>
                                                    <span>Розмір</span>
                                                </legend>
                                                <div class="product-size-options">
                                                    @foreach ($sizes as $variant)
                                                        @php
                                                            $sizeKey = mb_strtolower((string) $variant['size']);
                                                            $sizeLabel = preg_replace('/\s*-\s*/u', '-', (string) $variant['size']);
                                                            $isVariantAvailable = (bool) ($variant['is_available'] ?? false);
                                                            $isActiveSize = $sizeKey !== '' && $sizeKey === $initialSizeKey;
                                                        @endphp
                                                        <label @class(['is-active' => $isActiveSize, 'is-disabled' => ! $isVariantAvailable]) data-product-size-option>
                                                            <input type="radio" name="product_size" value="{{ $sizeKey }}" @checked($isActiveSize) @disabled(! $isVariantAvailable)>
                                                            <span>{{ $sizeLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @elseif ($variants->isNotEmpty())
                                            <fieldset class="product-option-group">
                                                <legend><span>Варіант</span></legend>
                                                <div class="product-size-options">
                                                    @foreach ($variants as $variant)
                                                        @php
                                                            $isVariantAvailable = (bool) ($variant['is_available'] ?? false);
                                                            $isActiveVariant = (int) ($variant['id'] ?? 0) === (int) ($initialVariant['id'] ?? 0);
                                                        @endphp
                                                        <label @class(['is-active' => $isActiveVariant, 'is-disabled' => ! $isVariantAvailable]) data-product-variant-option>
                                                            <input type="radio" name="product_variant_choice" value="{{ $variant['id'] }}" @checked($isActiveVariant) @disabled(! $isVariantAvailable)>
                                                            <span>{{ $variant['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @endif

                                        <div class="product-buy-actions">
                                            <div class="product-quantity" aria-label="Кількість">
                                                <button type="button" data-product-qty-minus aria-label="Зменшити кількість">−</button>
                                                <input type="number" min="1" max="99" value="1" inputmode="numeric" data-product-qty aria-label="Кількість товару">
                                                <button type="button" data-product-qty-plus aria-label="Збільшити кількість">+</button>
                                            </div>
                                            <button type="submit" class="product-add-button" data-product-add-button @disabled(! $initialVariantAvailable)>
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                                <span data-product-add-label>{{ $initialVariantAvailable ? 'У кошик' : 'Немає в наявності' }}</span>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="product-help-cards" aria-label="Допомога з розміром">
                                        @if ($product['size_chart']['content_html'] ?? null)
                                            <button type="button" data-product-dialog-open="size-chart">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 15 11-11 5 5L9 20H4z"/><path d="m13 6 2 2"/><path d="m10 9 2 2"/><path d="m7 12 2 2"/></svg>
                                                <span>Таблиця розмірів</span>
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                            </button>
                                        @endif
                                        <button type="button" data-product-dialog-open="measure-guide">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.4 9a2.8 2.8 0 1 1 4 2.5c-.9.5-1.4 1.1-1.4 2.2"/><path d="M12 17h.01"/></svg>
                                            <span>Як знімати мірки</span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="product-unavailable">
                                        <strong>Товар тимчасово недоступний</strong>
                                        <p>Залиште запит, і ми повідомимо, коли товар зʼявиться. Або менеджер підбере найближчу альтернативу.</p>
                                        <div class="product-unavailable__actions">
                                            <a
                                                href="{{ $notifyAvailabilityHref }}"
                                                class="product-unavailable__button"
                                                data-product-unavailable-action="notify_availability"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                                <span>Повідомити про наявність</span>
                                            </a>
                                            <a
                                                href="{{ $managerContactHref }}"
                                                class="product-unavailable__button product-unavailable__button--secondary"
                                                data-product-unavailable-action="contact_manager"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
                                                <span>Написати менеджеру</span>
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                <div class="product-service-info" aria-label="Доставка, оплата та повернення">
                                    <div class="product-service-info__note">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        <span>Очікуйте на замовлення в період <span>{{ $formatShortDate($deliveryStart) }} - {{ $formatShortDate($deliveryEnd) }}</span></span>
                                    </div>
                                    <div class="product-service-info__note">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h11v10H4z"/><path d="M15 10h3.5l1.5 2.2V17h-5z"/><circle cx="7" cy="18" r="1.5"/><circle cx="17.5" cy="18" r="1.5"/></svg>
                                        <span>Безкоштовна доставка та повернення: <span>Для всіх замовлень від {{ $formatMoney($freeDeliveryThreshold, 'UAH') }}</span></span>
                                    </div>
                                    <div
                                        class="product-service-info__free"
                                        data-free-shipping-progress
                                        data-free-shipping-threshold="{{ $freeDeliveryThreshold }}"
                                        data-free-shipping-current="{{ $cartTotalCents }}"
                                        style="--free-progress-percent: {{ $freeDeliveryProgress }}%;"
                                    >
                                        <span data-free-shipping-label>{{ $freeDeliveryLabel }}</span>
                                        <div aria-hidden="true"></div>
                                    </div>

                                    <div class="product-service-accordion">
                                        <details>
                                            <summary>Доставка</summary>
                                            <div class="product-service-accordion__body">
                                                <div class="product-delivery-table" role="table" aria-label="Способи доставки">
                                                    <div role="row">
                                                        <span role="columnheader">Спосіб доставки</span>
                                                        <span role="columnheader">Термін</span>
                                                        <span role="columnheader">Вартість</span>
                                                    </div>
                                                    <div role="row">
                                                        <span role="cell"><span>У поштомат</span><small>Зручно для компактних замовлень із самовивозом у вашому районі.</small></span>
                                                        <span role="cell">1-3 робочі дні</span>
                                                        <span role="cell">80 грн</span>
                                                    </div>
                                                    <div role="row">
                                                        <span role="cell"><span>У відділення</span><small>Отримання у відділенні Нової пошти після прибуття посилки.</small></span>
                                                        <span role="cell">1-3 робочі дні</span>
                                                        <span role="cell">90 грн</span>
                                                    </div>
                                                    <div role="row">
                                                        <span role="cell"><span>Кур’єром</span><small>Доставка кур’єром Нової пошти на вказану адресу.</small></span>
                                                        <span role="cell">1-4 робочі дні</span>
                                                        <span role="cell">120 грн</span>
                                                    </div>
                                                </div>
                                                <ul>
                                                    <li>Доставка в села/селища: +25 грн до тарифу.</li>
                                                    <li>Після оформлення замовлення ви отримаєте SMS/Viber з номером ТТН.</li>
                                                </ul>
                                            </div>
                                        </details>
                                        <details>
                                            <summary>Оплата</summary>
                                            <div class="product-service-accordion__body">
                                                <ul>
                                                    <li><span>Банківська картка</span> - оплата онлайн через сервіс LiqPay, Monobank або Приват24.</li>
                                                    <li><span>Готівка</span> - при отриманні на відділенні Нової Пошти.</li>
                                                    <li><span>Безготівковий розрахунок</span> - для юр. осіб та ФОП, видаємо рахунок-фактуру.</li>
                                                </ul>
                                            </div>
                                        </details>
                                        <details>
                                            <summary>Обмін та повернення</summary>
                                            <div class="product-service-accordion__body">
                                                <ul>
                                                    <li>Обмін та повернення: протягом 14 днів згідно з законодавством України.</li>
                                                    <li>Кошти повертаємо на картку протягом 1-3 робочих днів.</li>
                                                </ul>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <section class="product-details-tabs" aria-label="Деталі товару" data-product-tabs>
                            <div class="product-details-tabs__nav" role="tablist" aria-label="Інформація про товар">
                                <button type="button" id="product-tab-description" role="tab" aria-selected="{{ $activeProductTab === 'description' ? 'true' : 'false' }}" aria-controls="product-panel-description" data-product-tab="description">Опис</button>
                                <button type="button" id="product-tab-delivery" role="tab" aria-selected="{{ $activeProductTab === 'delivery' ? 'true' : 'false' }}" aria-controls="product-panel-delivery" data-product-tab="delivery">Доставка і повернення</button>
                                <button type="button" id="product-tab-reviews" role="tab" aria-selected="{{ $activeProductTab === 'reviews' ? 'true' : 'false' }}" aria-controls="product-panel-reviews" data-product-tab="reviews">Відгуки({{ $reviewCount }})</button>
                                <button type="button" id="product-tab-characteristics" role="tab" aria-selected="{{ $activeProductTab === 'characteristics' ? 'true' : 'false' }}" aria-controls="product-panel-characteristics" data-product-tab="characteristics">Характеристики</button>
                            </div>

                            <div class="product-details-tabs__panels">
                                <section id="product-panel-description" @class(['product-details-tabs__panel', 'is-active' => $activeProductTab === 'description']) role="tabpanel" aria-labelledby="product-tab-description" data-product-tab-panel="description" @if ($activeProductTab !== 'description') hidden @endif>
                                    <div class="product-details-tabs__grid">
                                        <div class="product-details-tabs__copy">
                                            @if ($productDescriptionHtml !== '')
                                                <div>{!! $productDescriptionHtml !!}</div>
                                            @else
                                                <p>{!! nl2br(e($productDescriptionText)) !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                </section>

                                <section id="product-panel-delivery" @class(['product-details-tabs__panel', 'is-active' => $activeProductTab === 'delivery']) role="tabpanel" aria-labelledby="product-tab-delivery" data-product-tab-panel="delivery" @if ($activeProductTab !== 'delivery') hidden @endif>
                                    <div class="product-delivery-details">
                                        <div class="product-delivery-details__head">
                                            <h2>Доставка і повернення</h2>
                                            <p>Відправляємо замовлення Новою поштою по Україні. Орієнтовне отримання: {{ $formatShortDate($deliveryStart) }} - {{ $formatShortDate($deliveryEnd) }}. Якщо розмір не підійшов, допоможемо швидко оформити обмін або повернення.</p>
                                        </div>

                                        <div class="product-delivery-details__grid">
                                            <article>
                                                <h3>Доставка</h3>
                                                <ul>
                                                    <li>У відділення або поштомат Нової пошти: 1-3 робочі дні.</li>
                                                    <li>Курʼєром на адресу: 1-4 робочі дні.</li>
                                                    <li>Після відправки надсилаємо номер ТТН у SMS або месенджер.</li>
                                                </ul>
                                            </article>

                                            <article>
                                                <h3>Безкоштовна доставка</h3>
                                                <ul>
                                                    <li>Діє для замовлень від {{ $formatMoney($freeDeliveryThreshold, 'UAH') }}.</li>
                                                    <li>Якщо сума менша, доставка оплачується за тарифами перевізника.</li>
                                                    <li>Менеджер підтвердить фінальну суму перед відправкою.</li>
                                                </ul>
                                            </article>

                                            <article>
                                                <h3>Обмін</h3>
                                                <ul>
                                                    <li>Обмінюємо розмір або модель протягом 14 днів після отримання.</li>
                                                    <li>Товар має бути без слідів використання, збереженим пакуванням і товарним виглядом.</li>
                                                    <li>Перед повторною відправкою менеджер допоможе перевірити заміри.</li>
                                                </ul>
                                            </article>

                                            <article>
                                                <h3>Повернення</h3>
                                                <ul>
                                                    <li>Повернення можливе протягом 14 днів згідно із законодавством України.</li>
                                                    <li>Кошти повертаємо на картку протягом 1-3 робочих днів після перевірки товару.</li>
                                                    <li>Для старту повернення напишіть нам у зручний месенджер і вкажіть номер замовлення.</li>
                                                </ul>
                                            </article>
                                        </div>

                                        <div class="product-delivery-details__steps" aria-label="Як оформити обмін або повернення">
                                            <h3>Як оформити обмін або повернення</h3>
                                            <ol>
                                                <li><span>1</span>Напишіть менеджеру номер замовлення та причину звернення.</li>
                                                <li><span>2</span>Отримайте інструкцію для відправки товару Новою поштою.</li>
                                                <li><span>3</span>Після перевірки товару ми відправимо заміну або повернемо кошти.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </section>

                                <section id="product-panel-reviews" @class(['product-details-tabs__panel', 'is-active' => $activeProductTab === 'reviews']) role="tabpanel" aria-labelledby="product-tab-reviews" data-product-tab-panel="reviews" @if ($activeProductTab !== 'reviews') hidden @endif>
                                    <div class="product-reviews">
                                        <div class="product-reviews__head">
                                            <h2>Відгуки</h2>
                                            <button type="button" class="product-review-action" data-product-dialog-open="review-form">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                                <span>Залишити відгук</span>
                                            </button>
                                        </div>

                                        @if (session('review_status'))
                                            <div class="product-review-status" role="status">{{ session('review_status') }}</div>
                                        @endif

                                        <div class="product-reviews__summary">
                                            <div class="product-reviews__score-card">
                                                <strong>{{ number_format($reviewRatingAverage, 1, '.', '') }}</strong>
                                                <span class="product-review-stars" aria-label="Рейтинг {{ number_format($reviewRatingAverage, 1, '.', '') }} з 5">
                                                    @for ($star = 1; $star <= 5; $star++)
                                                        <svg viewBox="0 0 20 20" aria-hidden="true" @class(['is-filled' => $reviewRatingAverage >= ($star - 0.25)])><path d="m10 1.9 2.5 5.1 5.6.8-4 4 1 5.6-5-2.7-5 2.7 1-5.6-4-4 5.5-.8L10 1.9Z"/></svg>
                                                    @endfor
                                                </span>
                                                <span>{{ $reviewCount }} Відгуки</span>
                                            </div>

                                            <div class="product-rating-breakdown" aria-label="Розподіл оцінок">
                                                @foreach ([5, 4, 3, 2, 1] as $score)
                                                    @php
                                                        $scoreCount = $productReviews->filter(fn (array $review): bool => (int) ($review['rating'] ?? 0) === $score)->count();
                                                        $scorePercent = $productReviews->isNotEmpty() ? (int) round(($scoreCount / $productReviews->count()) * 100) : 0;
                                                    @endphp
                                                    <div class="product-rating-breakdown__row" style="--review-rating-percent: {{ $scorePercent }}%;">
                                                        <span class="product-rating-breakdown__label">{{ $score }} <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.9 2.5 5.1 5.6.8-4 4 1 5.6-5-2.7-5 2.7 1-5.6-4-4 5.5-.8L10 1.9Z"/></svg></span>
                                                        <span class="product-rating-breakdown__bar" aria-hidden="true"><span></span></span>
                                                        <span class="product-rating-breakdown__count">{{ $scoreCount }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if ($productReviews->isNotEmpty())
                                            <div class="product-review-list">
                                                @foreach ($productReviews as $review)
                                                    @php
                                                        $reviewText = (string) (($review['body'] ?? null) ?: ($review['title'] ?? ''));
                                                    @endphp
                                                    <article class="product-review-row">
                                                        <header>
                                                            <strong>{{ $review['author_name'] ?? 'Покупець DomMood' }}</strong>
                                                            @if ($review['published_at'] ?? null)
                                                                <time>{{ $review['published_at'] }}</time>
                                                            @endif
                                                        </header>
                                                        <span class="product-review-stars" aria-label="Оцінка {{ (int) ($review['rating'] ?? 0) }} з 5">
                                                            @for ($star = 1; $star <= 5; $star++)
                                                                <svg viewBox="0 0 20 20" aria-hidden="true" @class(['is-filled' => (int) ($review['rating'] ?? 0) >= $star])><path d="m10 1.9 2.5 5.1 5.6.8-4 4 1 5.6-5-2.7-5 2.7 1-5.6-4-4 5.5-.8L10 1.9Z"/></svg>
                                                            @endfor
                                                        </span>
                                                        <p>{{ $reviewText }}</p>
                                                    </article>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="product-details-tabs__empty">
                                                <h2>Відгуків про цей товар ще немає</h2>
                                                <p>Перші відгуки зʼявляться після модерації покупок. Рейтинг магазину й товару оновлюється тільки для підтверджених відгуків.</p>
                                            </div>
                                        @endif
                                    </div>
                                </section>

                                <section id="product-panel-characteristics" @class(['product-details-tabs__panel', 'is-active' => $activeProductTab === 'characteristics']) role="tabpanel" aria-labelledby="product-tab-characteristics" data-product-tab-panel="characteristics" @if ($activeProductTab !== 'characteristics') hidden @endif>
                                    @if ($productAttributes->isNotEmpty())
                                        <div class="product-attributes">
                                            <h2>Характеристики</h2>
                                            <dl class="product-attributes-list" aria-label="Характеристики товару">
                                                @foreach ($productAttributes as $attribute)
                                                    <div class="product-attributes-list__row">
                                                        <dt>{{ rtrim($attribute['name'], ':') }}</dt>
                                                        <dd>{{ $attribute['value'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @else
                                        <div class="product-details-tabs__empty">
                                            <h2>Характеристики уточнюються</h2>
                                            <p>Менеджер перевірить матеріал, розмірну сітку або інші деталі перед відправкою замовлення.</p>
                                        </div>
                                    @endif
                                </section>
                            </div>
                        </section>
                    </div>
                </section>

                @if ($productSeoTextHtml !== '')
                    <section class="storefront-catalog-seo" aria-label="Додатковий SEO текст товару">
                        <div class="container">
                            <article class="storefront-seo-card">
                                {!! $productSeoTextHtml !!}
                            </article>
                        </div>
                    </section>
                @endif

                <section class="product-related product-recently-viewed" data-recently-viewed-section hidden>
                    <div class="container">
                        <div class="storefront-section-heading">
                            <div>
                                <h2>Нещодавно переглянуті</h2>
                                <p>Товари, які ви відкривали раніше на сайті.</p>
                            </div>
                            <div class="product-recently-viewed__controls" aria-label="Керування каруселлю">
                                <button type="button" data-recently-viewed-prev aria-label="Попередні товари">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                                </button>
                                <button type="button" data-recently-viewed-next aria-label="Наступні товари">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="product-recently-viewed__viewport">
                            <div class="product-recently-viewed__track" data-recently-viewed-track role="list"></div>
                        </div>
                    </div>
                </section>

                @if ($faqItems->isNotEmpty())
                    <section class="product-faq" aria-labelledby="product-faq-title">
                        <div class="container">
                            <div class="storefront-section-heading">
                                <div>
                                    <h2 id="product-faq-title">Питання про товар</h2>
                                    <p>Короткі відповіді про розмір, догляд, доставку та повернення.</p>
                                </div>
                            </div>
                            <div class="product-faq__grid">
                                @foreach ($faqItems as $faqItem)
                                    <details class="product-faq__item">
                                        <summary>
                                            <span>{{ $faqItem['question'] }}</span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        </summary>
                                        <p>{{ $faqItem['answer'] }}</p>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            </main>

            @include('storefront.partials.site-footer')
        </div>

        @if ($product['size_chart']['content_html'] ?? null)
            <dialog class="product-dialog" data-product-dialog="size-chart">
                <div class="product-dialog__panel">
                    <button type="button" class="product-dialog__close" data-product-dialog-close aria-label="Закрити">×</button>
                    <h2>Розмірна сітка</h2>
                    <div class="product-dialog__tabs" aria-label="Навігація по розмірах">
                        <button type="button" class="is-active">Розмірна сітка</button>
                        <button type="button" data-product-dialog-open="measure-guide">Як знімати мірки</button>
                    </div>
                    <div class="product-dialog__content">{!! $product['size_chart']['content_html'] !!}</div>
                </div>
            </dialog>
        @endif

        <dialog class="product-dialog product-dialog--measure" data-product-dialog="measure-guide">
            <div class="product-dialog__panel">
                <button type="button" class="product-dialog__close" data-product-dialog-close aria-label="Закрити">×</button>
                <h2>Як знімати мірки</h2>
                <div class="product-dialog__tabs" aria-label="Навігація по розмірах">
                    @if ($product['size_chart']['content_html'] ?? null)
                        <button type="button" data-product-dialog-open="size-chart">Розмірна сітка</button>
                    @endif
                    <button type="button" class="is-active">Як знімати мірки</button>
                </div>
                <div class="product-measure-guide">
                    <figure class="product-measure-guide__visual">
                        <img src="{{ asset('brand/product/foot-measure-guide.svg') }}" alt="Схема правильного та неправильного вимірювання стопи" width="320" height="400" loading="lazy">
                    </figure>
                    <div class="product-measure-guide__content">
                        <h3>Як правильно виміряти довжину стопи</h3>
                        <p>Встаньте на аркуш паперу і обведіть стопу олівцем або ручкою</p>
                        <p>Виміряйте відстань від однієї крайньої точки до іншої</p>
                        <div class="product-measure-note"><strong>Примітка:</strong> Вимірювання найкраще проводити наприкінці дня, коли розмір ноги максимальний (наприкінці дня до ніг приливає кров, і розмір стопи збільшується)</div>
                        <ol>
                            <li>Витягніть устілку із взуття, що є для вас найбільш зручним. Виміряйте її довжину</li>
                            <li>Якщо устілка не виймається, одягніть шкарпетку бажаної товщини (влітку шкарпетка має бути тоншою, взимку – товстішою), станьте на аркуш паперу та обведіть стопу олівцем або ручкою</li>
                            <li>Візьміть лінійку і виміряйте відстань від однієї крайньої точки до іншої (від п'яти до пальця, що виступає найбільше). Зробіть те саме з другою ногою. За основу візьміть найбільше значення</li>
                            <li>Округліть отриманий результат і знайдіть свій розмір у таблиці відповідного бренду</li>
                            <li>Під час вибору розміру необхідно звернути увагу на повноту ноги. Якщо повноту в моделі не вказано, отже взуття середньої (нормальної) повноти</li>
                        </ol>
                    </div>
                </div>
            </div>
        </dialog>

        <dialog class="product-dialog product-dialog--review" data-product-dialog="review-form" @if ($reviewHasErrors) data-product-dialog-auto-open @endif>
            <div class="product-dialog__panel">
                <button type="button" class="product-dialog__close" data-product-dialog-close aria-label="Закрити">×</button>
                <h2>Залишити відгук</h2>
                <form method="post" action="{{ $reviewFormAction }}" class="product-review-form">
                    @csrf
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="product-review-form__trap" aria-hidden="true">

                    <label>
                        <span>Ваше імʼя</span>
                        <input type="text" name="author_name" value="{{ old('author_name') }}" autocomplete="name" required>
                        @error('author_name', 'review')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="author_email" value="{{ old('author_email') }}" autocomplete="email" placeholder="Не обовʼязково">
                        @error('author_email', 'review')<small>{{ $message }}</small>@enderror
                    </label>

                    <fieldset class="product-review-form__rating">
                        <legend>Оцінка</legend>
                        <div>
                            @for ($star = 5; $star >= 1; $star--)
                                <label>
                                    <input type="radio" name="rating" value="{{ $star }}" @checked((int) old('rating', 5) === $star)>
                                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.9 2.5 5.1 5.6.8-4 4 1 5.6-5-2.7-5 2.7 1-5.6-4-4 5.5-.8L10 1.9Z"/></svg>
                                    <span>{{ $star }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('rating', 'review')<small>{{ $message }}</small>@enderror
                    </fieldset>

                    <label>
                        <span>Відгук</span>
                        <textarea name="body" rows="3" required>{{ old('body') }}</textarea>
                        @error('body', 'review')<small>{{ $message }}</small>@enderror
                    </label>

                    <div class="product-review-form__footer">
                        <button type="submit" class="product-review-form__submit">Надіслати відгук</button>
                        <p>Відгук буде опубліковано після модерації.</p>
                    </div>
                </form>
            </div>
        </dialog>

        <script type="application/json" data-product-json>
            {!! json_encode($productPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        </script>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.storefront-ui-scripts')
        @include('storefront.partials.cart-drawer-scripts')

        @if (file_exists(public_path('hot')))
            @vite('resources/js/storefront-product.js')
        @else
            <script type="module" src="{{ Vite::asset('resources/js/storefront-product.js') }}"></script>
        @endif

        @foreach ($schemas as $schema)
            <script type="application/ld+json">
                {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
            </script>
        @endforeach
    </body>
</html>
