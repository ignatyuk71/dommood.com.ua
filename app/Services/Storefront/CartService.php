<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Promocode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const SESSION_KEY = 'storefront_cart_token';

    private const FALLBACK_FREE_SHIPPING_THRESHOLD_CENTS = DeliveryPolicyService::DEFAULT_FREE_SHIPPING_THRESHOLD_CENTS;

    public function __construct(private readonly DeliveryPolicyService $deliveryPolicy) {}

    public function current(Request $request): Cart
    {
        $cart = $this->findCurrent($request);

        if ($cart) {
            return $cart;
        }

        $cart = Cart::query()->create([
            'token' => (string) Str::uuid(),
            'customer_id' => $request->user()?->customer?->id,
            'session_id' => $request->session()->getId(),
            'status' => 'active',
            'currency' => 'UAH',
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
            'expires_at' => now()->addDays(30),
        ]);

        $request->session()->put(self::SESSION_KEY, $cart->token);

        return $cart;
    }

    public function findCurrent(Request $request): ?Cart
    {
        $token = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($token === '') {
            return null;
        }

        return Cart::query()
            ->where('token', $token)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function addProduct(Request $request, int $productId, ?int $variantId = null, int $quantity = 1): Cart
    {
        $cart = $this->current($request);
        $quantity = max(1, min(99, $quantity));
        $product = $this->activeProduct($productId);
        $variant = $this->resolveVariant($product, $variantId);

        if ($product->stock_status === Product::STOCK_OUT_OF_STOCK) {
            throw ValidationException::withMessages([
                'product_id' => 'Товар зараз недоступний для додавання в кошик.',
            ]);
        }

        $priceCents = (int) ($variant?->price_cents ?: $product->price_cents);
        $existingItem = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->when($variant, fn (Builder $query) => $query->where('product_variant_id', $variant->id), fn (Builder $query) => $query->whereNull('product_variant_id'))
            ->first();

        if ($existingItem) {
            $existingItem->quantity = min(99, (int) $existingItem->quantity + $quantity);
            $existingItem->price_cents = $priceCents;
            $existingItem->total_cents = $existingItem->quantity * $priceCents;
            $existingItem->product_snapshot = $this->snapshot($product, $variant);
            $existingItem->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'price_cents' => $priceCents,
                'total_cents' => $quantity * $priceCents,
                'product_snapshot' => $this->snapshot($product, $variant),
            ]);
        }

        return $this->recalculate($cart);
    }

    public function updateQuantity(Cart $cart, int $itemId, int $quantity): Cart
    {
        return $this->updateItem($cart, $itemId, $quantity);
    }

    public function updateItem(Cart $cart, int $itemId, ?int $quantity = null, ?int $variantId = null, bool $changeVariant = false): Cart
    {
        $item = $cart->items()->whereKey($itemId)->firstOrFail();
        $quantity = $quantity === null ? (int) $item->quantity : $quantity;

        if ($quantity <= 0) {
            $item->delete();

            return $this->recalculate($cart);
        }

        $product = $this->activeProduct((int) $item->product_id);
        $variant = $changeVariant
            ? $this->resolveVariant($product, $variantId)
            : $product->variants->firstWhere('id', $item->product_variant_id);
        $priceCents = (int) ($variant?->price_cents ?: $product->price_cents);
        $quantity = min(99, $quantity);

        if ($changeVariant) {
            $existingItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('id', '!=', $item->id)
                ->when($variant, fn (Builder $query) => $query->where('product_variant_id', $variant->id), fn (Builder $query) => $query->whereNull('product_variant_id'))
                ->first();

            if ($existingItem) {
                $existingItem->quantity = min(99, (int) $existingItem->quantity + $quantity);
                $existingItem->price_cents = $priceCents;
                $existingItem->total_cents = $existingItem->quantity * $priceCents;
                $existingItem->product_snapshot = $this->snapshot($product, $variant);
                $existingItem->save();
                $item->delete();

                return $this->recalculate($cart);
            }
        }

        $item->product_variant_id = $variant?->id;
        $item->quantity = $quantity;
        $item->price_cents = $priceCents;
        $item->total_cents = $item->quantity * $priceCents;
        $item->product_snapshot = $this->snapshot($product, $variant);
        $item->save();

        return $this->recalculate($cart);
    }

    public function removeItem(Cart $cart, int $itemId): Cart
    {
        $cart->items()->whereKey($itemId)->delete();

        return $this->recalculate($cart);
    }

    public function applyPromocode(Cart $cart, string $code): Cart
    {
        $cart = $this->recalculate($cart);

        if ($cart->items()->count() === 0) {
            throw ValidationException::withMessages([
                'code' => 'Додайте товар у кошик перед застосуванням купона.',
            ]);
        }

        $promocode = $this->resolvePromocode($code);
        $subtotalCents = (int) $cart->subtotal_cents;

        if ($subtotalCents < (int) $promocode->minimum_order_cents) {
            throw ValidationException::withMessages([
                'code' => 'Мінімальна сума для цього купона: '.$this->money($promocode->minimum_order_cents).'.',
            ]);
        }

        $discountCents = $this->promocodeDiscount($promocode, $subtotalCents);

        if ($discountCents <= 0) {
            throw ValidationException::withMessages([
                'code' => 'Цей купон не дає знижку для поточного кошика.',
            ]);
        }

        $cart->forceFill([
            'promocode_code' => $this->normalizePromocode($promocode->code),
            'discount_total_cents' => $discountCents,
        ])->save();

        return $this->recalculate($cart);
    }

    public function clearPromocode(Cart $cart): Cart
    {
        $cart->forceFill([
            'promocode_code' => null,
            'discount_total_cents' => 0,
        ])->save();

        return $this->recalculate($cart);
    }

    public function recalculate(Cart $cart): Cart
    {
        $cart->load('items');

        $subtotalCents = $cart->items->sum(fn (CartItem $item): int => (int) $item->total_cents);
        $promocode = $cart->promocode_code ? $this->activePromocode((string) $cart->promocode_code) : null;
        $discountCents = $promocode
            ? $this->promocodeDiscount($promocode, $subtotalCents)
            : min((int) $cart->discount_total_cents, $subtotalCents);

        $cart->forceFill([
            'subtotal_cents' => $subtotalCents,
            'discount_total_cents' => $discountCents,
            'promocode_code' => $promocode ? $this->normalizePromocode($promocode->code) : null,
            'total_cents' => max(0, $subtotalCents - $discountCents),
            'expires_at' => now()->addDays(30),
        ])->save();

        return $cart->refresh();
    }

    public function payload(Cart $cart): array
    {
        $cart = $this->recalculate($cart)->load([
            'items.product.primaryCategory',
            'items.product.variants' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id'),
            'items.variant',
        ]);
        $items = $cart->items
            ->sortBy('id')
            ->map(fn (CartItem $item): array => $this->serializeItem($item))
            ->values()
            ->all();
        $freeShippingThresholdCents = $this->freeShippingThresholdCents();
        $freeShippingProgress = $this->freeShippingProgress((int) $cart->total_cents, $freeShippingThresholdCents);

        return [
            'id' => $cart->id,
            'token' => $cart->token,
            'currency' => $cart->currency ?: 'UAH',
            'items' => $items,
            'items_count' => count($items),
            'quantity_count' => array_sum(array_column($items, 'quantity')),
            'promocode_code' => $cart->promocode_code,
            'subtotal_cents' => (int) $cart->subtotal_cents,
            'discount_total_cents' => (int) $cart->discount_total_cents,
            'total_cents' => (int) $cart->total_cents,
            'free_shipping_threshold_cents' => $freeShippingThresholdCents,
            'free_shipping_remaining_cents' => $freeShippingProgress['remaining_cents'],
            'free_shipping_progress_percent' => $freeShippingProgress['progress_percent'],
            'free_shipping_label' => $freeShippingProgress['label'],
            'is_empty' => count($items) === 0,
        ];
    }

    public function summaryForRequest(Request $request): array
    {
        $cart = $this->findCurrent($request);

        if (! $cart) {
            return self::emptySummary();
        }

        return $this->summaryFromPayload($this->payload($cart));
    }

    public function summaryFromPayload(array $payload): array
    {
        $quantityCount = (int) ($payload['quantity_count'] ?? 0);
        $totalCents = (int) ($payload['total_cents'] ?? 0);
        $currency = (string) ($payload['currency'] ?? 'UAH');
        $isEmpty = $quantityCount <= 0 || (bool) ($payload['is_empty'] ?? false);
        $quantityLabel = $this->quantityLabel($quantityCount);
        $totalFormatted = $this->formatMoney($totalCents, $currency);
        $freeShippingThresholdCents = (int) ($payload['free_shipping_threshold_cents'] ?? $this->freeShippingThresholdCents());
        $freeShippingProgress = $this->freeShippingProgress($totalCents, $freeShippingThresholdCents);

        return [
            'items_count' => (int) ($payload['items_count'] ?? 0),
            'quantity_count' => $quantityCount,
            'badge' => $quantityCount > 99 ? '99+' : (string) $quantityCount,
            'quantity_label' => $quantityLabel,
            'total_cents' => $totalCents,
            'currency' => $currency,
            'total_formatted' => $totalFormatted,
            'free_shipping_threshold_cents' => $freeShippingThresholdCents,
            'free_shipping_remaining_cents' => $freeShippingProgress['remaining_cents'],
            'free_shipping_progress_percent' => $freeShippingProgress['progress_percent'],
            'free_shipping_label' => $freeShippingProgress['label'],
            'header_label' => $isEmpty ? '' : $totalFormatted,
            'aria_label' => $isEmpty ? 'Кошик' : $quantityLabel.' · '.$totalFormatted,
            'is_empty' => $isEmpty,
        ];
    }

    public static function emptySummary(): array
    {
        return [
            'items_count' => 0,
            'quantity_count' => 0,
            'badge' => '',
            'quantity_label' => '0 товарів',
            'total_cents' => 0,
            'currency' => 'UAH',
            'total_formatted' => '0 грн',
            'free_shipping_threshold_cents' => self::FALLBACK_FREE_SHIPPING_THRESHOLD_CENTS,
            'free_shipping_remaining_cents' => self::FALLBACK_FREE_SHIPPING_THRESHOLD_CENTS,
            'free_shipping_progress_percent' => 0,
            'free_shipping_label' => 'Додайте ще 1 200 грн, щоб отримати безкоштовну доставку',
            'header_label' => '',
            'aria_label' => 'Кошик',
            'is_empty' => true,
        ];
    }

    public function recommendedProducts(Cart $cart, int $limit = 10): array
    {
        $cartProductIds = $cart->items()->pluck('product_id')->filter()->all();

        return Product::query()
            ->select([
                'id',
                'primary_category_id',
                'name',
                'slug',
                'sku',
                'price_cents',
                'old_price_cents',
                'currency',
                'stock_status',
                'is_new',
                'is_bestseller',
                'is_featured',
                'sort_order',
                'published_at',
                'created_at',
            ])
            ->with([
                'primaryCategory:id,name,slug',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn (Builder $query) => $this->published($query))
            ->when($cartProductIds !== [], fn (Builder $query) => $query->whereNotIn('id', $cartProductIds))
            ->where('stock_status', '!=', Product::STOCK_OUT_OF_STOCK)
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_featured')
            ->ordered()
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values()
            ->all();
    }

    public function markConverted(Request $request, Cart $cart): void
    {
        $cart->forceFill([
            'status' => 'converted',
            'converted_at' => now(),
        ])->save();

        $request->session()->forget(self::SESSION_KEY);
    }

    private function activeProduct(int $productId): Product
    {
        return Product::query()
            ->with([
                'primaryCategory:id,name,slug',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn (Builder $query) => $this->published($query))
            ->findOrFail($productId);
    }

    private function resolveVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        if (! $variantId) {
            if ($product->variants->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Оберіть доступний варіант товару.',
                ]);
            }

            return null;
        }

        $variant = $product->variants->firstWhere('id', $variantId);

        if (! $variant || ! $this->variantIsAvailable($product, $variant)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Обраний варіант товару недоступний.',
            ]);
        }

        return $variant;
    }

    private function variantIsAvailable(Product $product, ProductVariant $variant): bool
    {
        return $product->stock_status !== Product::STOCK_OUT_OF_STOCK
            && (bool) $variant->is_active
            && (int) $variant->stock_quantity > 0;
    }

    private function resolvePromocode(string $code): Promocode
    {
        $promocode = $this->activePromocode($code);

        if (! $promocode) {
            throw ValidationException::withMessages([
                'code' => 'Купон не знайдено або він уже неактивний.',
            ]);
        }

        return $promocode;
    }

    private function activePromocode(string $code): ?Promocode
    {
        $code = $this->normalizePromocode($code);

        if ($code === '') {
            return null;
        }

        return Promocode::query()
            ->whereRaw('upper(code) = ?', [$code])
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->where(fn (Builder $query) => $query
                ->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit'))
            ->first();
    }

    private function promocodeDiscount(Promocode $promocode, int $subtotalCents): int
    {
        $discountCents = match ($promocode->discount_type) {
            'percent' => (int) round($subtotalCents * ((float) $promocode->percent_off / 100)),
            default => (int) $promocode->amount_cents,
        };

        if ($promocode->max_discount_cents) {
            $discountCents = min($discountCents, (int) $promocode->max_discount_cents);
        }

        return min(max(0, $discountCents), $subtotalCents);
    }

    private function normalizePromocode(string $code): string
    {
        return Str::upper(trim($code));
    }

    public function formatMoney(?int $amount, ?string $currency = 'UAH'): string
    {
        $amount = (int) $amount;
        $value = number_format($amount / 100, $amount % 100 === 0 ? 0 : 2, ',', ' ');
        $currency = $currency ?: 'UAH';

        return $currency === 'UAH' ? $value.' грн' : $value.' '.$currency;
    }

    private function money(?int $amount): string
    {
        return $this->formatMoney($amount);
    }

    private function freeShippingThresholdCents(): int
    {
        return $this->deliveryPolicy->freeShippingThresholdCents();
    }

    private function freeShippingProgress(int $cartTotalCents, int $thresholdCents): array
    {
        $thresholdCents = max(1, $thresholdCents);
        $cartTotalCents = max(0, $cartTotalCents);
        $remainingCents = max(0, $thresholdCents - $cartTotalCents);
        $progressPercent = min(100, (int) floor(($cartTotalCents / $thresholdCents) * 100));

        return [
            'remaining_cents' => $remainingCents,
            'progress_percent' => $progressPercent,
            'label' => $remainingCents > 0
                ? 'Додайте ще '.$this->formatMoney($remainingCents).', щоб отримати безкоштовну доставку'
                : 'Безкоштовна доставка доступна для цього замовлення',
        ];
    }

    private function quantityLabel(int $quantity): string
    {
        $absolute = abs($quantity);
        $lastTwo = $absolute % 100;
        $last = $absolute % 10;

        if ($last === 1 && $lastTwo !== 11) {
            return $quantity.' товар';
        }

        if ($last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14)) {
            return $quantity.' товари';
        }

        return $quantity.' товарів';
    }

    private function snapshot(Product $product, ?ProductVariant $variant = null): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();
        $variantName = $this->variantLabel($variant);

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_slug' => $product->primaryCategory?->slug,
            'sku' => $variant?->sku ?: $product->sku,
            'variant_name' => $variantName ?: null,
            'image_url' => $this->imageUrl($mainImage, $product, 'thumb'),
            'image_alt' => $mainImage?->alt ?: $product->name,
            'currency' => $product->currency ?: 'UAH',
            'old_price_cents' => $variant?->old_price_cents ?: $product->old_price_cents,
        ];
    }

    private function serializeItem(CartItem $item): array
    {
        $snapshot = $item->product_snapshot ?? [];
        $categorySlug = $snapshot['category_slug'] ?? $item->product?->primaryCategory?->slug;
        $productSlug = $snapshot['slug'] ?? $item->product?->slug;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'name' => $snapshot['name'] ?? $item->product?->name ?? 'Товар',
            'variant_name' => ($item->variant ? $this->variantLabel($item->variant) : null) ?: ($snapshot['variant_name'] ?? null),
            'sku' => $snapshot['sku'] ?? $item->variant?->sku ?? $item->product?->sku,
            'quantity' => (int) $item->quantity,
            'price_cents' => (int) $item->price_cents,
            'old_price_cents' => (int) ($snapshot['old_price_cents'] ?? 0),
            'total_cents' => (int) $item->total_cents,
            'currency' => $snapshot['currency'] ?? 'UAH',
            'variant_options' => $this->serializeVariantOptions($item),
            'image_url' => $snapshot['image_url'] ?? null,
            'image_alt' => $snapshot['image_alt'] ?? ($snapshot['name'] ?? 'Товар'),
            'url' => $categorySlug && $productSlug ? url('/catalog/'.$categorySlug.'/'.$productSlug) : null,
        ];
    }

    private function serializeVariantOptions(CartItem $item): array
    {
        $product = $item->product;

        if (! $product) {
            return [];
        }

        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'label' => $this->variantLabel($variant),
                'price_cents' => (int) ($variant->price_cents ?: $product->price_cents),
                'old_price_cents' => (int) ($variant->old_price_cents ?: $product->old_price_cents),
                'price_formatted' => $this->formatMoney($variant->price_cents ?: $product->price_cents, $product->currency ?: 'UAH'),
                'is_current' => (int) $item->product_variant_id === (int) $variant->id,
                'is_available' => $this->variantIsAvailable($product, $variant),
            ])
            ->values()
            ->all();
    }

    private function variantLabel(?ProductVariant $variant): string
    {
        if (! $variant) {
            return '';
        }

        $size = trim((string) $variant->size);
        $name = trim((string) $variant->name);
        $label = $size !== '' ? $size : $name;

        return $label !== '' ? $label : 'Варіант #'.$variant->id;
    }

    private function serializeProduct(Product $product): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price_cents' => $product->price_cents,
            'old_price_cents' => $product->old_price_cents,
            'currency' => $product->currency ?: 'UAH',
            'stock_status' => $product->stock_status,
            'is_new' => (bool) $product->is_new,
            'is_bestseller' => (bool) $product->is_bestseller,
            'is_featured' => (bool) $product->is_featured,
            'category' => $product->primaryCategory ? [
                'name' => $product->primaryCategory->name,
                'slug' => $product->primaryCategory->slug,
            ] : null,
            'url' => $product->primaryCategory ? url('/catalog/'.$product->primaryCategory->slug.'/'.$product->slug) : url('/catalog'),
            'image_url' => $this->imageUrl($mainImage, $product, 'card'),
            'image_alt' => $mainImage?->alt ?: $product->name,
        ];
    }

    private function imageUrl(?ProductImage $image, ?Product $product = null, string $variant = 'original'): ?string
    {
        $path = trim((string) $image?->path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = Str::of($path)
            ->replace('\\', '/')
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->ltrim('/')
            ->toString();

        if ($product && in_array($variant, ['card', 'thumb'], true)) {
            $variantPath = "products/{$product->id}/{$product->slug}-{$variant}.webp";

            if (Storage::disk($image?->disk ?: 'public')->exists($variantPath)) {
                return Storage::disk($image?->disk ?: 'public')->url($variantPath);
            }
        }

        return $path !== '' ? Storage::disk($image?->disk ?: 'public')->url($path) : null;
    }

    private function published(Builder $query): void
    {
        $query->whereNull('published_at')
            ->orWhere('published_at', '<=', now());
    }
}
