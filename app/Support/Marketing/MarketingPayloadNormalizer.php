<?php

namespace App\Support\Marketing;

use App\Models\Order;
use App\Models\OrderItem;

class MarketingPayloadNormalizer
{
    public function ecommerce(array $payload): array
    {
        $items = $this->items($payload['items'] ?? $payload['contents'] ?? []);
        $contentIds = $this->contentIds($payload, $items);
        $value = $this->money($payload['value'] ?? null);

        if ($value === null && $items !== []) {
            $value = round(array_reduce($items, static function (float $sum, array $item): float {
                return $sum + ((float) ($item['item_price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)));
            }, 0.0), 2);
        }

        return $this->clean([
            'currency' => $this->currency($payload['currency'] ?? 'UAH'),
            'value' => $value,
            'value_cents' => $value !== null ? (int) round($value * 100) : null,
            'shipping' => $this->money($payload['shipping'] ?? null),
            'content_type' => $items !== [] ? 'product' : null,
            'content_ids' => $contentIds,
            'content_name' => $payload['content_name'] ?? ($items[0]['name'] ?? null),
            'contents' => $items,
            'num_items' => $items !== [] ? array_sum(array_map(static fn (array $item): int => max(1, (int) ($item['quantity'] ?? 1)), $items)) : null,
            'transaction_id' => $this->text($payload['transaction_id'] ?? null),
            'event_id' => $this->text($payload['event_id'] ?? null),
            'source_channel' => $this->text($payload['source_channel'] ?? null),
            'shipping_tier' => $this->text($payload['shipping_tier'] ?? null),
            'payment_type' => $this->text($payload['payment_type'] ?? null),
        ]);
    }

    public function order(Order $order): array
    {
        $order->loadMissing('items');

        $items = $order->items
            ->values()
            ->map(fn (OrderItem $item): array => [
                'item_id' => (string) (($item->sku ?: null) ?: $item->product_id),
                'item_name' => (string) $item->product_name,
                'item_variant' => $item->variant_name,
                'price' => round(((int) $item->price_cents) / 100, 2),
                'quantity' => (int) $item->quantity,
            ])
            ->all();

        return $this->ecommerce([
            'currency' => $order->currency ?: 'UAH',
            'value' => round(max(0, (int) $order->total_cents - (int) $order->delivery_price_cents) / 100, 2),
            'shipping' => round(((int) $order->delivery_price_cents) / 100, 2),
            'transaction_id' => (string) $order->order_number,
            'event_id' => 'order_'.$order->order_number,
            'source_channel' => $order->channel,
            'shipping_tier' => $order->delivery_method,
            'payment_type' => $order->payment_method,
            'items' => $items,
        ]);
    }

    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $this->text($item['id'] ?? $item['content_id'] ?? $item['item_id'] ?? null);
            if ($id === null) {
                continue;
            }

            $result[] = $this->clean([
                'id' => $id,
                'name' => $this->text($item['name'] ?? $item['item_name'] ?? $item['content_name'] ?? null),
                'quantity' => max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1)),
                'item_price' => $this->money($item['item_price'] ?? $item['price'] ?? null),
            ]);
        }

        return $result;
    }

    private function contentIds(array $payload, array $items): array
    {
        $explicit = $payload['content_ids'] ?? null;
        $values = is_array($explicit) ? $explicit : ($explicit ? [$explicit] : []);
        $ids = array_values(array_filter(array_map(fn (mixed $value): ?string => $this->text($value), $values)));

        if ($ids === []) {
            $ids = array_values(array_filter(array_map(
                static fn (array $item): ?string => isset($item['id']) ? (string) $item['id'] : null,
                $items
            )));
        }

        return array_values(array_unique($ids));
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'UAH';
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? round($number, 2) : null;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function clean(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
