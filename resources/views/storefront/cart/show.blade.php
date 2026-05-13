<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>Кошик - {{ $storeName }}</title>
        @if (file_exists(public_path('hot')))
            @vite('resources/css/storefront.css')
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
        @endif
    </head>
    <body>
        <div class="storefront-page storefront-checkout-page">
            <header class="storefront-checkout-topbar">
                <a href="{{ route('home') }}" class="storefront-checkout-logo" aria-label="{{ $storeName }} - головна">
                    <img src="{{ asset('brand/dom-mood-wordmark-black.png') }}" alt="{{ $storeName }}" width="168" height="28">
                </a>
                <nav aria-label="Checkout кроки">
                    <span class="is-active">Кошик</span>
                    <span>Checkout</span>
                    <span>Підтвердження</span>
                </nav>
            </header>

            <main class="storefront-cart-page" data-cart-shell>
                @include('storefront.partials.cart-drawer', [
                    'cart' => $cart,
                    'recommendedProducts' => $recommendedProducts,
                    'drawerOpen' => true,
                    'cartPage' => true,
                ])
            </main>
        </div>

        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.cart-drawer-scripts')
    </body>
</html>
