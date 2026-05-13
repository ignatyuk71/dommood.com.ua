@php
    $utilityLinks = $utilityLinks ?? [];
    $menuItems = $menuItems ?? [];
    $mobileNavigationItems = count($mobileMenuItems ?? []) > 0 ? $mobileMenuItems : $menuItems;
    $headerPhone = $supportPhone ?: '+380679753512';
    $cleanHeaderPhone = preg_replace('/[^0-9+]/', '', $headerPhone);
    $messengerPhone = ltrim($cleanHeaderPhone, '+');
@endphp

<header class="storefront-site-header">
    <div class="storefront-desktop-header">
        <div class="storefront-utility-bar">
            <div @class(['container', 'is-utility-compact' => count($utilityLinks) === 0])>
                @if (count($utilityLinks) > 0)
                    <nav class="storefront-utility-menu" aria-label="Сервісне меню">
                        @foreach ($utilityLinks as $link)
                            @php($target = $link['target'] ?? '_self')
                            <a href="{{ $link['url'] }}" target="{{ $target }}" @if ($target === '_blank') rel="noopener" @endif>
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
                            <input type="search" name="q" value="{{ $query ?? '' }}" placeholder="пошук товарів" aria-label="Пошук товарів">
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
            <input type="search" name="q" value="{{ $query ?? '' }}" placeholder="Пошук в каталозі" aria-label="Пошук в каталозі">
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
