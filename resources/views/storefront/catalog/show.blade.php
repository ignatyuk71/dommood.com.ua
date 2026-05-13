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
        <div class="storefront-page storefront-product-page">
            @include('storefront.partials.site-header')

            <main></main>

            @include('storefront.partials.site-footer')
        </div>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-feedback')
        @include('storefront.partials.storefront-ui-scripts')
        @include('storefront.partials.cart-drawer-scripts')

        @foreach (collect($schemas ?? [])->filter() as $schema)
            <script type="application/ld+json">
                {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
            </script>
        @endforeach
    </body>
</html>
