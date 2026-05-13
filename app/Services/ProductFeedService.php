<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFeedConfig;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductFeedService
{
    public const CHANNEL_GOOGLE = 'google_merchant';
    public const CHANNEL_META = 'meta_catalog';
    public const CHANNEL_TIKTOK = 'tiktok_catalog';

    public function channelOptions(): array
    {
        return [
            self::CHANNEL_GOOGLE => [
                'label' => 'Google Merchant',
                'route' => 'feeds.google-merchant',
                'format' => 'XML',
                'color' => 'red',
            ],
            self::CHANNEL_META => [
                'label' => 'Meta Catalog',
                'route' => 'feeds.meta-catalog',
                'format' => 'CSV',
                'color' => 'blue',
            ],
            self::CHANNEL_TIKTOK => [
                'label' => 'TikTok Catalog',
                'route' => 'feeds.tiktok-catalog',
                'format' => 'CSV',
                'color' => 'pink',
            ],
        ];
    }

    public function defaultBrand(): string
    {
        return 'DomMood';
    }

    public function defaultGoogleCategory(): string
    {
        return '187';
    }

    public function googleGenderOptions(): array
    {
        return [
            'female' => 'Жіноча',
            'male' => 'Чоловіча',
            'unisex' => 'Унісекс',
        ];
    }

    public function googleAgeGroupOptions(): array
    {
        return [
            'adult' => 'Дорослі',
            'kids' => 'Діти 5-13 років',
            'toddler' => 'Малюки 1-5 років',
            'infant' => 'Немовлята 3-12 міс.',
            'newborn' => 'Новонароджені 0-3 міс.',
        ];
    }

    public function googleSizeSystemOptions(): array
    {
        return collect(['EU', 'US', 'UK', 'AU', 'BR', 'CN', 'DE', 'FR', 'IT', 'JP', 'MEX'])
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->all();
    }

    public function googleSizeTypeOptions(): array
    {
        return [
            'regular' => 'Звичайний',
            'petite' => 'Petite / маломірний',
            'plus' => 'Plus / великі розміри',
            'tall' => 'Tall / для високих',
            'big' => 'Big / широкий розмір',
            'maternity' => 'Для вагітних',
        ];
    }

    public function feedUrls(): array
    {
        return collect($this->channelOptions())
            ->mapWithKeys(fn (array $channel, string $key): array => [
                $key => [
                    'label' => $channel['label'],
                    'format' => $channel['format'],
                    'url' => route($channel['route']),
                    'color' => $channel['color'],
                ],
            ])
            ->all();
    }

    public function baseProductQuery()
    {
        return Product::query()
            ->with([
                'primaryCategory:id,name,slug',
                'categories:id,name,slug',
                'colorGroup:id,name',
                'images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
                'variants.images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
                'feedConfigs',
            ])
            ->withCount(['variants', 'categories'])
            ->ordered();
    }

    public function attachStatusesToPaginator(LengthAwarePaginator $products): void
    {
        $products->getCollection()->transform(function (Product $product): Product {
            $product->feed_statuses = $this->productChannelStatuses($product);

            return $product;
        });
    }

    public function productFeedPayload(Product $product): array
    {
        $product->loadMissing([
            'primaryCategory:id,name,slug',
            'categories:id,name,slug',
            'images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
            'variants.images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
            'feedConfigs',
        ]);

        $channels = [];

        foreach (array_keys($this->channelOptions()) as $channel) {
            $items = $this->buildFeedItems($product, $channel);
            $config = $this->channelConfig($product, $channel);

            $channels[$channel] = [
                'config' => $this->serializeConfig($config),
                'status' => $this->statusFromItems($items),
                'items' => $items,
            ];
        }

        return [
            'product' => $this->serializeProduct($product),
            'channels' => $channels,
            'feedUrls' => $this->feedUrls(),
        ];
    }

    public function exportItems(string $channel): Collection
    {
        return $this->baseProductQuery()
            ->get()
            ->flatMap(function (Product $product) use ($channel): array {
                $config = $this->channelConfig($product, $channel);
                if (! $config->is_enabled) {
                    return [];
                }

                return collect($this->buildFeedItems($product, $channel))
                    ->filter(fn (array $item): bool => $item['is_exportable'] === true)
                    ->values()
                    ->all();
            })
            ->values();
    }

    public function productChannelStatuses(Product $product): array
    {
        $statuses = [];

        foreach (array_keys($this->channelOptions()) as $channel) {
            $statuses[$channel] = $this->statusFromItems($this->buildFeedItems($product, $channel));
        }

        return $statuses;
    }

    public function buildFeedItems(Product $product, string $channel): array
    {
        $config = $this->channelConfig($product, $channel);
        $productUrl = $this->productUrl($product);
        $variants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
            ->sortBy('sort_order')
            ->values();

        if ($variants->isEmpty()) {
            return [$this->mapItem($product, null, $config, $productUrl)];
        }

        return $variants
            ->map(fn (ProductVariant $variant): array => $this->mapItem($product, $variant, $config, $productUrl))
            ->all();
    }

    public function suggestedGoogleCategory(Product $product): string
    {
        return $this->inferredGoogleCategory($product);
    }

    protected function mapItem(
        Product $product,
        ?ProductVariant $variant,
        ProductFeedConfig $config,
        ?string $productUrl
    ): array {
        $title = trim((string) ($config->custom_title ?: $product->name));
        $color = trim((string) ($variant?->color_name ?: $product->colorGroup?->name ?: ''));
        $size = trim((string) ($variant?->size ?: ''));
        $description = $this->descriptionText($product, $config);
        $imageUrls = $this->imageUrls($product, $variant);
        $pricing = $this->pricingForItem($product, $variant);
        $issues = [];

        if (! $config->is_enabled) {
            $issues[] = 'Канал ще не ввімкнено для цього товару.';
        }

        if ($product->status !== Product::STATUS_ACTIVE) {
            $issues[] = 'Товар не активний у каталозі.';
        }

        if ($title === '') {
            $issues[] = 'Немає назви товару для фіда.';
        }

        if ($description === '') {
            $issues[] = 'Немає короткого або повного опису.';
        }

        if (! $productUrl) {
            $issues[] = 'Не знайдено публічний URL товару.';
        }

        if (! $imageUrls['image_link']) {
            $issues[] = 'Не знайдено головне зображення товару.';
        }

        if ($pricing['price'] === null || $pricing['price'] <= 0) {
            $issues[] = 'Не заповнена ціна товару або варіанта.';
        }

        if ($variant && ! $variant->is_active) {
            $issues[] = 'Варіант вимкнений.';
        }

        $itemTitle = trim(collect([$title, $color, $size])->filter()->implode(' '));
        $itemId = $variant?->sku ?: ($variant ? 'variant-'.$variant->id : ($product->sku ?: 'product-'.$product->id));
        $groupId = trim((string) ($config->google_item_group_id ?: ($product->sku ?: 'product-'.$product->id)));
        $productType = $this->productType($product);
        $brand = trim((string) ($config->brand ?: $this->defaultBrand()));
        $identifiers = $this->googleIdentifiers($product, $variant, $brand);
        $audience = $this->googleAudience($product, $config);

        return [
            'channel' => $config->channel,
            'is_enabled' => (bool) $config->is_enabled,
            'is_exportable' => (bool) $config->is_enabled && empty($issues),
            'issues' => $issues,
            'id' => $itemId,
            'item_group_id' => $groupId,
            'title' => $itemTitle,
            'description' => $description,
            'link' => $productUrl,
            'image_link' => $imageUrls['image_link'],
            'additional_image_links' => $imageUrls['additional_image_links'],
            'availability' => $this->availabilityForItem($product, $variant),
            'condition' => 'new',
            'brand' => $brand,
            'gtin' => $identifiers['gtin'],
            'mpn' => $identifiers['mpn'],
            'identifier_exists' => $identifiers['identifier_exists'],
            'price' => $pricing['price'],
            'sale_price' => $pricing['sale_price'],
            'currency' => $product->currency ?: 'UAH',
            'size' => $size,
            'color' => $color,
            'product_type' => $productType,
            'google_product_category' => $this->googleCategory($product, $config),
            'google_gender' => $audience['gender'],
            'google_age_group' => $audience['age_group'],
            'google_material' => trim((string) ($config->google_material ?: '')),
            'google_pattern' => trim((string) ($config->google_pattern ?: '')),
            'google_size_system' => trim((string) ($config->google_size_system ?: '')),
            'google_size_types' => $this->normalizedArray($config->google_size_types ?? []),
            'google_is_bundle' => (bool) ($config->google_is_bundle ?? false),
            'google_product_highlights' => $this->normalizedArray($config->google_product_highlights ?? []),
            'google_product_details' => $this->normalizedProductDetails($config->google_product_details ?? []),
            'custom_labels' => [
                $config->custom_label_0,
                $config->custom_label_1,
                $config->custom_label_2,
                $config->custom_label_3,
                $config->custom_label_4,
            ],
            'variant_id' => $variant?->id,
            'variant_sku' => $variant?->sku,
            'variant_stock' => $variant?->stock_quantity,
        ];
    }

    protected function statusFromItems(array $items): array
    {
        $total = count($items);
        $exportable = collect($items)->where('is_exportable', true)->count();
        $issues = collect($items)->flatMap(fn (array $item): array => $item['issues'])->filter()->values()->all();

        if ($total === 0) {
            return ['state' => 'empty', 'label' => 'Немає item', 'class' => 'slate', 'count' => 0, 'issues' => []];
        }

        if ($exportable === 0) {
            return ['state' => 'error', 'label' => 'Не готово', 'class' => 'rose', 'count' => 0, 'issues' => array_values(array_unique($issues))];
        }

        if ($exportable < $total) {
            return ['state' => 'partial', 'label' => 'Частково', 'class' => 'amber', 'count' => $exportable, 'issues' => array_values(array_unique($issues))];
        }

        return ['state' => 'ready', 'label' => 'Готово', 'class' => 'emerald', 'count' => $exportable, 'issues' => []];
    }

    protected function channelConfig(Product $product, string $channel): ProductFeedConfig
    {
        return $product->feedConfigs->firstWhere('channel', $channel)
            ?: new ProductFeedConfig([
                'product_id' => $product->id,
                'channel' => $channel,
                'is_enabled' => false,
                'brand' => $this->defaultBrand(),
            ]);
    }

    protected function descriptionText(Product $product, ProductFeedConfig $config): string
    {
        if ($config->custom_description) {
            return trim(preg_replace('/\s+/', ' ', strip_tags($config->custom_description)) ?? '');
        }

        $description = trim((string) ($product->short_description ?: $product->description ?: $product->meta_description ?: ''));

        return trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');
    }

    protected function imageUrls(Product $product, ?ProductVariant $variant): array
    {
        $variantImages = $variant
            ? $variant->images->sortBy('sort_order')->values()
            : new EloquentCollection();
        $productImages = $product->images->sortBy('sort_order')->values();
        $images = $variantImages->isNotEmpty() ? $variantImages : $productImages;

        $primary = $this->imageUrl($images->first());
        $additional = $images->skip(1)
            ->map(fn (ProductImage $image): ?string => $this->imageUrl($image))
            ->filter()
            ->values()
            ->all();

        return [
            'image_link' => $primary,
            'additional_image_links' => $additional,
        ];
    }

    protected function imageUrl(?ProductImage $image): ?string
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

        return url(Storage::disk($image?->disk ?: 'public')->url($path));
    }

    protected function pricingForItem(Product $product, ?ProductVariant $variant): array
    {
        $productPrice = $this->centsToMoney($product->price_cents);
        $variantPrice = $this->centsToMoney($variant?->price_cents);

        if ($variantPrice !== null) {
            if ($product->old_price_cents && $product->old_price_cents > ($variant->price_cents ?? 0)) {
                return ['price' => $this->centsToMoney($product->old_price_cents), 'sale_price' => $variantPrice];
            }

            return ['price' => $variantPrice, 'sale_price' => null];
        }

        if ($product->old_price_cents && $product->old_price_cents > $product->price_cents) {
            return ['price' => $this->centsToMoney($product->old_price_cents), 'sale_price' => $productPrice];
        }

        return ['price' => $productPrice, 'sale_price' => null];
    }

    protected function centsToMoney(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    protected function availabilityForItem(Product $product, ?ProductVariant $variant): string
    {
        if ($product->status !== Product::STATUS_ACTIVE || $product->stock_status === Product::STOCK_OUT_OF_STOCK) {
            return 'out_of_stock';
        }

        if ($variant) {
            return ($variant->stock_quantity ?? 0) > 0 ? 'in_stock' : 'out_of_stock';
        }

        return $product->stock_status === Product::STOCK_PREORDER ? 'preorder' : 'in_stock';
    }

    protected function productType(Product $product): string
    {
        $categories = $product->categories->isNotEmpty()
            ? $product->categories
            : collect([$product->primaryCategory])->filter();

        return $categories
            ->sortBy('sort_order')
            ->pluck('name')
            ->filter()
            ->implode(' > ');
    }

    protected function productUrl(Product $product): ?string
    {
        if (! $product->slug) {
            return null;
        }

        $categorySlug = $product->primaryCategory?->slug
            ?: $product->categories->first()?->slug;

        return $categorySlug
            ? url('/catalog/'.$categorySlug.'/'.$product->slug)
            : url('/products/'.$product->slug);
    }

    protected function googleIdentifiers(Product $product, ?ProductVariant $variant, string $brand): array
    {
        if ($variant) {
            $gtin = $this->normalizedGtin($variant->barcode);
            $mpn = $gtin ? null : $this->normalizedMpn($variant->sku);

            return [
                'gtin' => $gtin,
                'mpn' => $mpn,
                'identifier_exists' => $this->identifierExistsValue($gtin, $mpn, $brand),
            ];
        }

        $gtin = null;
        $mpn = $gtin ? null : $this->normalizedMpn($product->sku);

        return [
            'gtin' => $gtin,
            'mpn' => $mpn,
            'identifier_exists' => $this->identifierExistsValue($gtin, $mpn, $brand),
        ];
    }

    protected function googleAudience(Product $product, ProductFeedConfig $config): array
    {
        $gender = trim((string) ($config->google_gender ?: ''));
        $ageGroup = trim((string) ($config->google_age_group ?: ''));
        $inferred = $this->inferredGoogleAudience($product);

        return [
            'gender' => $gender !== '' ? $gender : $inferred['gender'],
            'age_group' => $ageGroup !== '' ? $ageGroup : $inferred['age_group'],
        ];
    }

    protected function googleCategory(Product $product, ProductFeedConfig $config): string
    {
        $inferred = $this->inferredGoogleCategory($product);
        $configured = trim((string) ($config->google_product_category ?: ''));

        if ($configured === '') {
            return $inferred;
        }

        $normalized = $this->normalizedGoogleCategory($configured);

        return $normalized === $this->defaultGoogleCategory() && $inferred !== $this->defaultGoogleCategory()
            ? $inferred
            : $normalized;
    }

    protected function inferredGoogleCategory(Product $product): string
    {
        $signals = Str::lower(collect([$product->name, $this->productType($product)])->filter()->implode(' | '));

        if (Str::contains($signals, ['піжам', 'пижам', 'sleepwear', 'loungewear', 'pajama', 'nightgown'])) {
            return '2580';
        }

        return $this->defaultGoogleCategory();
    }

    protected function inferredGoogleAudience(Product $product): array
    {
        $signals = Str::lower(collect([$product->name, $this->productType($product)])->filter()->implode(' | '));
        $isKids = Str::contains($signals, ['дитяч', 'дівоч', 'дівч', 'хлопч', 'малюк', 'підліт']);
        $isFemale = Str::contains($signals, ['жіноч', 'дівоч', 'дівч']);
        $isMale = Str::contains($signals, ['чоловіч', 'хлопч']);

        return [
            'gender' => $isFemale ? 'female' : ($isMale ? 'male' : ($isKids ? 'unisex' : 'female')),
            'age_group' => $isKids ? 'kids' : 'adult',
        ];
    }

    protected function normalizedGoogleCategory(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8'));

        if ($value === '') {
            return $this->defaultGoogleCategory();
        }

        if (preg_match('/^(\d+)\s*-/', $value, $matches) || preg_match('/^(\d+)$/', $value, $matches)) {
            return $matches[1];
        }

        $normalizedValue = preg_replace('/\s+/', ' ', $value) ?? $value;

        return [
            'Apparel & Accessories' => '166',
            'Apparel & Accessories > Shoes' => '187',
            'Apparel & Accessories > Shoes > Slippers' => '187',
            'Apparel & Accessories > Clothing > Sleepwear' => '208',
            'Apparel & Accessories > Clothing > Sleepwear > Pajamas' => '2580',
            'Apparel & Accessories > Clothing > Sleepwear & Loungewear' => '208',
            'Apparel & Accessories > Clothing > Sleepwear & Loungewear > Pajamas' => '2580',
        ][$normalizedValue] ?? $normalizedValue;
    }

    protected function normalizedGtin(mixed $value): ?string
    {
        $digits = preg_replace('/[\s-]+/', '', trim((string) $value)) ?? '';

        if ($digits === '' || ! preg_match('/^\d+$/', $digits) || ! in_array(strlen($digits), [8, 12, 13, 14], true)) {
            return null;
        }

        return $this->hasValidGtinCheckDigit($digits) ? $digits : null;
    }

    protected function normalizedMpn(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function identifierExistsValue(?string $gtin, ?string $mpn, string $brand): ?string
    {
        return $gtin !== null || ($mpn !== null && trim($brand) !== '') ? null : 'no';
    }

    protected function hasValidGtinCheckDigit(string $digits): bool
    {
        $checkDigit = (int) substr($digits, -1);
        $body = array_reverse(str_split(substr($digits, 0, -1)));
        $sum = collect($body)->reduce(
            fn (int $carry, string $digit, int $index): int => $carry + (((int) $digit) * ($index % 2 === 0 ? 3 : 1)),
            0
        );

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }

    protected function normalizedArray(array $values): array
    {
        return collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizedProductDetails(array $details): array
    {
        return collect($details)
            ->map(function ($detail): ?array {
                if (! is_array($detail)) {
                    return null;
                }

                $attributeName = trim((string) ($detail['attribute_name'] ?? ''));
                $attributeValue = trim((string) ($detail['attribute_value'] ?? ''));
                $sectionName = trim((string) ($detail['section_name'] ?? ''));

                if ($attributeName === '' || $attributeValue === '') {
                    return null;
                }

                return [
                    'section_name' => $sectionName !== '' ? $sectionName : null,
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function serializeConfig(ProductFeedConfig $config): array
    {
        return [
            'is_enabled' => (bool) $config->is_enabled,
            'brand' => $config->brand ?: $this->defaultBrand(),
            'google_product_category' => $config->google_product_category,
            'custom_title' => $config->custom_title,
            'custom_description' => $config->custom_description,
            'google_gender' => $config->google_gender,
            'google_age_group' => $config->google_age_group,
            'google_material' => $config->google_material,
            'google_pattern' => $config->google_pattern,
            'google_size_system' => $config->google_size_system,
            'google_size_types' => $config->google_size_types ?? [],
            'google_is_bundle' => (bool) $config->google_is_bundle,
            'google_item_group_id' => $config->google_item_group_id,
            'google_product_highlights' => $config->google_product_highlights ?? [],
            'google_product_details' => $config->google_product_details ?? [],
            'custom_label_0' => $config->custom_label_0,
            'custom_label_1' => $config->custom_label_1,
            'custom_label_2' => $config->custom_label_2,
            'custom_label_3' => $config->custom_label_3,
            'custom_label_4' => $config->custom_label_4,
        ];
    }

    protected function serializeProduct(Product $product): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'status' => $product->status,
            'price' => number_format(($product->price_cents ?? 0) / 100, 2, '.', ''),
            'currency' => $product->currency,
            'main_image_url' => $this->imageUrl($mainImage),
            'variants_count' => $product->variants_count ?? $product->variants->count(),
            'categories' => $product->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()->all(),
        ];
    }
}
