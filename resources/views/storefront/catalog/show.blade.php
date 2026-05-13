<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] ?? ($product['name'].' - '.$storeName) }}</title>
        <meta name="description" content="{{ $seo['meta_description'] ?? ($product['short_description'] ?: $product['name']) }}">
        <link rel="canonical" href="{{ $seo['canonical_url'] ?? url('/catalog/'.$category->slug.'/'.$product['slug']) }}">
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $seo['title'] ?? $product['name'] }}">
        <meta property="og:description" content="{{ $seo['meta_description'] ?? ($product['short_description'] ?: $product['name']) }}">
        @if ($product['image_url'])
            <meta property="og:image" content="{{ $product['image_url'] }}">
        @endif
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#7b1a25">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/storefront.css', 'resources/css/storefront-product.css'])
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront-product.css') }}">
        @endif
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
            $initialVariant = $variants->first();
            $currentPrice = (int) ($initialVariant['price_cents'] ?? $product['price_cents']);
            $currentOldPrice = (int) ($initialVariant['old_price_cents'] ?? $product['old_price_cents']);
            $hasDiscount = $currentOldPrice > $currentPrice && $currentPrice > 0;
            $discountPercent = $hasDiscount ? (int) round((1 - ($currentPrice / $currentOldPrice)) * 100) : 0;
            $isPurchasable = (int) ($product['id'] ?? 0) > 0 && ($product['stock_status'] ?? 'in_stock') !== \App\Models\Product::STOCK_OUT_OF_STOCK;
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
                ->unique(fn (array $variant): string => mb_strtolower(($variant['color_name'] ?? '').'|'.($variant['color_hex'] ?? '')))
                ->values();
            $colorProducts = collect($product['color_options'] ?? []);
            $sizes = $variants
                ->filter(fn (array $variant): bool => filled($variant['size'] ?? null))
                ->unique(fn (array $variant): string => mb_strtolower($variant['size']))
                ->values();
            $rating = (float) ($product['rating_average'] ?? 0);
            $reviewCount = (int) ($product['reviews_count'] ?? 0);
            $schemas = collect($schemas ?? [])->filter()->values();

            $productPayload = [
                'id' => $product['id'],
                'name' => $product['name'],
                'currency' => $product['currency'],
                'base_price_cents' => $product['price_cents'],
                'base_old_price_cents' => $product['old_price_cents'],
                'sku' => $product['sku'],
                'stock_status' => $product['stock_status'],
                'stock_status_label' => $product['stock_status_label'],
                'variants' => $variants->values()->all(),
            ];
        @endphp

        <div class="storefront-page storefront-product-page" data-product-page>
            @include('storefront.partials.site-header')

            <main>
                <section class="product-pdp">
                    <div class="container">
                        <nav class="product-breadcrumbs" aria-label="Хлібні крихти">
                            <a href="{{ route('home') }}">Головна</a>
                            <span aria-hidden="true">/</span>
                            <a href="{{ url('/catalog/'.$category->slug) }}">{{ $category->name }}</a>
                            <span aria-hidden="true">/</span>
                            <span>{{ $product['name'] }}</span>
                        </nav>

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
                                    @if ($product['short_description'])
                                        <p class="product-summary__intro">{{ $product['short_description'] }}</p>
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
                                                        @endphp
                                                        <label @class(['is-active' => $loop->first]) data-product-color-option>
                                                            <input type="radio" name="product_color" value="{{ $colorKey }}" @checked($loop->first)>
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
                                                        <label @class(['is-active' => $loop->first]) data-product-size-option>
                                                            <input type="radio" name="product_size" value="{{ mb_strtolower($variant['size']) }}" @checked($loop->first)>
                                                            <span>{{ $variant['size'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @elseif ($variants->isNotEmpty())
                                            <fieldset class="product-option-group">
                                                <legend><span>Варіант</span></legend>
                                                <div class="product-size-options">
                                                    @foreach ($variants as $variant)
                                                        <label @class(['is-active' => $loop->first]) data-product-variant-option>
                                                            <input type="radio" name="product_variant_choice" value="{{ $variant['id'] }}" @checked($loop->first)>
                                                            <span>{{ $variant['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @endif

                                        <span @class(['product-stock', 'is-warning' => $product['stock_status'] === 'preorder', 'is-muted' => $product['stock_status'] === 'out_of_stock']) data-product-stock>{{ $product['stock_status_label'] }}</span>

                                        <div class="product-buy-actions">
                                            <div class="product-quantity" aria-label="Кількість">
                                                <button type="button" data-product-qty-minus aria-label="Зменшити кількість">−</button>
                                                <input type="number" min="1" max="99" value="1" inputmode="numeric" data-product-qty aria-label="Кількість товару">
                                                <button type="button" data-product-qty-plus aria-label="Збільшити кількість">+</button>
                                            </div>
                                            <button type="submit" class="product-add-button">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                                <span>У кошик</span>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="product-help-cards" aria-label="Допомога з розміром">
                                        <button type="button" data-product-dialog-open="size-chart">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 15 11-11 5 5L9 20H4z"/><path d="m13 6 2 2"/><path d="m10 9 2 2"/><path d="m7 12 2 2"/></svg>
                                            <span>Таблиця розмірів</span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                        <button type="button" data-product-dialog-open="measure-guide">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.4 9a2.8 2.8 0 1 1 4 2.5c-.9.5-1.4 1.1-1.4 2.2"/><path d="M12 17h.01"/></svg>
                                            <span>Як знімати мірки</span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="product-unavailable">
                                        <strong>Товар тимчасово недоступний</strong>
                                        <p>Напишіть нам у месенджер, і менеджер підбере найближчу альтернативу.</p>
                                    </div>
                                @endif

                                <div class="product-benefits" aria-label="Переваги покупки">
                                    <div class="product-benefit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h11v10H4z"/><path d="M15 10h3.5l1.5 2.2V17h-5z"/><circle cx="7" cy="18" r="1.5"/><circle cx="17.5" cy="18" r="1.5"/></svg><span>Відправка після підтвердження</span></div>
                                    <div class="product-benefit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 4 4L20 4"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6"/></svg><span>Обмін/повернення 14 днів</span></div>
                                    <div class="product-benefit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v10H3z"/><path d="M7 11h5"/><path d="M17 13h.01"/></svg><span>Оплата онлайн або при отриманні</span></div>
                                    <div class="product-benefit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"/></svg><span>Акуратне пакування</span></div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </section>

                @if (count($relatedProducts ?? []) > 0)
                    <section class="product-related">
                        <div class="container">
                            <div class="storefront-section-heading">
                                <div>
                                    <h2>З цим товаром купують</h2>
                                    <p>Схожі моделі, які легко додати до замовлення або підібрати в іншому кольорі.</p>
                                </div>
                                <a href="{{ url('/catalog/'.$category->slug) }}" class="storefront-section-link">До категорії</a>
                            </div>
                            <div class="storefront-product-grid storefront-catalog-grid">
                                @foreach ($relatedProducts as $relatedProduct)
                                    @include('storefront.partials.product-card', [
                                        'product' => $relatedProduct,
                                        'productUrl' => $productUrl,
                                        'formatMoney' => $formatMoney,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            </main>

            @include('storefront.partials.site-footer')
        </div>

        @if ($isPurchasable)
            <div class="product-mobile-purchase" data-product-mobile-sticky>
                <div class="product-mobile-purchase__price">
                    <span data-product-mobile-price>{{ $formatMoney($currentPrice, $product['currency']) }}</span>
                    <del data-product-mobile-old-price @if (! $hasDiscount) hidden @endif>{{ $formatMoney($currentOldPrice, $product['currency']) }}</del>
                </div>
                <button type="button" data-product-mobile-submit>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span>У кошик</span>
                </button>
            </div>
        @endif

        <dialog class="product-dialog" data-product-dialog="size-chart">
            <div class="product-dialog__panel">
                <button type="button" class="product-dialog__close" data-product-dialog-close aria-label="Закрити">×</button>
                <h2>{{ $product['size_chart']['title'] ?? 'Таблиця розмірів' }}</h2>
                <div class="product-dialog__tabs" aria-label="Навігація по розмірах">
                    <button type="button" class="is-active">Розмірна сітка</button>
                    <button type="button" data-product-dialog-open="measure-guide">Як знімати мірки</button>
                </div>
                @if ($product['size_chart']['description'] ?? null)
                    <p>{{ $product['size_chart']['description'] }}</p>
                @else
                    <p>Порівняйте довжину стопи з розміром. Якщо вагаєтесь між двома розмірами, краще уточнити заміри у менеджера.</p>
                @endif
                @if ($product['size_chart']['content_html'] ?? null)
                    <div class="product-dialog__content">{!! $product['size_chart']['content_html'] !!}</div>
                @else
                    <table class="product-size-table">
                        <thead>
                            <tr>
                                <th scope="col">Розмір</th>
                                <th scope="col">Довжина</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>36/37</td><td>24-24.5 см</td></tr>
                            <tr><td>38/39</td><td>25-25.5 см</td></tr>
                            <tr><td>40/41</td><td>26-26.5 см</td></tr>
                            <tr><td>42/43</td><td>27-27.5 см</td></tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </dialog>

        <dialog class="product-dialog product-dialog--measure" data-product-dialog="measure-guide">
            <div class="product-dialog__panel">
                <button type="button" class="product-dialog__close" data-product-dialog-close aria-label="Закрити">×</button>
                <h2>Як знімати мірки</h2>
                <div class="product-dialog__tabs" aria-label="Навігація по розмірах">
                    <button type="button" data-product-dialog-open="size-chart">Розмірна сітка</button>
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
