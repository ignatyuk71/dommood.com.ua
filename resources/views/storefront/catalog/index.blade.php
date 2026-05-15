<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] ?? ($category?->name ? $category->name.' - '.$storeName : 'Каталог - '.$storeName) }}</title>
        <meta name="description" content="{{ $seo['meta_description'] ?? ($category?->description ?: 'Каталог товарів '.$storeName) }}">
        @if ($metaRobots)
            <meta name="robots" content="{{ $metaRobots }}">
        @endif
        <link rel="canonical" href="{{ $seo['canonical_url'] ?? url('/catalog'.($category?->slug ? '/'.$category->slug : '')) }}">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#29277f">
        <meta property="og:title" content="{{ $seo['title'] ?? $heading }}">
        <meta property="og:description" content="{{ $seo['meta_description'] ?? $intro }}">
        <meta property="og:url" content="{{ $seo['canonical_url'] ?? url()->current() }}">
        <meta property="og:type" content="website">
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

            $productUrl = static fn (array $product): string => $product['url'] ?? url('/catalog/'.($product['category']['slug'] ?? 'catalog').'/'.$product['slug']);
            $hasFilters = ($priceFilter['is_available'] ?? false) || count($categoryFilters) > 0 || count($filterGroups) > 0;
            $totalProducts = $products->total();
            $lastTwoDigits = $totalProducts % 100;
            $lastDigit = $totalProducts % 10;
            $productsCountLabel = ($lastTwoDigits < 11 || $lastTwoDigits > 14) && $lastDigit === 1
                ? 'товар'
                : ((($lastTwoDigits < 11 || $lastTwoDigits > 14) && $lastDigit >= 2 && $lastDigit <= 4) ? 'товари' : 'товарів');
            $categoryDescription = trim((string) ($category?->description ?? ''));
        @endphp

        <div class="storefront-page storefront-catalog-page">
            @include('storefront.partials.site-header')

            <main>
                <section class="storefront-catalog-hero">
                    <div class="container">
                        <nav class="storefront-catalog-breadcrumbs" aria-label="Хлібні крихти">
                            <a href="{{ route('home') }}">Головна</a>
                            <span>/</span>
                            <a href="{{ url('/catalog') }}">Каталог</a>
                            @if ($category)
                                <span>/</span>
                                <span>{{ $category->name }}</span>
                            @endif
                        </nav>

                        <div class="storefront-catalog-hero__grid">
                            <div class="storefront-catalog-hero__content">
                                <h1>{{ $heading }}</h1>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="storefront-catalog-section">
                    <div class="container">
                        @if ($hasFilters || count($activeFilterLabels) > 0)
                            <div class="storefront-catalog-mobile-toolbar">
                                @if ($hasFilters)
                                    <button type="button" class="storefront-catalog-filter-toggle" data-catalog-filters-open>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 4h-7"/><path d="M10 4H3"/><path d="M21 12h-9"/><path d="M8 12H3"/><path d="M21 20h-5"/><path d="M12 20H3"/><circle cx="12" cy="4" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="14" cy="20" r="2"/></svg>
                                        <span>Фільтр</span>
                                    </button>
                                @endif

                                @if (count($activeFilterLabels) > 0)
                                    <a href="{{ $clearFiltersUrl }}" class="storefront-catalog-clear-mobile">Очистити</a>
                                @endif
                            </div>
                        @endif

                        <div @class([
                            'storefront-catalog-layout',
                            'storefront-catalog-layout--no-filters' => ! $hasFilters,
                        ])>
                            @if ($hasFilters)
                                <aside class="storefront-catalog-filters storefront-catalog-filters--desktop" aria-label="Фільтри товарів">
                                    @include('storefront.catalog.partials.filters', [
                                        'priceFilter' => $priceFilter,
                                        'categoryFilters' => $categoryFilters,
                                        'filterGroups' => $filterGroups,
                                        'activeFilterLabels' => $activeFilterLabels,
                                        'clearFiltersUrl' => $clearFiltersUrl,
                                    ])
                                </aside>
                            @endif

                            <div class="storefront-catalog-results">
                                @if (count($activeFilterLabels) > 0)
                                    <div class="storefront-catalog-active-filters" aria-label="Активні фільтри">
                                        @foreach ($activeFilterLabels as $filter)
                                            <a href="{{ $filter['url'] }}">
                                                {{ $filter['label'] }}
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>
                                            </a>
                                        @endforeach
                                        <a href="{{ $clearFiltersUrl }}" class="is-clear">Очистити все</a>
                                    </div>
                                @endif

                                @if ($products->count() > 0)
                                    <div class="storefront-product-grid storefront-catalog-grid">
                                        @foreach ($products as $product)
                                            @include('storefront.partials.product-card', [
                                                'product' => $product,
                                                'formatMoney' => $formatMoney,
                                                'productUrl' => $productUrl,
                                            ])
                                        @endforeach
                                    </div>

                                    <div class="storefront-catalog-pagination">
                                        {{ $products->links() }}
                                    </div>
                                @else
                                    <div class="storefront-empty storefront-catalog-empty">
                                        <h2>Товарів поки немає</h2>
                                        <p class="mb-0">У цій категорії немає активних товарів під вибрані умови.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                @if ($categoryDescription !== '')
                    <section class="storefront-catalog-description">
                        <div class="container">
                            <p>{{ $categoryDescription }}</p>
                        </div>
                    </section>
                @endif

                @if ($seoText)
                    <section class="storefront-catalog-seo">
                        <div class="container">
                            <article class="storefront-seo-card storefront-catalog-seo__content">
                                {!! $seoText !!}
                            </article>
                        </div>
                    </section>
                @endif
            </main>

            @if ($hasFilters)
                <div class="storefront-catalog-filter-drawer" data-catalog-filters aria-hidden="true" inert>
                    <button type="button" class="storefront-catalog-filter-drawer__overlay" data-catalog-filters-close aria-label="Закрити фільтри"></button>
                    <aside class="storefront-catalog-filter-drawer__panel" aria-label="Мобільні фільтри">
                        <div class="storefront-catalog-filter-drawer__head">
                            <button type="button" data-catalog-filters-close aria-label="Назад до товарів">
                                <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <strong>Фільтр</strong>
                            <span aria-hidden="true"></span>
                        </div>

                        @include('storefront.catalog.partials.filters', [
                            'filterMode' => 'mobile',
                            'totalProducts' => $totalProducts,
                            'productsCountLabel' => $productsCountLabel,
                            'priceFilter' => $priceFilter,
                            'categoryFilters' => $categoryFilters,
                            'filterGroups' => $filterGroups,
                            'activeFilterLabels' => $activeFilterLabels,
                            'clearFiltersUrl' => $clearFiltersUrl,
                        ])
                    </aside>
                </div>
            @endif

            @include('storefront.partials.site-footer')
        </div>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.storefront-ui-scripts')
        @include('storefront.partials.cart-drawer-scripts')

        <script type="application/ld+json">
            {!! json_encode($schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </body>
</html>
