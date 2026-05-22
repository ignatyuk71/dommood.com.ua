@php
    $hasDiscount = (int) ($product['old_price_cents'] ?? 0) > (int) ($product['price_cents'] ?? 0);
    $cardDescription = trim((string) ($product['short_description'] ?? ''));
    $availabilityBadge = $product['availability_badge'] ?? null;

    if ($cardDescription === '') {
        $cardDescription = trim(strip_tags((string) ($product['description'] ?? '')));
    }

    $cardDescription = \Illuminate\Support\Str::limit($cardDescription, 130);
    $analyticsItem = \App\Support\Storefront\EcommerceAnalytics::productCard($product);
@endphp

<article
    @class([
        'storefront-product-card',
        'is-out-of-stock' => ($availabilityBadge['status'] ?? null) === 'out_of_stock',
        'is-preorder' => ($availabilityBadge['status'] ?? null) === 'preorder',
    ])
    data-product-id="{{ $product['id'] }}"
    data-analytics-product-card
    data-analytics-item="{{ json_encode($analyticsItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
>
    <a href="{{ $productUrl($product) }}" class="storefront-product-card__media">
        @if ($product['image_url'])
            <img src="{{ $product['image_url'] }}" alt="{{ $product['image_alt'] ?: $product['name'] }}" loading="lazy">
        @else
            <span class="storefront-image-placeholder">{{ mb_substr($product['name'] ?: 'DM', 0, 2) }}</span>
        @endif

        <span class="storefront-product-badges">
            @if ($product['is_new'])
                <span class="is-new">Новинка</span>
            @endif
            @if ($product['is_bestseller'])
                <span class="is-hit">Хіт</span>
            @endif
            @if ($product['is_featured'])
                <span class="is-top">Топ</span>
            @endif
            @if ($availabilityBadge)
                <span class="is-availability is-{{ $availabilityBadge['tone'] ?? 'info' }}">{{ $availabilityBadge['label'] }}</span>
            @endif
        </span>
    </a>

    <div class="storefront-product-card__body">
        <div>
            @if ($product['sku'])
                <span class="storefront-product-card__sku">Артикул: {{ $product['sku'] }}</span>
            @endif
            <h3>
                <a href="{{ $productUrl($product) }}">{{ $product['name'] }}</a>
            </h3>
        </div>

        @if ($cardDescription !== '')
            <p>{{ $cardDescription }}</p>
        @endif

        <div class="storefront-product-card__footer">
            <div class="storefront-product-price">
                <span>{{ $formatMoney($product['price_cents'], $product['currency']) }}</span>
                @if ($hasDiscount)
                    <del>{{ $formatMoney($product['old_price_cents'], $product['currency']) }}</del>
                @endif
            </div>
            <span class="storefront-stock @if ($product['stock_status'] === 'out_of_stock') is-muted @elseif ($product['stock_status'] === 'preorder') is-warning @endif">
                {{ $product['stock_status_label'] }}
            </span>
        </div>

    </div>
</article>
