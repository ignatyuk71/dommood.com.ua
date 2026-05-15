<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] ?? $page->title }}</title>
        <meta name="description" content="{{ $seo['meta_description'] ?? ($page->meta_description ?: $page->title) }}">
        <link rel="canonical" href="{{ $seo['canonical_url'] ?? url('/'.$page->slug) }}">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#29277f">
        @if (file_exists(public_path('hot')))
            @vite('resources/css/storefront.css')
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
        @endif
    </head>
    <body>
        @php
            $widePageSlugs = [
                'polityka-konfidentsiinosti',
                'bezkoshtovne-povernennia-novoiu-poshtoiu',
                'uhoda-korystuvacha',
                'kontakty',
                'obmin-ta-povernennya',
                'oplata-i-dostavka',
                'pro-nas',
            ];
            $utilityLinks = $utilityLinks ?? [];
            $menuItems = $menuItems ?? [];
            $mobileNavigationItems = count($mobileMenuItems ?? []) > 0 ? $mobileMenuItems : $menuItems;
            $headerPhone = $supportPhone ?: '+380679753512';
            $cleanHeaderPhone = preg_replace('/[^0-9+]/', '', $headerPhone);
            $messengerPhone = ltrim($cleanHeaderPhone, '+');
            $footerPhone = $supportPhone ?: '+380679753512';
            $cleanFooterPhone = preg_replace('/[^0-9+]/', '', $footerPhone);
            $messengerFooterPhone = ltrim($cleanFooterPhone, '+');
            $footerEmail = $supportEmail ?: 'dommood.com.ua@gmail.com';
            $breadcrumbs = [
                ['label' => 'Головна', 'url' => route('home')],
                ['label' => $page->title],
            ];
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
        @endphp

        <div class="storefront-page storefront-content-page">
            <header class="storefront-site-header">
                <div class="storefront-desktop-header">
                    <div class="storefront-utility-bar">
                        <div @class(['container', 'is-utility-compact' => count($utilityLinks) === 0])>
                            @if (count($utilityLinks) > 0)
                                <nav class="storefront-utility-menu" aria-label="Сервісне меню">
                                    @foreach ($utilityLinks as $link)
                                        @php
                                            $target = $link['target'] ?? '_self';
                                        @endphp
                                        <a
                                            href="{{ $link['url'] }}"
                                            target="{{ $target }}"
                                            @if ($target === '_blank') rel="noopener" @endif
                                        >
                                            {{ $link['title'] }}
                                        </a>
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
                                            <a href="viber://chat?number={{ rawurlencode($cleanHeaderPhone) }}" class="storefront-messenger-link is-viber" aria-label="Viber" title="Viber">
                                                <img src="{{ asset('brand/icons/viber.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="tg://resolve?phone={{ $messengerPhone }}" class="storefront-messenger-link is-telegram" aria-label="Telegram" title="Telegram">
                                                <img src="{{ asset('brand/icons/telegram.svg') }}" alt="" width="24" height="24">
                                            </a>
                                            <a href="https://wa.me/{{ $messengerPhone }}" class="storefront-messenger-link is-whatsapp" aria-label="WhatsApp" title="WhatsApp">
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

                                <a href="{{ route('home') }}" class="storefront-desktop-logo" aria-label="{{ $storeName }} - головна">
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
                        <a href="{{ route('home') }}" class="storefront-mobile-logo" aria-label="{{ $storeName }} - головна">
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
                    </aside>
                </div>
            </header>

            <main class="storefront-content-page__main">
                <article @class([
                    'container',
                    'storefront-content-page__container--wide' => in_array($page->slug, $widePageSlugs, true),
                ])>
                    @include('storefront.partials.breadcrumbs', ['items' => $breadcrumbs])

                    <h1>{{ $page->title }}</h1>

                    <div class="storefront-content-page__body">
                        {!! $page->content ?: '<p>Сторінка готується до публікації.</p>' !!}
                    </div>
                </article>
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

                const setPanelInert = (panel, isHidden) => {
                    panel.toggleAttribute('inert', isHidden);
                    panel.inert = isHidden;
                };

                const setMenuState = (isOpen) => {
                    if (!menu) {
                        return;
                    }

                    menu.classList.toggle('is-open', isOpen);
                    menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    setPanelInert(menu, !isOpen);
                    document.body.classList.toggle('storefront-menu-open', isOpen);

                    if (!isOpen && menu.contains(document.activeElement)) {
                        openButton?.focus({ preventScroll: true });
                    }
                };

                const setSearchState = (isOpen) => {
                    if (!searchPanel) {
                        return;
                    }

                    searchPanel.classList.toggle('is-open', isOpen);
                    searchPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                    setPanelInert(searchPanel, !isOpen);
                    document.body.classList.toggle('storefront-menu-open', isOpen);

                    if (isOpen) {
                        searchPanel.querySelector('input')?.focus();
                    } else if (searchPanel.contains(document.activeElement)) {
                        searchOpenButton?.focus({ preventScroll: true });
                    }
                };

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

                openButton?.addEventListener('click', () => setMenuState(true));
                closeButtons.forEach((button) => button.addEventListener('click', () => setMenuState(false)));
                menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuState(false)));
                searchOpenButton?.addEventListener('click', () => setSearchState(true));
                searchCloseButton?.addEventListener('click', () => setSearchState(false));

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
            })();
        </script>
    </body>
</html>
