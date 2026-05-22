<?php

namespace App\Support\Storefront;

use App\Models\Order;
use App\Models\OrderItem;

class EcommerceAnalytics
{
    public static function cart(array $cart, ?int $shippingCents = null, ?string $shippingTier = null, ?string $paymentType = null): array
    {
        $payload = [
            'currency' => self::currency($cart['currency'] ?? 'UAH'),
            'value' => self::money((int) ($cart['total_cents'] ?? 0)),
            'items' => collect($cart['items'] ?? [])
                ->values()
                ->map(fn (array $item, int $index): array => self::cartItem($item, $index))
                ->all(),
        ];

        if (filled($cart['promocode_code'] ?? null)) {
            $payload['coupon'] = (string) $cart['promocode_code'];
        }

        if ($shippingCents !== null) {
            $payload['shipping'] = self::money($shippingCents);
        }

        if (filled($shippingTier)) {
            $payload['shipping_tier'] = $shippingTier;
        }

        if (filled($paymentType)) {
            $payload['payment_type'] = $paymentType;
        }

        return $payload;
    }

    public static function cartItem(array $item, int $index = 0, ?int $quantity = null): array
    {
        return self::clean([
            'item_id' => (string) (($item['sku'] ?? null) ?: ($item['product_id'] ?? '')),
            'item_name' => (string) ($item['name'] ?? 'Товар'),
            'item_category' => $item['category_name'] ?? null,
            'item_variant' => $item['variant_name'] ?? null,
            'price' => self::money((int) ($item['price_cents'] ?? 0)),
            'quantity' => $quantity ?? (int) ($item['quantity'] ?? 1),
            'index' => $index,
        ]);
    }

    public static function productCard(array $product, int $index = 0): array
    {
        return self::clean([
            'item_id' => (string) (($product['sku'] ?? null) ?: ($product['id'] ?? '')),
            'item_name' => (string) ($product['name'] ?? 'Товар'),
            'item_brand' => $product['brand']['name'] ?? null,
            'item_category' => $product['category']['name'] ?? null,
            'price' => self::money((int) ($product['price_cents'] ?? 0)),
            'quantity' => 1,
            'index' => $index,
        ]);
    }

    public static function purchase(Order $order): array
    {
        $order->loadMissing('items');
        $itemsValueCents = max(0, (int) $order->total_cents - (int) $order->delivery_price_cents);
        $payload = [
            'transaction_id' => (string) $order->order_number,
            'value' => self::money($itemsValueCents),
            'shipping' => self::money((int) $order->delivery_price_cents),
            'currency' => self::currency($order->currency ?: 'UAH'),
            'items' => $order->items
                ->values()
                ->map(fn (OrderItem $item, int $index): array => self::orderItem($item, $index))
                ->all(),
        ];

        if (filled($order->promocode_code)) {
            $payload['coupon'] = (string) $order->promocode_code;
        }

        if (filled($order->delivery_method)) {
            $payload['shipping_tier'] = (string) $order->delivery_method;
        }

        if (filled($order->payment_method)) {
            $payload['payment_type'] = (string) $order->payment_method;
        }

        return $payload;
    }

    private static function orderItem(OrderItem $item, int $index): array
    {
        $snapshot = $item->product_snapshot ?? [];

        return self::clean([
            'item_id' => (string) (($item->sku ?: null) ?: $item->product_id),
            'item_name' => (string) $item->product_name,
            'item_category' => $snapshot['category_name'] ?? null,
            'item_variant' => $item->variant_name,
            'price' => self::money((int) $item->price_cents),
            'quantity' => (int) $item->quantity,
            'index' => $index,
        ]);
    }

    private static function money(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return strlen($currency) === 3 ? $currency : 'UAH';
    }

    private static function clean(array $payload): array
    {
        return array_filter($payload, fn ($value): bool => $value !== null && $value !== '');
    }
}
