@php
    $hasDiscount = (int) ($product['old_price_cents'] ?? 0) > (int) ($product['price_cents'] ?? 0);
@endphp

<article class="storefront-product-card" data-product-id="{{ $product['id'] }}">
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
