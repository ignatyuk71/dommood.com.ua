<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductColorGroup;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Services\AdminActivityLogger;
use App\Services\Media\ProductImageOptimizer;
use App\Support\Catalog\CatalogSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const STATUS_LABELS = [
        Product::STATUS_DRAFT => 'Чернетка',
        Product::STATUS_ACTIVE => 'Активний',
        Product::STATUS_ARCHIVED => 'Архів',
    ];

    private const STOCK_STATUS_LABELS = [
        Product::STOCK_IN_STOCK => 'В наявності',
        Product::STOCK_OUT_OF_STOCK => 'Немає в наявності',
        Product::STOCK_PREORDER => 'Передзамовлення',
    ];

    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $categoryId = $request->integer('category_id') ?: null;

        $products = Product::query()
            ->with([
                'primaryCategory:id,name,slug',
                'categories:id,name,slug',
                'images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount(['categories', 'attributeValues', 'variants'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, Product::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($categoryId, function ($query) use ($categoryId): void {
                $query->whereHas('categories', fn ($inner) => $inner->where('categories.id', $categoryId));
            })
            ->ordered()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->serializeProduct($product));

        return Inertia::render('Admin/Catalog/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
            ],
            'statusOptions' => $this->statusOptions(),
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/Products/Form', [
            'mode' => 'create',
            'product' => $this->emptyProduct(),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            $product = Product::query()->create($this->payload($data));
            $this->syncCategories($product, $data);
            $this->syncAttributes($product, $data['attribute_value_ids'] ?? []);
            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants'] ?? []);
            }
            $newImageIds = $this->storeUploadedImages($product, $request->file('images', []), $data['new_image_keys'] ?? []);
            $this->applyImageOrder($product, $data['image_order'] ?? [], $newImageIds);
            $this->ensureMainImage($product);
            $this->refreshMainImageVersions($product);
            $product->refresh();

            app(AdminActivityLogger::class)->log(
                $request,
                'product.created',
                $product,
                newValues: $this->productActivitySnapshot($product),
                description: 'Менеджер створив товар',
            );
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Товар створено');
    }

    public function edit(Product $product): Response
    {
        $product->load([
            'categories:id,name',
            'attributeValues:id,attribute_id,value,slug',
            'images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
            'variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return Inertia::render('Admin/Catalog/Products/Form', [
            'mode' => 'edit',
            'product' => $this->serializeProduct($product, full: true),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $oldValues = $this->productActivitySnapshot($product);

        DB::transaction(function () use ($request, $product, $data, $oldValues): void {
            $product->update($this->payload($data, $product->id));
            $product->refresh();

            $this->syncCategories($product, $data);
            $this->syncAttributes($product, $data['attribute_value_ids'] ?? []);
            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants'] ?? []);
            }
            $this->deleteImages($product, $data['delete_image_ids'] ?? []);
            $newImageIds = $this->storeUploadedImages($product, $request->file('images', []), $data['new_image_keys'] ?? []);
            $this->applyImageOrder($product, $data['image_order'] ?? [], $newImageIds);
            $this->ensureMainImage($product);
            $this->refreshMainImageVersions($product);

            $product->refresh();

            app(AdminActivityLogger::class)->log(
                $request,
                'product.updated',
                $product,
                oldValues: $oldValues,
                newValues: $this->productActivitySnapshot($product),
                description: 'Менеджер оновив товар',
            );
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Товар оновлено');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $oldValues = $this->productActivitySnapshot($product);

        DB::transaction(function () use ($request, $product, $oldValues): void {
            $product->load('images');
            $this->deleteGeneratedImageVersions($product);

            foreach ($product->images as $image) {
                $this->deleteImageFile($image->path);
                $image->delete();
            }

            $product->categories()->detach();
            $product->attributeValues()->detach();
            $product->variants()->delete();
            $product->delete();

            app(AdminActivityLogger::class)->log(
                $request,
                'product.deleted',
                $product,
                oldValues: $oldValues,
                newValues: ['deleted' => true],
                description: 'Менеджер видалив товар',
            );

            $this->deleteEmptyDirectory("products/{$product->id}");
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Товар видалено');
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        $newProduct = DB::transaction(function () use ($product): Product {
            $product->load([
                'categories',
                'attributeValues',
                'images',
                'variants',
            ]);

            $copy = $product->replicate(['slug', 'sku', 'published_at']);
            $copy->name = $product->name.' копія';
            $copy->slug = $this->resolveSlug($product->slug.'-copy', $copy->name);
            $copy->sku = null;
            $copy->status = Product::STATUS_DRAFT;
            $copy->published_at = null;
            $copy->save();

            $categoryPayload = $product->categories
                ->mapWithKeys(fn (Category $category): array => [
                    $category->id => [
                        'is_primary' => (bool) $category->pivot->is_primary,
                        'sort_order' => (int) $category->pivot->sort_order,
                    ],
                ])
                ->all();

            $copy->categories()->sync($categoryPayload);

            $attributePayload = $product->attributeValues
                ->mapWithKeys(fn (AttributeValue $value): array => [
                    $value->id => [
                        'attribute_id' => $value->pivot->attribute_id,
                    ],
                ])
                ->all();

            $copy->attributeValues()->sync($attributePayload);

            foreach ($product->variants as $variant) {
                $copy->variants()->create([
                    'sku' => null,
                    'barcode' => $variant->barcode,
                    'name' => $variant->name,
                    'color_name' => $variant->color_name,
                    'color_hex' => $variant->color_hex,
                    'size' => $variant->size,
                    'price_cents' => $variant->price_cents,
                    'old_price_cents' => $variant->old_price_cents,
                    'cost_price_cents' => $variant->cost_price_cents,
                    'stock_quantity' => $variant->stock_quantity,
                    'reserved_quantity' => 0,
                    'is_active' => $variant->is_active,
                    'sort_order' => $variant->sort_order,
                ]);
            }

            foreach ($product->images as $image) {
                $path = $this->normalizeStoragePath($image->path);

                if (! $path || ! Storage::disk('public')->exists($path)) {
                    continue;
                }

                $targetPath = "products/{$copy->id}/".basename($path);
                Storage::disk('public')->copy($path, $targetPath);

                $copy->images()->create([
                    'disk' => 'public',
                    'path' => $targetPath,
                    'alt' => $image->alt,
                    'title' => $image->title,
                    'is_main' => $image->is_main,
                    'sort_order' => $image->sort_order,
                ]);
            }

            $this->refreshMainImageVersions($copy);

            return $copy;
        });

        app(AdminActivityLogger::class)->log(
            $request,
            'product.duplicated',
            $newProduct,
            oldValues: ['source_product_id' => $product->id],
            newValues: $this->productActivitySnapshot($newProduct),
            description: 'Менеджер продублював товар',
        );

        return redirect()
            ->route('admin.products.edit', $newProduct)
            ->with('success', 'Товар продубльовано');
    }

    public function quickUpdate(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'stock_status' => ['required', Rule::in(Product::STOCK_STATUSES)],
            'category_ids_present' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
        $oldValues = $this->productActivitySnapshot($product);

        DB::transaction(function () use ($request, $product, $data, $oldValues): void {
            $updates = [
                'price_cents' => $this->moneyToCents($data['price']),
                'old_price_cents' => $this->nullableMoneyToCents($data['old_price'] ?? null),
                'status' => $data['status'],
                'stock_status' => $data['stock_status'],
            ];

            if ($data['status'] === Product::STATUS_ACTIVE && ! $product->published_at) {
                $updates['published_at'] = now();
            }

            if ($data['status'] !== Product::STATUS_ACTIVE) {
                $updates['published_at'] = null;
            }

            $product->update($updates);

            if ($request->boolean('category_ids_present')) {
                $this->syncQuickCategories($product, $data['category_ids'] ?? []);
            }

            $product->refresh();

            app(AdminActivityLogger::class)->log(
                $request,
                'product.quick_updated',
                $product,
                oldValues: $oldValues,
                newValues: $this->productActivitySnapshot($product),
                description: 'Менеджер швидко оновив товар',
            );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Товар оновлено',
                'product' => $this->freshProductForIndex($product),
            ]);
        }

        return redirect()->back()->with('success', 'Товар оновлено');
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);
        $oldValues = $this->variantActivitySnapshot($variant);

        $data = $request->validate([
            'size' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $variant->update([
            'size' => $this->nullableString($data['size'] ?? null),
            'price_cents' => $this->nullableMoneyToCents($data['price'] ?? null),
            'stock_quantity' => (int) $data['stock_quantity'],
            'is_active' => (bool) $data['is_active'],
        ]);

        $product->touch();
        $variant->refresh();

        app(AdminActivityLogger::class)->log(
            $request,
            'product.variant_updated',
            $product,
            oldValues: $oldValues,
            newValues: $this->variantActivitySnapshot($variant),
            metadata: ['variant_id' => $variant->id],
            description: 'Менеджер оновив варіацію товару',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Варіацію оновлено',
                'variant' => $this->serializeVariant($variant->fresh()),
            ]);
        }

        return redirect()->back()->with('success', 'Варіацію оновлено');
    }

    public function destroyVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        $this->ensureVariantBelongsToProduct($product, $variant);
        $oldValues = $this->variantActivitySnapshot($variant);
        $variantId = $variant->id;

        $variant->delete();
        $product->touch();

        app(AdminActivityLogger::class)->log(
            $request,
            'product.variant_deleted',
            $product,
            oldValues: $oldValues,
            newValues: ['deleted' => true],
            metadata: ['variant_id' => $variantId],
            description: 'Менеджер видалив варіацію товару',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Варіацію видалено',
                'variants_count' => $product->variants()->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Варіацію видалено');
    }

    private function productActivitySnapshot(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'status' => $product->status,
            'price_cents' => (int) $product->price_cents,
            'old_price_cents' => $product->old_price_cents,
            'stock_status' => $product->stock_status,
            'primary_category_id' => $product->primary_category_id,
            'published_at' => $product->published_at,
        ];
    }

    private function variantActivitySnapshot(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'size' => $variant->size,
            'price_cents' => $variant->price_cents,
            'stock_quantity' => (int) $variant->stock_quantity,
            'is_active' => (bool) $variant->is_active,
        ];
    }

    private function payload(array $data, ?int $ignoreId = null): array
    {
        return [
            'primary_category_id' => $data['primary_category_id'],
            'brand_id' => null,
            'color_group_id' => $data['color_group_id'] ?? null,
            'size_chart_id' => $data['size_chart_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $ignoreId),
            'sku' => $this->nullableString($data['sku'] ?? null),
            'short_description' => $this->nullableString($data['short_description'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => $data['status'] ?? Product::STATUS_DRAFT,
            'price_cents' => $this->moneyToCents($data['price'] ?? 0),
            'old_price_cents' => $this->nullableMoneyToCents($data['old_price'] ?? null),
            'cost_price_cents' => $this->nullableMoneyToCents($data['cost_price'] ?? null),
            'currency' => strtoupper($data['currency'] ?? 'UAH'),
            'stock_status' => $data['stock_status'] ?? Product::STOCK_IN_STOCK,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_new' => (bool) ($data['is_new'] ?? false),
            'is_bestseller' => (bool) ($data['is_bestseller'] ?? false),
            'color_sort_order' => (int) ($data['color_sort_order'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $this->nullableString($data['meta_title'] ?? null),
            'meta_description' => $this->nullableString($data['meta_description'] ?? null),
            'seo_text' => $this->nullableString($data['seo_text'] ?? null),
            'canonical_url' => $this->nullableString($data['canonical_url'] ?? null),
            'published_at' => $this->publishedAt($data['published_at'] ?? null, $data['status'] ?? Product::STATUS_DRAFT),
        ];
    }

    private function syncCategories(Product $product, array $data): void
    {
        $categoryIds = collect($data['category_ids'] ?? [])
            ->push($data['primary_category_id'])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $syncPayload = $categoryIds
            ->mapWithKeys(fn (int $id): array => [
                $id => [
                    'is_primary' => $id === (int) $data['primary_category_id'],
                    'sort_order' => 0,
                ],
            ])
            ->all();

        $product->categories()->sync($syncPayload);
    }

    private function syncQuickCategories(Product $product, array $categoryIds): void
    {
        $categoryIds = collect($categoryIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            throw ValidationException::withMessages([
                'category_ids' => 'Оберіть хоча б одну категорію для товару.',
            ]);
        }

        $primaryCategoryId = $categoryIds->contains((int) $product->primary_category_id)
            ? (int) $product->primary_category_id
            : (int) $categoryIds->first();

        $product->update([
            'primary_category_id' => $primaryCategoryId,
        ]);

        $product->categories()->sync(
            $categoryIds
                ->mapWithKeys(fn (int $id): array => [
                    $id => [
                        'is_primary' => $id === $primaryCategoryId,
                        'sort_order' => 0,
                    ],
                ])
                ->all()
        );
    }

    private function ensureVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            abort(404);
        }
    }

    private function freshProductForIndex(Product $product): array
    {
        $product = $product->fresh();
        $product->load([
            'primaryCategory:id,name,slug',
            'categories:id,name,slug',
            'images' => fn ($query) => $query->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id'),
            'variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);
        $product->loadCount(['categories', 'attributeValues', 'variants']);

        return $this->serializeProduct($product);
    }

    private function syncAttributes(Product $product, array $attributeValueIds): void
    {
        $values = AttributeValue::query()
            ->whereIn('id', collect($attributeValueIds)->map(fn ($id) => (int) $id)->unique()->all())
            ->get(['id', 'attribute_id']);

        $syncPayload = $values
            ->mapWithKeys(fn (AttributeValue $value): array => [
                $value->id => [
                    'attribute_id' => $value->attribute_id,
                ],
            ])
            ->all();

        $product->attributeValues()->sync($syncPayload);
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach (array_values($variants) as $index => $variantData) {
            if (! is_array($variantData) || ! $this->shouldPersistVariant($variantData)) {
                continue;
            }

            $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;
            $variant = $variantId
                ? $product->variants()->whereKey($variantId)->first()
                : null;

            if ($variantId && ! $variant) {
                continue;
            }

            $payload = [
                'sku' => $this->nullableString($variantData['sku'] ?? null),
                'size' => $this->nullableString($variantData['size'] ?? null),
                'price_cents' => $this->nullableMoneyToCents($variantData['price'] ?? null),
                'stock_quantity' => max(0, (int) ($variantData['stock_quantity'] ?? 0)),
                'is_active' => (bool) ($variantData['is_active'] ?? true),
                'sort_order' => $index * 10,
            ];

            if ($variant) {
                $variant->update($payload);
            } else {
                $variant = $product->variants()->create($payload);
            }

            $keptIds[] = $variant->id;
        }

        $query = $product->variants();

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }

        $query->delete();
    }

    private function shouldPersistVariant(array $variantData): bool
    {
        return collect([
            $variantData['sku'] ?? null,
            $variantData['size'] ?? null,
            $variantData['price'] ?? null,
            $variantData['stock_quantity'] ?? null,
        ])->contains(fn ($value): bool => trim((string) $value) !== '');
    }

    private function storeUploadedImages(Product $product, array|UploadedFile|null $images, array $keys = []): array
    {
        $images = is_array($images) ? $images : array_filter([$images]);

        if ($images === []) {
            return [];
        }

        $maxSortOrder = (int) $product->images()->max('sort_order');
        $storedImageIds = [];

        foreach (array_values($images) as $index => $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $imageModel = $product->images()->create([
                'disk' => 'public',
                'path' => $this->storeImage($product, $image, $index),
                'alt' => $product->name,
                'title' => $product->name,
                'is_main' => false,
                'sort_order' => $maxSortOrder + (($index + 1) * 10),
            ]);

            $key = trim((string) ($keys[$index] ?? ''));

            if ($key !== '') {
                $storedImageIds["n:{$key}"] = $imageModel->id;
            }
        }

        return $storedImageIds;
    }

    private function deleteImages(Product $product, array $imageIds): void
    {
        $images = $product->images()
            ->whereIn('id', collect($imageIds)->map(fn ($id) => (int) $id)->all())
            ->get();

        foreach ($images as $image) {
            $this->deleteImageFile($image->path);
            $image->delete();
        }
    }

    private function applyImageOrder(Product $product, array $order, array $newImageIds): void
    {
        $order = collect($order)
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->values();

        if ($order->isEmpty()) {
            return;
        }

        $existingImageIds = $product->images()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orderedIds = [];

        foreach ($order as $key) {
            $imageId = null;

            if (str_starts_with($key, 'e:')) {
                $imageId = (int) str_replace('e:', '', $key);
            }

            if (str_starts_with($key, 'n:')) {
                $imageId = $newImageIds[$key] ?? null;
            }

            if ($imageId && in_array((int) $imageId, $existingImageIds, true)) {
                $orderedIds[] = (int) $imageId;
            }
        }

        $orderedIds = array_values(array_unique($orderedIds));

        if ($orderedIds === []) {
            return;
        }

        $product->images()->update(['is_main' => false]);

        foreach ($orderedIds as $index => $imageId) {
            $product->images()->whereKey($imageId)->update([
                'is_main' => $index === 0,
                'sort_order' => $index * 10,
            ]);
        }
    }

    private function ensureMainImage(Product $product): void
    {
        if ($product->images()->where('is_main', true)->exists()) {
            return;
        }

        $firstImage = $product->images()->orderBy('sort_order')->orderBy('id')->first();

        if ($firstImage) {
            $firstImage->update(['is_main' => true]);
        }
    }

    private function refreshMainImageVersions(Product $product): void
    {
        $directory = "products/{$product->id}";
        $this->deleteGeneratedImageVersions($product);

        $mainImage = $product->images()
            ->orderByDesc('is_main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        $sourcePath = $this->normalizeStoragePath($mainImage?->path);

        if (! $mainImage || ! $sourcePath) {
            $this->deleteEmptyDirectory($directory);

            return;
        }

        $baseFilename = CatalogSlug::make($product->slug) ?: 'product-'.$product->id;

        app(ProductImageOptimizer::class)->storeResponsiveVersions(
            $sourcePath,
            $directory,
            $baseFilename,
            $mainImage->disk ?: 'public',
        );
    }

    private function deleteGeneratedImageVersions(Product $product): void
    {
        $paths = Storage::disk('public')->files("products/{$product->id}");
        $paths = array_values(array_filter($paths, fn (string $path): bool => str_ends_with($path, '-card.webp')
            || str_ends_with($path, '-thumb.webp')
            || str_ends_with($path, '-swatch.webp')));

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = trim((string) $slug);
        $base = $base !== '' ? CatalogSlug::make($base) : CatalogSlug::make($name);
        $base = $base !== '' ? $base : 'product';
        $candidate = $base;
        $counter = 1;

        while (Product::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function formOptions(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions(),
            'colorGroupOptions' => $this->colorGroupOptions(),
            'sizeChartOptions' => $this->sizeChartOptions(),
            'attributeOptions' => $this->attributeOptions(),
            'statusOptions' => $this->statusOptions(),
            'stockStatusOptions' => $this->stockStatusOptions(),
        ];
    }

    private function categoryOptions(): array
    {
        $categories = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug'])
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'label' => $this->categoryOptionLabel($category, $categories),
            ])
            ->values()
            ->all();
    }

    private function categoryOptionLabel(Category $category, $categories): string
    {
        $depth = 0;
        $parentId = $category->parent_id;
        $visited = [];

        while ($parentId && ! in_array($parentId, $visited, true)) {
            $visited[] = $parentId;
            $parent = $categories->firstWhere('id', $parentId);

            if (! $parent) {
                break;
            }

            $depth++;
            $parentId = $parent->parent_id;
        }

        return str_repeat('— ', $depth).$category->name;
    }

    private function colorGroupOptions(): array
    {
        return ProductColorGroup::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (ProductColorGroup $group): array => [
                'id' => $group->id,
                'label' => $group->name,
                'code' => $group->code,
            ])
            ->all();
    }

    private function sizeChartOptions(): array
    {
        return SizeChart::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'content_json'])
            ->map(fn (SizeChart $chart): array => [
                'id' => $chart->id,
                'label' => $chart->title,
                'code' => $chart->code,
                'content' => $chart->content_json,
            ])
            ->all();
    }

    private function attributeOptions(): array
    {
        return ProductAttribute::query()
            ->with('values:id,attribute_id,value,slug,color_hex,sort_order')
            ->ordered()
            ->get()
            ->map(fn (ProductAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $attribute->type,
                'is_filterable' => $attribute->is_filterable,
                'is_variant_option' => $attribute->is_variant_option,
                'values' => $attribute->values
                    ->map(fn (AttributeValue $value): array => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'slug' => $value->slug,
                        'color_hex' => $value->color_hex,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function statusOptions(): array
    {
        return collect(self::STATUS_LABELS)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function stockStatusOptions(): array
    {
        return collect(self::STOCK_STATUS_LABELS)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function serializeProduct(Product $product, bool $full = false): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'primary_category_id' => $product->primary_category_id,
            'primary_category' => $product->primaryCategory ? [
                'id' => $product->primaryCategory->id,
                'name' => $product->primaryCategory->name,
                'slug' => $product->primaryCategory->slug,
            ] : null,
            'category_ids' => $product->relationLoaded('categories')
                ? $product->categories->pluck('id')->values()->all()
                : [],
            'categories' => $product->relationLoaded('categories')
                ? $product->categories
                    ->map(fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])
                    ->values()
                    ->all()
                : [],
            'color_group_id' => $product->color_group_id,
            'size_chart_id' => $product->size_chart_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'public_url' => $this->publicProductUrl($product),
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'description' => $full ? $product->description : null,
            'status' => $product->status,
            'status_label' => self::STATUS_LABELS[$product->status] ?? $product->status,
            'price' => $this->centsToMoney($product->price_cents),
            'old_price' => $this->nullableCentsToMoney($product->old_price_cents),
            'cost_price' => $full ? $this->nullableCentsToMoney($product->cost_price_cents) : null,
            'currency' => $product->currency,
            'stock_status' => $product->stock_status,
            'stock_status_label' => self::STOCK_STATUS_LABELS[$product->stock_status] ?? $product->stock_status,
            'is_featured' => $product->is_featured,
            'is_new' => $product->is_new,
            'is_bestseller' => $product->is_bestseller,
            'color_sort_order' => $product->color_sort_order,
            'sort_order' => $product->sort_order,
            'meta_title' => $full ? $product->meta_title : null,
            'meta_description' => $full ? $product->meta_description : null,
            'seo_text' => $full ? $product->seo_text : null,
            'canonical_url' => $full ? $product->canonical_url : null,
            'published_at' => $product->published_at?->format('Y-m-d\TH:i'),
            'attribute_value_ids' => $full ? $product->attributeValues->pluck('id')->values()->all() : [],
            'attribute_rows' => $full ? $this->serializeAttributeRows($product) : [],
            'variants' => $product->relationLoaded('variants') ? $this->serializeVariants($product) : [],
            'images' => $this->serializeImages($product),
            'main_image_url' => $this->imageUrl($mainImage?->path),
            'categories_count' => $product->categories_count ?? 0,
            'attribute_values_count' => $product->attribute_values_count ?? 0,
            'variants_count' => $product->variants_count ?? 0,
            'created_at' => $product->created_at?->toDateTimeString(),
        ];
    }

    private function emptyProduct(): array
    {
        return [
            'primary_category_id' => '',
            'category_ids' => [],
            'color_group_id' => '',
            'size_chart_id' => '',
            'name' => '',
            'slug' => '',
            'sku' => '',
            'short_description' => '',
            'description' => '',
            'status' => Product::STATUS_DRAFT,
            'price' => '0.00',
            'old_price' => '',
            'cost_price' => '',
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
            'is_featured' => false,
            'is_new' => false,
            'is_bestseller' => false,
            'color_sort_order' => 0,
            'sort_order' => 0,
            'meta_title' => '',
            'meta_description' => '',
            'seo_text' => '',
            'canonical_url' => '',
            'published_at' => '',
            'attribute_value_ids' => [],
            'attribute_rows' => [],
            'variants' => [],
            'images' => [],
        ];
    }

    private function publicProductUrl(Product $product): string
    {
        $category = $product->primaryCategory ?: $product->categories->first();

        if (! $category || blank($product->slug)) {
            return url('/catalog');
        }

        return url('/catalog/'.$category->slug.'/'.$product->slug);
    }

    private function serializeAttributeRows(Product $product): array
    {
        return $product->attributeValues
            ->map(fn (AttributeValue $value): array => [
                'attribute_id' => $value->attribute_id,
                'attribute_value_id' => $value->id,
            ])
            ->values()
            ->all();
    }

    private function serializeVariants(Product $product): array
    {
        return $product->variants
            ->map(fn (ProductVariant $variant): array => $this->serializeVariant($variant))
            ->values()
            ->all();
    }

    private function serializeVariant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'size' => $variant->size,
            'price' => $this->nullableCentsToMoney($variant->price_cents),
            'stock_quantity' => $variant->stock_quantity,
            'is_active' => $variant->is_active,
            'sort_order' => $variant->sort_order,
        ];
    }

    private function serializeImages(Product $product): array
    {
        return $product->images
            ->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => $this->imageUrl($image->path),
                'alt' => $image->alt,
                'title' => $image->title,
                'is_main' => $image->is_main,
                'sort_order' => $image->sort_order,
            ])
            ->values()
            ->all();
    }

    private function storeImage(Product $product, UploadedFile $image, int $index): string
    {
        $siteSlug = CatalogSlug::make(config('app.name', 'dommood')) ?: 'dommood';
        $filename = "{$product->slug}-{$siteSlug}-".now()->format('Ymd-His').'-'.($index + 1);

        return app(ProductImageOptimizer::class)->storeAsWebp($image, "products/{$product->id}", $filename);
    }

    private function deleteImageFile(?string $path): void
    {
        $path = $this->normalizeStoragePath($path);

        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
        $this->deleteEmptyDirectory(dirname($path));
    }

    private function deleteEmptyDirectory(string $directory): void
    {
        $directory = trim($directory, './');

        if ($directory !== '' && Storage::disk('public')->exists($directory) && count(Storage::disk('public')->files($directory)) === 0) {
            Storage::disk('public')->deleteDirectory($directory);
        }
    }

    private function imageUrl(?string $path): ?string
    {
        $path = $this->normalizeStoragePath($path);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        return str($path)
            ->replace('\\', '/')
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->ltrim('/')
            ->toString();
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $value)) * 100);
    }

    private function nullableMoneyToCents(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->moneyToCents($value);
    }

    private function centsToMoney(?int $cents): string
    {
        return number_format(($cents ?? 0) / 100, 2, '.', '');
    }

    private function nullableCentsToMoney(?int $cents): ?string
    {
        return $cents === null ? null : $this->centsToMoney($cents);
    }

    private function publishedAt(?string $value, string $status): ?Carbon
    {
        if ($value) {
            return Carbon::parse($value);
        }

        return $status === Product::STATUS_ACTIVE ? now() : null;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
