@php
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
@endphp

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
                            <a href="{{ $child['url'] }}" target="{{ $childTarget }}" @if ($childTarget === '_blank') rel="noopener" @endif>
                                {{ $child['title'] }}
                            </a>
                        @endforeach
                    @else
                        <a href="{{ $item['url'] }}" target="{{ $target }}" @if ($target === '_blank') rel="noopener" @endif>
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
