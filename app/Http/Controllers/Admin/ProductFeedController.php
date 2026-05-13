<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFeedConfig;
use App\Services\ProductFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductFeedController extends Controller
{
    public function __construct(
        private readonly ProductFeedService $feedService
    ) {
    }

    public function index(): Response
    {
        $products = $this->feedService->baseProductQuery()
            ->paginate(20)
            ->withQueryString();

        $this->feedService->attachStatusesToPaginator($products);

        return Inertia::render('Admin/Catalog/ProductFeeds/Index', [
            'products' => $products->through(fn (Product $product): array => $this->serializeProductRow($product)),
            'channelOptions' => $this->feedService->channelOptions(),
            'feedUrls' => $this->feedService->feedUrls(),
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Catalog/ProductFeeds/Edit', [
            'payload' => $this->feedService->productFeedPayload($product),
            'channelOptions' => $this->feedService->channelOptions(),
            'defaultGoogleCategory' => $this->feedService->suggestedGoogleCategory($product),
            'googleGenderOptions' => $this->options($this->feedService->googleGenderOptions()),
            'googleAgeGroupOptions' => $this->options($this->feedService->googleAgeGroupOptions()),
            'googleSizeSystemOptions' => $this->options($this->feedService->googleSizeSystemOptions()),
            'googleSizeTypeOptions' => $this->options($this->feedService->googleSizeTypeOptions()),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $allowedChannels = array_keys($this->feedService->channelOptions());

        $data = $request->validate([
            'channels' => ['required', 'array'],
            'channels.*.is_enabled' => ['nullable', 'boolean'],
            'channels.*.brand' => ['nullable', 'string', 'max:255'],
            'channels.*.google_product_category' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_title' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_description' => ['nullable', 'string'],
            'channels.*.google_gender' => ['nullable', 'string', Rule::in(array_keys($this->feedService->googleGenderOptions()))],
            'channels.*.google_age_group' => ['nullable', 'string', Rule::in(array_keys($this->feedService->googleAgeGroupOptions()))],
            'channels.*.google_material' => ['nullable', 'string', 'max:200'],
            'channels.*.google_pattern' => ['nullable', 'string', 'max:100'],
            'channels.*.google_size_system' => ['nullable', 'string', Rule::in(array_keys($this->feedService->googleSizeSystemOptions()))],
            'channels.*.google_size_types' => ['nullable', 'array', 'max:2'],
            'channels.*.google_size_types.*' => ['nullable', 'string', Rule::in(array_keys($this->feedService->googleSizeTypeOptions()))],
            'channels.*.google_is_bundle' => ['nullable', 'boolean'],
            'channels.*.google_item_group_id' => ['nullable', 'string', 'max:80'],
            'channels.*.google_product_highlights' => ['nullable', 'string'],
            'channels.*.google_product_details' => ['nullable', 'string'],
            'channels.*.custom_label_0' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_label_1' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_label_2' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_label_3' => ['nullable', 'string', 'max:255'],
            'channels.*.custom_label_4' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($allowedChannels as $channel) {
            $channelData = $data['channels'][$channel] ?? [];

            ProductFeedConfig::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'channel' => $channel,
                ],
                [
                    'is_enabled' => (bool) ($channelData['is_enabled'] ?? false),
                    'brand' => $this->nullableTrim($channelData['brand'] ?? null),
                    'google_product_category' => $this->nullableTrim($channelData['google_product_category'] ?? null),
                    'custom_title' => $this->nullableTrim($channelData['custom_title'] ?? null),
                    'custom_description' => $this->nullableTrim($channelData['custom_description'] ?? null),
                    'google_gender' => $this->nullableTrim($channelData['google_gender'] ?? null),
                    'google_age_group' => $this->nullableTrim($channelData['google_age_group'] ?? null),
                    'google_material' => $this->nullableTrim($channelData['google_material'] ?? null),
                    'google_pattern' => $this->nullableTrim($channelData['google_pattern'] ?? null),
                    'google_size_system' => $this->nullableTrim($channelData['google_size_system'] ?? null),
                    'google_size_types' => $this->normalizedSizeTypes($channelData['google_size_types'] ?? []),
                    'google_is_bundle' => (bool) ($channelData['google_is_bundle'] ?? false),
                    'google_item_group_id' => $this->nullableTrim($channelData['google_item_group_id'] ?? null),
                    'google_product_highlights' => $this->parsedProductHighlights($channelData['google_product_highlights'] ?? null, "channels.$channel.google_product_highlights"),
                    'google_product_details' => $this->parsedProductDetails($channelData['google_product_details'] ?? null, "channels.$channel.google_product_details"),
                    'custom_label_0' => $this->nullableTrim($channelData['custom_label_0'] ?? null),
                    'custom_label_1' => $this->nullableTrim($channelData['custom_label_1'] ?? null),
                    'custom_label_2' => $this->nullableTrim($channelData['custom_label_2'] ?? null),
                    'custom_label_3' => $this->nullableTrim($channelData['custom_label_3'] ?? null),
                    'custom_label_4' => $this->nullableTrim($channelData['custom_label_4'] ?? null),
                ]
            );
        }

        return redirect()
            ->route('admin.product-feeds.edit', $product)
            ->with('success', 'Налаштування Product Feeds оновлено');
    }

    private function serializeProductRow(Product $product): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();
        $imagePath = trim((string) $mainImage?->path);
        $imageUrl = $imagePath !== ''
            ? (Str::startsWith($imagePath, ['http://', 'https://'])
                ? $imagePath
                : asset('storage/'.Str::of($imagePath)->replaceStart('/storage/', '')->replaceStart('storage/', '')->ltrim('/')->toString()))
            : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'status' => $product->status,
            'price' => number_format(($product->price_cents ?? 0) / 100, 2, '.', ''),
            'currency' => $product->currency,
            'main_image_url' => $imageUrl,
            'variants_count' => $product->variants_count,
            'feed_statuses' => $product->feed_statuses,
        ];
    }

    private function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizedSizeTypes(array $values): ?array
    {
        $normalized = collect($values)->map(fn ($value): string => trim((string) $value))->filter()->unique()->values()->all();

        return $normalized !== [] ? $normalized : null;
    }

    private function parsedProductHighlights(?string $value, string $field): ?array
    {
        $highlights = collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])
            ->map(function (string $line): string {
                $line = trim($line);
                $line = preg_replace('/^[-*•]+\s*/u', '', $line) ?? $line;

                return trim($line);
            })
            ->filter()
            ->unique()
            ->values();

        if ($highlights->isEmpty()) {
            return null;
        }

        if ($highlights->count() > 100) {
            throw ValidationException::withMessages([$field => 'Для Google Merchant можна зберегти максимум 100 основних характеристик.']);
        }

        if ($highlights->first(fn (string $line): bool => mb_strlen($line) > 150) !== null) {
            throw ValidationException::withMessages([$field => 'Кожна характеристика має бути не довша за 150 символів.']);
        }

        return $highlights->all();
    }

    private function parsedProductDetails(?string $value, string $field): ?array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])->map(fn (string $line): string => trim($line))->filter()->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->map(function (string $line) use ($field): array {
            $parts = collect(explode('|', $line))->map(fn (string $part): string => trim($part))->values();

            if (! in_array($parts->count(), [2, 3], true)) {
                throw ValidationException::withMessages([$field => 'Формат рядка: "Назва | Значення" або "Розділ | Назва | Значення".']);
            }

            [$sectionName, $attributeName, $attributeValue] = $parts->count() === 2
                ? [null, $parts[0], $parts[1]]
                : [$parts[0], $parts[1], $parts[2]];

            if ($attributeName === '' || $attributeValue === '') {
                throw ValidationException::withMessages([$field => 'Назва й значення обовʼязкові в кожному рядку.']);
            }

            return [
                'section_name' => $sectionName !== null && $sectionName !== '' ? $sectionName : null,
                'attribute_name' => $attributeName,
                'attribute_value' => $attributeValue,
            ];
        })->all();
    }
}
