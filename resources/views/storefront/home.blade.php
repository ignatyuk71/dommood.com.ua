<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $storeName }} - товари для дому та щоденного затишку</title>
        <meta name="description" content="{{ $storeName }}: категорії, новинки, актуальні ціни та наявність для швидкої покупки онлайн.">
        <link rel="canonical" href="{{ url('/') }}">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#29277f">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet">
        @if (file_exists(public_path('hot')))
            @vite('resources/css/storefront.css')
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
        @endif
    </head>
    <body>
        @php
            $productsCount = count($products);
            $newProductsCount = count($newProducts ?? []);
            $heroCategories = $categories;

            $formatMoney = static function (?int $cents, ?string $currency = 'UAH'): string {
                $amount = number_format(((int) $cents) / 100, ((int) $cents) % 100 === 0 ? 0 : 2, ',', ' ');

                return $amount.' '.(($currency ?: 'UAH') === 'UAH' ? 'грн' : $currency);
            };

            $productUrl = static function (array $product): string {
                $categorySlug = $product['category']['slug'] ?? 'catalog';

                return url('/catalog/'.$categorySlug.'/'.$product['slug']);
            };

            $utilityLinks = $utilityLinks ?? [];
            $mobileNavigationItems = count($mobileMenuItems ?? []) > 0 ? $mobileMenuItems : $menuItems;
            $headerPhone = $supportPhone ?: '+380679753512';
            $cleanHeaderPhone = preg_replace('/[^0-9+]/', '', $headerPhone);
            $messengerPhone = ltrim($cleanHeaderPhone, '+');
            $viberHref = 'viber://chat?number='.rawurlencode($cleanHeaderPhone);
            $telegramHref = 'tg://resolve?phone='.$messengerPhone;
            $whatsappHref = 'https://wa.me/'.$messengerPhone;
            $outdoorPromoProducts = $outdoorPromoProducts ?? [];
            $outdoorCategory = $outdoorCategory ?? null;
            $pajamasPromoProducts = $pajamasPromoProducts ?? [];
            $pajamasCategory = $pajamasCategory ?? null;
            $categoryUrlByHints = static function (array $slugs, array $nameFragments, string $fallbackQuery) use ($categories): string {
                foreach ($categories as $category) {
                    $categorySlug = (string) ($category['slug'] ?? '');
                    $categoryName = mb_strtolower((string) ($category['name'] ?? ''));

                    if (in_array($categorySlug, $slugs, true)) {
                        return $category['url'];
                    }

                    foreach ($nameFragments as $fragment) {
                        if ($fragment !== '' && str_contains($categoryName, $fragment)) {
                            return $category['url'];
                        }
                    }
                }

                return url('/catalog').'?q='.rawurlencode($fallbackQuery);
            };
            $pajamasCategoryUrl = $pajamasCategory['url'] ?? $categoryUrlByHints(
                ['zhinochi-pizhamy', 'zhinochi-pizhami', 'pizhamy', 'pizhami'],
                ['піжам', 'pizham', 'pizama'],
                'жіночі піжами',
            );
            $outdoorCategoryUrl = $outdoorCategory['url'] ?? $categoryUrlByHints(
                ['zhinochi-kaptsi-dlia-vulytsi', 'vulychni-tapochky'],
                ['вулич', 'вулиц', 'гумов'],
                'вуличні тапочки',
            );
            $aboutUrl = url('/pro-nas');
            $footerPhone = $supportPhone ?: '+380679753512';
            $cleanFooterPhone = preg_replace('/[^0-9+]/', '', $footerPhone);
            $messengerFooterPhone = ltrim($cleanFooterPhone, '+');
            $footerEmail = $supportEmail ?: 'dommood.com.ua@gmail.com';
            $footerAddress = 'м.Костопіль, вул. Рівненська, 107, Рівненська область, Україна, 35000';
            $footerSocialLinks = [
                ['title' => 'Instagram', 'url' => 'https://www.instagram.com/dommood.com.ua/', 'icon' => 'instagram'],
                ['title' => 'TikTok', 'url' => 'https://www.tiktok.com/@dommood.com.ua', 'icon' => 'tiktok'],
            ];
            $footerClientLinks = [
                ['title' => 'Вхід до кабінету', 'url' => $canLogin ? route('login') : url('/account')],
                ['title' => 'Про нас', 'url' => url('/pro-nas')],
                ['title' => 'Оплата і доставка', 'url' => url('/oplata-i-dostavka')],
                ['title' => 'Обмін та повернення', 'url' => url('/obmin-ta-povernennya')],
                ['title' => 'Контакти', 'url' => url('/kontakty')],
                ['title' => 'Угода користувача', 'url' => url('/uhoda-korystuvacha')],
                ['title' => 'Політика конфіденційності', 'url' => url('/polityka-konfidentsiinosti')],
                ['title' => 'Відгуки про магазин', 'url' => url('/vidhuky-pro-mahazyn')],
                ['title' => 'Безкоштовне повернення', 'url' => url('/bezkoshtovne-povernennia-novoiu-poshtoiu')],
            ];
            $outdoorPromoBackground = asset('storage/banners/featured/outdoor-promo-img-8006.webp');
            $pajamasPromoBackground = asset('storage/banners/featured/pajamas-promo-user-attachment.webp');
            $homeComfortLinks = [
                [
                    'title' => 'Для коротких виходів',
                    'text' => 'Пари на гумовій підошві для двору, тераси і дороги.',
                    'url' => $outdoorCategoryUrl,
                ],
                [
                    'title' => 'Для вечора вдома',
                    'text' => 'Мʼякі комплекти для спокійного вечора і сну.',
                    'url' => $pajamasCategoryUrl,
                ],
                [
                    'title' => 'На подарунок',
                    'text' => 'Затишні речі, які легко обрати без довгих роздумів.',
                    'url' => url('/catalog').'?q='.rawurlencode('подарунок'),
                ],
            ];
            $homeFaqItems = collect([
                [
                    'question' => 'Які товари можна купити в '.$storeName.'?',
                    'answer' => $storeName.' підбирає мʼякі домашні тапочки, вуличні моделі на гумовій підошві та піжами для щоденного комфорту вдома.',
                ],
                [
                    'question' => 'Чи підходять пухнасті тапочки для вулиці?',
                    'answer' => 'Так, моделі на гумовій підошві підходять для коротких виходів надвір, тераси, двору або поїздок. Для дощу, снігу чи довгих прогулянок краще обрати спеціалізоване взуття.',
                ],
                [
                    'question' => 'Як підібрати правильний розмір?',
                    'answer' => 'Орієнтуйтесь на довжину стопи й звичний розмір взуття. Якщо вагаєтесь між двома розмірами або плануєте носити зі шкарпетками, краще обрати більший або уточнити заміри у менеджера.',
                ],
                [
                    'question' => 'Як доглядати за пухнастими тапочками?',
                    'answer' => 'Очищуйте поверхню мʼякою щіткою або вологою серветкою, без агресивної хімії та сильного намокання. Сушіть природним способом, подалі від батарей і прямих джерел тепла.',
                ],
                [
                    'question' => 'Чи можна обміняти або повернути товар?',
                    'answer' => 'Так, обмін або повернення можливі згідно з умовами магазину, якщо товар не був у використанні, має збережений товарний вигляд і пакування.',
                ],
                [
                    'question' => 'Від якої суми доставка безкоштовна?',
                    'answer' => 'Безкоштовна доставка діє для замовлень від 1200 грн. Остаточні умови доставки менеджер підтвердить під час оформлення замовлення.',
                    'answer_html' => 'Безкоштовна доставка діє для замовлень від <span class="storefront-inline-price">1200 грн</span>. Остаточні умови доставки менеджер підтвердить під час оформлення замовлення.',
                ],
                [
                    'question' => 'Як швидко оформити замовлення?',
                    'answer' => 'Оберіть товар у каталозі, залиште контактні дані в checkout, а менеджер підтвердить деталі, наявність, оплату та доставку.',
                ],
            ]);
            $schemas = [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $storeName,
                    'url' => url('/'),
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => $storeName,
                    'url' => url('/'),
                    'logo' => asset('brand/dom-mood-wordmark-black.png'),
                    'contactPoint' => array_filter([
                        '@type' => 'ContactPoint',
                        'email' => $supportEmail,
                        'telephone' => $supportPhone,
                        'contactType' => 'customer support',
                        'areaServed' => 'UA',
                    ]),
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => 'Товари '.$storeName,
                    'itemListElement' => collect($products)->take(8)->values()->map(fn (array $product, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'url' => $productUrl($product),
                        'name' => $product['name'],
                    ])->all(),
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $homeFaqItems->map(fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ])->all(),
                ],
            ];
        @endphp

        <div class="storefront-page">
            <header class="storefront-site-header">
                <div class="storefront-desktop-header">
                    <div class="storefront-utility-bar">
                        <div @class(['container', 'is-utility-compact' => count($utilityLinks) === 0])>
                            @if (count($utilityLinks) > 0)
                                <nav class="storefront-utility-menu" aria-label="Головне меню сайту">
                                    @foreach ($utilityLinks as $link)
                                        <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
                                    @endforeach
                                </nav>
                            @endif

                            <a href="{{ $canLogin ? route('login') : '#' }}" class="storefront-utility-login">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
                                <span>Вхід</span>
                            </a>
                        </div>
                    </div>

                    <div class="storefront-desktop-main">
                        <div class="container">
                            <div class="storefront-desktop-main__inner">
                                <div class="storefront-desktop-main__side is-left">
                                    <form action="{{ url('/catalog') }}" method="get" class="storefront-search" role="search">
                                        <input type="search" name="q" placeholder="пошук товарів" aria-label="Пошук товарів">
                                        <button type="submit" aria-label="Шукати">
                                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
                                        </button>
                                    </form>

                                    <div class="storefront-contact-line" aria-label="Контакти підтримки">
                                        <a href="tel:{{ $cleanHeaderPhone }}" class="storefront-phone-link">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.8 16.9a15.4 15.4 0 0 1-6.7-6.7l1.8-1.8c.4-.4.5-.9.3-1.4L8.2 4.4C8 3.9 7.5 3.5 7 3.5H4.4c-.8 0-1.4.6-1.4 1.4C3 13.8 10.2 21 19.1 21c.8 0 1.4-.6 1.4-1.4V17c0-.6-.4-1.1-.9-1.2L17 14.8c-.5-.2-1 0-1.4.3l-1.8 1.8Z"/></svg>
                                            <span>{{ $headerPhone }}</span>
                                        </a>
                                        <span class="storefront-messenger-links" role="group" aria-label="Написати в месенджер">
                                            <a href="{{ $viberHref }}" class="storefront-messenger-link is-viber" aria-label="Написати у Viber на {{ $headerPhone }}" title="Viber">
                                                <img src="{{ asset('brand/icons/viber.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="{{ $telegramHref }}" class="storefront-messenger-link is-telegram" aria-label="Написати у Telegram на {{ $headerPhone }}" title="Telegram">
                                                <img src="{{ asset('brand/icons/telegram.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="{{ $whatsappHref }}" class="storefront-messenger-link is-whatsapp" aria-label="Написати у WhatsApp на {{ $headerPhone }}" title="WhatsApp">
                                                <img src="{{ asset('brand/icons/whatsapp.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="https://www.instagram.com/dommood.com.ua/" class="storefront-messenger-link is-instagram" aria-label="Instagram" title="Instagram">
                                                <img src="{{ asset('brand/icons/instagram.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="https://www.facebook.com/dommood.com.ua" class="storefront-messenger-link is-facebook" aria-label="Facebook" title="Facebook">
                                                <img src="{{ asset('brand/icons/facebook.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="https://www.tiktok.com/@dommood.com.ua" class="storefront-messenger-link is-tiktok" aria-label="TikTok" title="TikTok">
                                                <img src="{{ asset('brand/icons/tiktok.svg') }}" alt="" width="24" height="24">
                                            </a>
                                        </span>
                                    </div>
                                </div>

                                <a href="{{ route('home') }}" class="storefront-desktop-logo" aria-label="{{ $storeName }} - тапочки та піжами">
                                    <img src="{{ asset('brand/dom-mood-wordmark-black.png') }}" alt="{{ $storeName }}" width="290" height="48">
                                    <span class="storefront-logo-tagline">Тапочки та піжами</span>
                                </a>

                                <div class="storefront-desktop-main__side is-right">
                                    <div class="storefront-worktime" aria-label="Графік роботи: Пн-Сб 11:00-19:00, неділя вихідний">
                                        <span class="storefront-worktime__accent" aria-hidden="true"></span>
                                        <div class="storefront-worktime__body">
                                            <span class="storefront-worktime__title">Графік роботи</span>
                                            <span class="storefront-worktime__hours">
                                                <span>Пн-Сб</span>
                                                <span>11:00-19:00</span>
                                            </span>
                                            <span class="storefront-worktime__closed">Неділя - вихідний</span>
                                        </div>
                                    </div>

                                    <a href="{{ url('/cart') }}" class="storefront-cart-link" data-cart-target data-cart-open aria-label="{{ ($headerCartSummary['is_empty'] ?? true) ? 'Кошик' : 'Кошик: '.$headerCartSummary['aria_label'] }}">
                                        <span class="storefront-cart-link__icon">
                                            <svg viewBox="0 0 24 24"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                            <span class="storefront-cart-badge" data-cart-badge @if ($headerCartSummary['is_empty'] ?? true) hidden @endif>{{ $headerCartSummary['badge'] ?? '' }}</span>
                                        </span>
                                        <span class="storefront-cart-link__body">
                                            <span class="storefront-cart-link__title">Мій кошик</span>
                                            <span class="storefront-cart-link__summary" data-cart-summary @if ($headerCartSummary['is_empty'] ?? true) hidden @endif>{{ $headerCartSummary['header_label'] ?? '' }}</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (count($menuItems) > 0)
                        <div class="storefront-category-nav-wrap">
                            <div class="container">
                                <nav class="storefront-category-nav" aria-label="Основне меню сайту">
                                    @include('storefront.partials.desktop-menu-items', ['items' => $menuItems])
                                </nav>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="storefront-mobile-header">
                    <div class="storefront-mobile-bar">
                        <button type="button" class="storefront-mobile-icon" data-mobile-menu-open aria-label="Відкрити меню">
                            <svg viewBox="0 0 24 24"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                        </button>
                        <a href="{{ route('home') }}" class="storefront-mobile-logo" aria-label="{{ $storeName }} - тапочки та піжами">
                            <img src="{{ asset('brand/dom-mood-wordmark-black.png') }}" alt="{{ $storeName }}" width="142" height="24">
                            <span class="storefront-logo-tagline">Тапочки та піжами</span>
                        </a>
                        <button type="button" class="storefront-mobile-icon" data-mobile-search-open aria-label="Відкрити пошук">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
                        </button>
                        <a href="{{ url('/cart') }}" class="storefront-mobile-icon" data-cart-target data-cart-open aria-label="{{ ($headerCartSummary['is_empty'] ?? true) ? 'Кошик' : 'Кошик: '.$headerCartSummary['aria_label'] }}">
                            <span class="storefront-cart-link__icon">
                                <svg viewBox="0 0 24 24"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                <span class="storefront-cart-badge" data-cart-badge @if ($headerCartSummary['is_empty'] ?? true) hidden @endif>{{ $headerCartSummary['badge'] ?? '' }}</span>
                            </span>
                        </a>
                    </div>
                </div>

                <div class="storefront-mobile-search" data-mobile-search aria-hidden="true" inert>
                    <form action="{{ url('/catalog') }}" method="get" role="search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
                        <input type="search" name="q" placeholder="Пошук в каталозі" aria-label="Пошук в каталозі">
                        <button type="button" data-mobile-search-close aria-label="Закрити пошук">
                            <svg viewBox="0 0 24 24"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>
                        </button>
                    </form>
                </div>

                <div class="storefront-mobile-menu" data-mobile-menu aria-hidden="true" inert>
                    <button type="button" class="storefront-mobile-menu__overlay" data-mobile-menu-close aria-label="Закрити меню"></button>
                    <aside class="storefront-mobile-drawer" aria-label="Мобільне меню">
                        <div class="storefront-mobile-drawer__head">
                            <button type="button" data-mobile-menu-close aria-label="Закрити меню">
                                <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <strong>{{ $storeName }}</strong>
                        </div>

                        <nav class="storefront-mobile-nav" aria-label="Мобільне меню сайту">
                            @include('storefront.partials.mobile-menu-items', ['items' => $mobileNavigationItems, 'level' => 0])
                        </nav>

                        <nav class="storefront-mobile-action-links" aria-label="Дії клієнта">
                            <a href="{{ $canLogin ? route('login') : '#' }}">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/><circle cx="12" cy="12" r="10"/></svg>
                                Вхід для клієнтів
                            </a>
                        </nav>

                        <div class="storefront-mobile-contacts">
                            <a href="tel:{{ $cleanHeaderPhone }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.8 16.9a15.4 15.4 0 0 1-6.7-6.7l1.8-1.8c.4-.4.5-.9.3-1.4L8.2 4.4C8 3.9 7.5 3.5 7 3.5H4.4c-.8 0-1.4.6-1.4 1.4C3 13.8 10.2 21 19.1 21c.8 0 1.4-.6 1.4-1.4V17c0-.6-.4-1.1-.9-1.2L17 14.8c-.5-.2-1 0-1.4.3l-1.8 1.8Z"/></svg>
                                {{ $headerPhone }}
                            </a>
                            <a href="{{ $viberHref }}" aria-label="Написати у Viber на {{ $headerPhone }}">
                                <span class="storefront-mobile-contact-icon">
                                    <img src="{{ asset('brand/icons/viber.svg') }}" alt="" width="27" height="27">
                                </span>
                                {{ $headerPhone }}
                            </a>
                            <a href="{{ $telegramHref }}" aria-label="Написати у Telegram на {{ $headerPhone }}">
                                <span class="storefront-mobile-contact-icon">
                                    <img src="{{ asset('brand/icons/telegram.svg') }}" alt="" width="27" height="27">
                                </span>
                                {{ $headerPhone }}
                            </a>
                            <a href="{{ $whatsappHref }}" aria-label="Написати у WhatsApp на {{ $headerPhone }}">
                                <span class="storefront-mobile-contact-icon">
                                    <img src="{{ asset('brand/icons/whatsapp.svg') }}" alt="" width="27" height="27">
                                </span>
                                {{ $headerPhone }}
                            </a>
                        </div>
                    </aside>
                </div>
            </header>

            <main>
                <section class="storefront-hero storefront-category-banner" aria-labelledby="home-hero-title">
                    @php
                        $heroMainBanner = $homeBanners['main'] ?? [];
                        $heroSideBanners = array_values(array_filter([
                            $homeBanners['side_top'] ?? [],
                            $homeBanners['side_bottom'] ?? [],
                        ], fn ($banner) => filled($banner['image_url'] ?? null)));
                        $railHeroCategories = $heroCategories;
                    @endphp

                    @if (($heroMainBanner['image_url'] ?? null) || count($heroSideBanners) > 0 || count($heroCategories) > 0)
                        <div class="storefront-category-banner__frame">
                            <div class="storefront-category-banner__layout">
                                <a href="{{ $heroMainBanner['url'] ?? url('/catalog') }}" class="storefront-category-banner__main" aria-label="Перейти до промо-добірки">
                                    @if ($heroMainBanner['image_url'] ?? null)
                                        <span class="storefront-category-banner__media">
                                            <picture>
                                                @if ($heroMainBanner['mobile_image_url'] ?? null)
                                                    <source media="(max-width: 767.98px)" srcset="{{ $heroMainBanner['mobile_image_url'] }}">
                                                @endif
                                                <img
                                                    src="{{ $heroMainBanner['image_url'] }}"
                                                    alt="{{ $heroMainBanner['alt'] ?? $storeName }}"
                                                    fetchpriority="high"
                                                    decoding="async"
                                                >
                                            </picture>
                                        </span>
                                    @endif

                                    <span class="storefront-category-banner__body">
                                        <h1 id="home-hero-title">{{ $heroMainBanner['title'] ?? ('Каталог '.$storeName) }}</h1>
                                        <span class="storefront-category-banner__actions">
                                            <span class="storefront-category-banner__button">{{ $heroMainBanner['subtitle'] ?? 'Переглянути добірку' }}</span>
                                        </span>
                                    </span>
                                </a>

                                @if (count($heroSideBanners) > 0)
                                    <div class="storefront-category-banner__side" aria-label="Акцентні категорії">
                                        @foreach ($heroSideBanners as $banner)
                                            <a href="{{ $banner['url'] ?? url('/catalog') }}" class="storefront-category-banner__mini">
                                                <span class="storefront-category-banner__mini-media">
                                                    @if ($banner['image_url'] ?? null)
                                                        <picture>
                                                            @if ($banner['mobile_image_url'] ?? null)
                                                                <source media="(max-width: 767.98px)" srcset="{{ $banner['mobile_image_url'] }}">
                                                            @endif
                                                            <img src="{{ $banner['image_url'] }}" alt="{{ $banner['alt'] ?? $banner['title'] ?? $storeName }}" loading="lazy" decoding="async">
                                                        </picture>
                                                    @else
                                                        <span class="storefront-image-placeholder">{{ mb_substr($banner['title'] ?? 'DM', 0, 2) }}</span>
                                                    @endif
                                                </span>
                                                <span class="storefront-category-banner__mini-overlay" aria-hidden="true"></span>
                                                <span class="storefront-category-banner__mini-body">
                                                    <strong>{{ $banner['title'] ?? $storeName }}</strong>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if (count($railHeroCategories) > 0)
                            <div class="storefront-category-banner__categories" data-category-collage data-category-carousel>
                                <div class="storefront-category-banner__rail" aria-label="Інші категорії магазину">
                                @foreach ($railHeroCategories as $category)
                                    <a href="{{ $category['url'] }}" class="storefront-category-banner__rail-card">
                                        <span class="storefront-category-banner__rail-media">
                                            @if ($category['image_url'])
                                                <img src="{{ $category['image_url'] }}" alt="{{ $category['name'] }}" loading="lazy" decoding="async">
                                            @else
                                                <span class="storefront-image-placeholder">{{ mb_substr($category['name'], 0, 2) }}</span>
                                            @endif
                                        </span>
                                        <span class="storefront-category-banner__rail-body">
                                            <strong>{{ $category['name'] }}</strong>
                                            <span>Дивитися категорію</span>
                                        </span>
                                    </a>
                                @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="container">
                            <div class="storefront-empty">
                                <h1 id="home-hero-title" class="mb-2">Головний банер поки не заповнений</h1>
                                <p class="mb-0">Додайте банер у налаштуваннях магазину або активні категорії з фото.</p>
                            </div>
                        </div>
                    @endif
                </section>

                @if (count($outdoorPromoProducts) > 0)
                <section class="storefront-featured-carousel" aria-labelledby="outdoor-carousel-title" data-featured-carousel>
                    <div class="container">
                        <div class="storefront-featured-carousel__grid">
                            <a href="{{ $outdoorCategoryUrl }}" class="storefront-featured-carousel__promo">
                                <span
                                    class="storefront-featured-carousel__media storefront-featured-carousel__media--background"
                                    style="--dm-featured-promo-image: url('{{ $outdoorPromoBackground }}');"
                                >
                                </span>
                                <span class="storefront-featured-carousel__overlay" aria-hidden="true"></span>
                                <span class="storefront-featured-carousel__content">
                                    <span class="storefront-featured-carousel__eyebrow">{{ $outdoorCategory['name'] ?? 'Жіночі капці для вулиці' }}</span>
                                    <strong>Комфорт поза домом</strong>
                                    <span>Пухнасті жіночі тапочки на гумовій підошві для тераси, двору, поїздок і щоденних виходів.</span>
                                    <span class="storefront-featured-carousel__button">Переглянути вуличні тапочки</span>
                                </span>
                            </a>

                            <div class="storefront-featured-carousel__main">
                                <div class="storefront-featured-carousel__head">
                                    <div>
                                        <p>{{ $outdoorCategory['name'] ?? 'Жіночі капці для вулиці' }}</p>
                                        <h2 id="outdoor-carousel-title">Пухнасті моделі на гумовій підошві</h2>
                                    </div>
                                    <div class="storefront-featured-carousel__controls" aria-label="Керування каруселлю">
                                        <button type="button" data-featured-carousel-prev aria-label="Попередні товари">
                                            <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                                        </button>
                                        <button type="button" data-featured-carousel-next aria-label="Наступні товари">
                                            <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="storefront-featured-carousel__viewport">
                                    <div class="storefront-featured-carousel__track" data-featured-carousel-track>
                                        @foreach ($outdoorPromoProducts as $promoProduct)
                                            <a href="{{ $promoProduct['url'] }}" class="storefront-featured-product" aria-label="Переглянути товар: {{ $promoProduct['name'] }}">
                                                <span class="storefront-featured-product__media">
                                                    @if ($promoProduct['image_url'])
                                                        <img src="{{ $promoProduct['image_url'] }}" alt="{{ $promoProduct['name'] }}" loading="lazy">
                                                    @else
                                                        <span class="storefront-image-placeholder">DM</span>
                                                    @endif
                                                    @if ($promoProduct['discount'])
                                                        <span>{{ $promoProduct['discount'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="storefront-featured-product__body">
                                                    <h3>
                                                        {{ $promoProduct['name'] }}
                                                    </h3>
                                                    <span class="storefront-featured-product__price">
                                                        <strong>{{ $formatMoney($promoProduct['price_cents']) }}</strong>
                                                        @if ($promoProduct['old_price_cents'])
                                                            <del>{{ $formatMoney($promoProduct['old_price_cents']) }}</del>
                                                        @endif
                                                    </span>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="storefront-featured-carousel__dots" aria-hidden="true">
                                    @foreach ($outdoorPromoProducts as $promoProduct)
                                        <span @class(['is-active' => $loop->first])></span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                <section class="storefront-home-bridge" aria-label="Швидкий вибір для домашнього комфорту">
                    <div class="container">
                        <div class="storefront-home-bridge__inner">
                            <div class="storefront-home-bridge__copy">
                                <span>Після вулиці</span>
                                <strong>Додайте до образу щось для дому</strong>
                            </div>
                            <div class="storefront-home-bridge__links">
                                @foreach ($homeComfortLinks as $link)
                                    <a href="{{ $link['url'] }}">
                                        <strong>{{ $link['title'] }}</strong>
                                        <span>{{ $link['text'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                @if (count($pajamasPromoProducts) > 0)
                <section class="storefront-featured-carousel storefront-featured-carousel--promo-right" aria-labelledby="pajamas-carousel-title" data-featured-carousel>
                    <div class="container">
                        <div class="storefront-featured-carousel__grid">
                            <a href="{{ $pajamasCategoryUrl }}" class="storefront-featured-carousel__promo">
                                <span
                                    class="storefront-featured-carousel__media storefront-featured-carousel__media--background"
                                    style="--dm-featured-promo-image: url('{{ $pajamasPromoBackground }}');"
                                >
                                </span>
                                <span class="storefront-featured-carousel__overlay" aria-hidden="true"></span>
                                <span class="storefront-featured-carousel__content">
                                    <span class="storefront-featured-carousel__eyebrow">{{ $pajamasCategory['name'] ?? 'Жіночі піжами' }}</span>
                                    <strong>Затишок для вечорів вдома</strong>
                                    <span>Мʼякі комплекти для сну, ранкової кави, подарунків і спокійних домашніх вихідних.</span>
                                    <span class="storefront-featured-carousel__button">Переглянути жіночі піжами</span>
                                </span>
                            </a>

                            <div class="storefront-featured-carousel__main">
                                <div class="storefront-featured-carousel__head">
                                    <div>
                                        <p>{{ $pajamasCategory['name'] ?? 'Жіночі піжами' }}</p>
                                        <h2 id="pajamas-carousel-title">Мʼякі комплекти для дому та сну</h2>
                                    </div>
                                    <div class="storefront-featured-carousel__controls" aria-label="Керування каруселлю піжам">
                                        <button type="button" data-featured-carousel-prev aria-label="Попередні піжами">
                                            <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                                        </button>
                                        <button type="button" data-featured-carousel-next aria-label="Наступні піжами">
                                            <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="storefront-featured-carousel__viewport">
                                    <div class="storefront-featured-carousel__track" data-featured-carousel-track>
                                        @foreach ($pajamasPromoProducts as $promoProduct)
                                            <a href="{{ $promoProduct['url'] }}" class="storefront-featured-product" aria-label="Переглянути товар: {{ $promoProduct['name'] }}">
                                                <span class="storefront-featured-product__media">
                                                    @if ($promoProduct['image_url'])
                                                        <img src="{{ $promoProduct['image_url'] }}" alt="{{ $promoProduct['name'] }}" loading="lazy">
                                                    @else
                                                        <span class="storefront-image-placeholder">DM</span>
                                                    @endif
                                                    @if ($promoProduct['discount'])
                                                        <span>{{ $promoProduct['discount'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="storefront-featured-product__body">
                                                    <h3>
                                                        {{ $promoProduct['name'] }}
                                                    </h3>
                                                    <span class="storefront-featured-product__price">
                                                        <strong>{{ $formatMoney($promoProduct['price_cents']) }}</strong>
                                                        @if ($promoProduct['old_price_cents'])
                                                            <del>{{ $formatMoney($promoProduct['old_price_cents']) }}</del>
                                                        @endif
                                                    </span>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="storefront-featured-carousel__dots" aria-hidden="true">
                                    @foreach (array_slice($pajamasPromoProducts, 0, 6) as $promoProduct)
                                        <span @class(['is-active' => $loop->first])></span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                <section class="storefront-brand-story" aria-labelledby="brand-story-title">
                    <div class="storefront-brand-story__grid">
                        <a href="{{ $aboutUrl }}" class="storefront-brand-story__media storefront-brand-story__media--left" aria-label="Дізнатися більше про DomMood">
                            <img
                                src="{{ asset('brand/home/brand-story-pink.webp') }}"
                                alt="Яскраво-рожеві пухнасті капці DomMood"
                                loading="lazy"
                                decoding="async"
                                width="900"
                                height="760"
                            >
                        </a>

                        <div class="storefront-brand-story__content">
                            <div class="storefront-brand-story__copy">
                                <h2 id="brand-story-title">DomMood - це домашній комфорт для кожного дня</h2>
                                <p>
                                    DomMood - український інтернет-магазин домашніх капців, жіночих піжам і затишних
                                    речей для дому. Ми підбираємо моделі для щоденного носіння: мʼякі жіночі капці,
                                    пухнасті домашні тапочки, дитячі капці та піжами, у яких зручно відпочивати після
                                    роботи, проводити вихідні й починати ранок без поспіху.
                                </p>
                                <p>
                                    У каталозі є капці з мʼяким верхом, приємною підкладкою та практичною підошвою для
                                    квартири, будинку, тераси або короткого виходу надвір. Для холодного сезону
                                    підійдуть пухнасті моделі з теплим ворсом, для подарунка - яскраві кольори й
                                    базові відтінки, які легко поєднати з домашнім одягом.
                                </p>
                                <a href="{{ $aboutUrl }}" class="storefront-brand-story__button">
                                    Про нас
                                    <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </div>

                        <a href="{{ $aboutUrl }}" class="storefront-brand-story__media storefront-brand-story__media--right" aria-label="Дізнатися більше про DomMood">
                            <img
                                src="{{ asset('brand/home/brand-story-black.webp') }}"
                                alt="Чорні пухнасті капці DomMood"
                                loading="lazy"
                                decoding="async"
                                width="900"
                                height="760"
                            >
                        </a>
                    </div>
                </section>

                @if ($newProductsCount > 0)
                    <section class="storefront-section storefront-section--new-products" aria-labelledby="new-products-title">
                        <div class="container">
                            <div class="storefront-section-heading">
                                <div>
                                    <h2 id="new-products-title">Новинки</h2>
                                    <p>Свіжі моделі DomMood, які варто побачити першими.</p>
                                </div>
                                <a href="{{ url('/catalog').'?filter=new' }}" class="storefront-section-link">Усі новинки</a>
                            </div>

                            <div class="storefront-product-grid storefront-product-grid--new">
                                @foreach (array_slice($newProducts, 0, 8) as $product)
                                    @include('storefront.partials.product-card', [
                                        'product' => $product,
                                        'productUrl' => $productUrl,
                                        'formatMoney' => $formatMoney,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                <section class="storefront-benefits" aria-labelledby="storefront-benefits-title">
                    <div class="container">
                        <div class="storefront-benefits__panel">
                            <div class="storefront-benefits__layout">
                                <div class="storefront-benefits__message">
                                    <h2 id="storefront-benefits-title">Купувати просто</h2>
                                    <p>Швидке оформлення, актуальна наявність і безкоштовна доставка від <span class="storefront-inline-price">1200 грн</span>.</p>
                                    <a href="{{ url('/catalog') }}" class="storefront-benefits__cta">
                                        До каталогу
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                                    </a>
                                </div>

                                <div class="storefront-benefits__list">
                                    <article class="storefront-benefit-card">
                                        <span class="storefront-benefits__icon" aria-hidden="true">
                                            <svg viewBox="0 0 48 48"><path d="M8.5 15.5h22v15h-22z"/><path d="M30.5 20h6.2l3.8 5.3v5.2h-10z"/><path d="M13.8 34.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M35.2 34.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M13 20h8"/><path d="M34 22.5h2.2"/></svg>
                                        </span>
                                        <div class="storefront-benefit-card__body">
                                            <h3>Швидке оформлення</h3>
                                            <p>Без зайвих полів: швидше до підтвердження замовлення.</p>
                                        </div>
                                    </article>
                                    <article class="storefront-benefit-card">
                                        <span class="storefront-benefits__icon" aria-hidden="true">
                                            <svg viewBox="0 0 48 48"><path d="M10 15.5h28a3 3 0 0 1 3 3v15a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3v-15a3 3 0 0 1 3-3z"/><path d="M7 22h34"/><path d="M13 30h8"/><path d="M29 30h6"/><path d="M35.5 18.5h1.5"/></svg>
                                        </span>
                                        <div class="storefront-benefit-card__body">
                                            <h3>Безкоштовна доставка</h3>
                                            <p>Доставка за наш рахунок для замовлень від <span class="storefront-inline-price">1200 грн</span>.</p>
                                        </div>
                                    </article>
                                    <article class="storefront-benefit-card">
                                        <span class="storefront-benefits__icon" aria-hidden="true">
                                            <svg viewBox="0 0 48 48"><path d="M11 21h26v18H11z"/><path d="M9 15h30v6H9z"/><path d="M24 15v24"/><path d="M18.5 15c-2.8-2.4-3.6-5.6-1.8-7 2.2-1.7 5.5.8 7.3 7"/><path d="M29.5 15c2.8-2.4 3.6-5.6 1.8-7-2.2-1.7-5.5.8-7.3 7"/></svg>
                                        </span>
                                        <div class="storefront-benefit-card__body">
                                            <h3>Актуальний каталог</h3>
                                            <p>Показуємо лише активні товари з ціною та наявністю.</p>
                                        </div>
                                    </article>
                                    <article class="storefront-benefit-card">
                                        <span class="storefront-benefits__icon" aria-hidden="true">
                                            <svg viewBox="0 0 48 48"><path d="M34.5 10.5c-11.8.8-20.5 8.2-20.5 18.2 0 5.2 3.5 8.8 8.4 8.8 9 0 14.6-9.2 12.1-27z"/><path d="M14.5 36.5c4.2-8 10.8-13.2 18.5-16"/><path d="M17.5 25.5c-2.7-1.8-4.8-4.4-6-7.7"/><path d="M21.5 31.5c-4 .1-7.4 1.2-10 3.2"/></svg>
                                        </span>
                                        <div class="storefront-benefit-card__body">
                                            <h3>Швидкий сайт</h3>
                                            <p>Легка сторінка швидко відкривається й веде до каталогу.</p>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="storefront-product-faq storefront-home-faq" aria-labelledby="home-faq-title">
                    <div class="container">
                        <div class="storefront-home-faq__layout">
                            <div class="storefront-section-heading storefront-product-faq__heading storefront-home-faq__heading">
                                <p class="storefront-section-eyebrow">FAQ</p>
                                <h2 id="home-faq-title">Що нас питають найчастіше?</h2>
                            </div>

                            <div class="storefront-product-faq__list storefront-home-faq__list">
                                @foreach ($homeFaqItems as $item)
                                    <details class="storefront-product-faq__item">
                                        <summary>
                                            <span>{{ $item['question'] }}</span>
                                        </summary>
                                        <div class="storefront-product-faq__answer">
                                            <p>{!! $item['answer_html'] ?? e($item['answer']) !!}</p>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="storefront-seo-section">
                    <div class="container">
                        <div class="storefront-seo-card">
                            <div>
                                <h2>{{ $storeName }} - онлайн-магазин товарів для дому</h2>
                            </div>
                            <div>
                                <p>
                                    Обирайте мʼякі домашні тапочки, моделі для коротких виходів і піжами для спокійних вечорів.
                                    На головній зібрані актуальні добірки, категорії та популярні товари з фото, цінами й наявністю.
                                </p>
                                <p>
                                    Переходьте в каталог, порівнюйте моделі та швидко знаходьте речі для себе або подарунка.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="storefront-footer">
                <div class="container">
                    <div class="storefront-footer__grid">
                        <div class="storefront-footer__brand">
                            <a href="{{ route('home') }}" class="storefront-footer__logo" aria-label="{{ $storeName }} - головна">
                                <img src="{{ asset('brand/dom-mood-stacked.png') }}" alt="{{ $storeName }}" width="230" height="120">
                            </a>
                            <p class="storefront-footer__copyright">{{ $storeName }}© {{ now()->year }}</p>
                        </div>

                        <nav class="storefront-footer__menu" aria-label="Footer меню">
                            @foreach ($footerMenuItems ?? [] as $item)
                                @php
                                    $target = $item['target'] ?? '_self';
                                    $children = $item['children'] ?? [];
                                @endphp

                                @if (count($children) > 0)
                                    <h2>{{ $item['title'] }}</h2>
                                    @foreach ($children as $child)
                                        @php($childTarget = $child['target'] ?? '_self')
                                        <a
                                            href="{{ $child['url'] }}"
                                            target="{{ $childTarget }}"
                                            @if ($childTarget === '_blank') rel="noopener" @endif
                                        >
                                            {{ $child['title'] }}
                                        </a>
                                    @endforeach
                                @else
                                    <a
                                        href="{{ $item['url'] }}"
                                        target="{{ $target }}"
                                        @if ($target === '_blank') rel="noopener" @endif
                                    >
                                        {{ $item['title'] }}
                                    </a>
                                @endif
                            @endforeach
                        </nav>

                        <nav class="storefront-footer__menu" aria-labelledby="storefront-footer-clients">
                            <h2 id="storefront-footer-clients">Клієнтам</h2>
                            @foreach ($footerClientLinks as $item)
                                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                            @endforeach
                        </nav>

                        <div class="storefront-footer__contacts" aria-labelledby="storefront-footer-contacts">
                            <h2 id="storefront-footer-contacts">Контактна інформація</h2>

                            <a href="tel:{{ $cleanFooterPhone }}" class="storefront-footer__contact-row">
                                <span class="storefront-footer__contact-icon storefront-footer__contact-icon--callback" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 3v5.5M12 15.5V21M3 12h5.5M15.5 12H21M6.4 6.4l3.9 3.9M13.7 13.7l3.9 3.9M17.6 6.4l-3.9 3.9M10.3 13.7l-3.9 3.9"/></svg>
                                </span>
                                <span>{{ $footerPhone }}</span>
                            </a>
                            <span class="storefront-footer__callback-text">Передзвонити вам?</span>

                            <a href="viber://chat?number={{ rawurlencode($cleanFooterPhone) }}" class="storefront-footer__contact-row">
                                <span class="storefront-footer__contact-icon" aria-hidden="true">
                                    <img src="{{ asset('brand/icons/viber.svg') }}" alt="" width="19" height="19">
                                </span>
                                <span>{{ $footerPhone }}</span>
                            </a>
                            <a href="https://wa.me/{{ $messengerFooterPhone }}" class="storefront-footer__contact-row">
                                <span class="storefront-footer__contact-icon" aria-hidden="true">
                                    <img src="{{ asset('brand/icons/whatsapp.svg') }}" alt="" width="19" height="19">
                                </span>
                                <span>{{ $footerPhone }}</span>
                            </a>
                            <a href="tg://resolve?phone={{ $messengerFooterPhone }}" class="storefront-footer__contact-row">
                                <span class="storefront-footer__contact-icon" aria-hidden="true">
                                    <img src="{{ asset('brand/icons/telegram.svg') }}" alt="" width="19" height="19">
                                </span>
                                <span>{{ $footerPhone }}</span>
                            </a>
                            <a href="mailto:{{ $footerEmail }}" class="storefront-footer__contact-row">
                                <span class="storefront-footer__contact-icon storefront-footer__contact-icon--muted" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/></svg>
                                </span>
                                <span>{{ $footerEmail }}</span>
                            </a>

                            <div class="storefront-footer__socials">
                                <span>Ми в соцмережах</span>
                                <div>
                                    @foreach ($footerSocialLinks as $social)
                                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ $social['title'] }}">
                                            <img src="{{ asset('brand/icons/'.$social['icon'].'.svg') }}" alt="" width="29" height="29">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="storefront-footer__address">
                            <span class="storefront-footer__address-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-13a7 7 0 1 0-14 0c0 6.9 7 13 7 13Z"/><circle cx="12" cy="9" r="2.4"/></svg>
                            </span>
                            <div>
                                <p>{{ $footerAddress }}</p>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($footerAddress) }}" target="_blank" rel="noopener">
                                    Мапа проїзду
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.cart-drawer-scripts')

        <script>
            (() => {
                const menu = document.querySelector('[data-mobile-menu]');
                const openButton = document.querySelector('[data-mobile-menu-open]');
                const closeButtons = document.querySelectorAll('[data-mobile-menu-close]');
                const searchPanel = document.querySelector('[data-mobile-search]');
                const searchOpenButton = document.querySelector('[data-mobile-search-open]');
                const searchCloseButton = document.querySelector('[data-mobile-search-close]');
                const submenuButtons = document.querySelectorAll('[data-mobile-submenu-toggle]');
                const desktopSubmenuLinks = document.querySelectorAll('[data-desktop-submenu-toggle]');
                const categoryCollage = document.querySelector('[data-category-collage]');
                const featuredCarousels = document.querySelectorAll('[data-featured-carousel]');

                const setPanelInert = (panel, isHidden) => {
                    panel.toggleAttribute('inert', isHidden);
                    panel.inert = isHidden;
                };

                const moveFocusOutside = (panel, fallbackButton) => {
                    if (!panel.contains(document.activeElement)) {
                        return;
                    }

                    if (fallbackButton) {
                        fallbackButton.focus({ preventScroll: true });
                        return;
                    }

                    document.activeElement?.blur();
                };

                const setMenuState = (isOpen) => {
                    if (!menu) {
                        return;
                    }

                    if (isOpen) {
                        setPanelInert(menu, false);
                    } else {
                        moveFocusOutside(menu, openButton);
                    }

                    menu.classList.toggle('is-open', isOpen);
                    menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    setPanelInert(menu, !isOpen);
                    document.body.classList.toggle('storefront-menu-open', isOpen);
                };

                const setSearchState = (isOpen) => {
                    if (!searchPanel) {
                        return;
                    }

                    if (isOpen) {
                        setPanelInert(searchPanel, false);
                    } else {
                        moveFocusOutside(searchPanel, searchOpenButton);
                    }

                    searchPanel.classList.toggle('is-open', isOpen);
                    searchPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    setPanelInert(searchPanel, !isOpen);
                    document.body.classList.toggle('storefront-menu-open', isOpen);

                    if (isOpen) {
                        searchPanel.querySelector('input')?.focus();
                    }
                };

                openButton?.addEventListener('click', () => setMenuState(true));
                closeButtons.forEach((button) => button.addEventListener('click', () => setMenuState(false)));
                menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuState(false)));

                searchOpenButton?.addEventListener('click', () => setSearchState(true));
                searchCloseButton?.addEventListener('click', () => setSearchState(false));

                const closeDesktopSubmenus = (exceptItem = null) => {
                    desktopSubmenuLinks.forEach((link) => {
                        const item = link.closest('.storefront-desktop-menu__item');

                        if (!item || item === exceptItem) {
                            return;
                        }

                        item.classList.remove('is-open');
                        link.setAttribute('aria-expanded', 'false');
                    });
                };

                desktopSubmenuLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        const item = link.closest('.storefront-desktop-menu__item');

                        if (!item) {
                            return;
                        }

                        event.preventDefault();

                        const isExpanded = link.getAttribute('aria-expanded') === 'true';

                        closeDesktopSubmenus(item);
                        item.classList.toggle('is-open', !isExpanded);
                        link.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!event.target.closest('.storefront-category-nav')) {
                        closeDesktopSubmenus();
                    }
                });

                submenuButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const isExpanded = button.getAttribute('aria-expanded') === 'true';
                        const panel = button.nextElementSibling;

                        button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                        panel?.classList.toggle('is-open', !isExpanded);
                    });
                });

                if (categoryCollage) {
                    let isDraggingCollage = false;
                    let dragStartX = 0;
                    let dragStartScroll = 0;
                    let hasDraggedCollage = false;
                    let collagePointerId = null;

                    categoryCollage.addEventListener('pointerdown', (event) => {
                        hasDraggedCollage = false;

                        if (event.pointerType === 'touch' || event.target.closest('a')) {
                            return;
                        }

                        isDraggingCollage = true;
                        collagePointerId = event.pointerId;
                        dragStartX = event.clientX;
                        dragStartScroll = categoryCollage.scrollLeft;
                        categoryCollage.classList.add('is-dragging');

                        if (categoryCollage.hasPointerCapture?.(event.pointerId) === false) {
                            categoryCollage.setPointerCapture(event.pointerId);
                        }
                    });

                    categoryCollage.addEventListener('pointermove', (event) => {
                        if (!isDraggingCollage || event.pointerId !== collagePointerId) {
                            return;
                        }

                        const dragDistance = event.clientX - dragStartX;
                        hasDraggedCollage = Math.abs(dragDistance) > 10;
                        categoryCollage.scrollLeft = dragStartScroll - dragDistance;
                    });

                    const stopCategoryDrag = (event) => {
                        if (!isDraggingCollage || event.pointerId !== collagePointerId) {
                            return;
                        }

                        isDraggingCollage = false;
                        collagePointerId = null;
                        categoryCollage.classList.remove('is-dragging');

                        if (categoryCollage.hasPointerCapture?.(event.pointerId)) {
                            categoryCollage.releasePointerCapture(event.pointerId);
                        }
                    };

                    categoryCollage.addEventListener('pointerup', stopCategoryDrag);
                    categoryCollage.addEventListener('pointercancel', stopCategoryDrag);
                    categoryCollage.addEventListener('pointerleave', stopCategoryDrag);

                    categoryCollage.addEventListener('click', (event) => {
                        if (event.target.closest('a')) {
                            return;
                        }

                        if (hasDraggedCollage) {
                            event.preventDefault();
                            event.stopPropagation();
                            hasDraggedCollage = false;
                        }
                    });
                }

                featuredCarousels.forEach((carousel) => {
                    const track = carousel.querySelector('[data-featured-carousel-track]');
                    const prev = carousel.querySelector('[data-featured-carousel-prev]');
                    const next = carousel.querySelector('[data-featured-carousel-next]');

                    const scrollFeaturedCarousel = (direction) => {
                        if (!track) {
                            return;
                        }

                        track.scrollBy({
                            left: direction * track.clientWidth * 0.82,
                            behavior: 'smooth',
                        });
                    };

                    prev?.addEventListener('click', () => scrollFeaturedCarousel(-1));
                    next?.addEventListener('click', () => scrollFeaturedCarousel(1));
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        setMenuState(false);
                        setSearchState(false);
                        closeDesktopSubmenus();
                    }
                });
            })();
        </script>

        <script type="application/ld+json">
            {!! json_encode($schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </body>
</html>
