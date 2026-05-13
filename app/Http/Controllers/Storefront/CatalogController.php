<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\DeliveryMethod;
use App\Models\FilterSeoPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Services\Seo\SeoResolver;
use App\Services\SiteSettingsService;
use App\Support\Catalog\FilterUrlBuilder;
use App\Support\Catalog\ProductFilterQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $settings,
        private readonly SeoResolver $seo,
        private readonly ProductFilterQuery $productFilterQuery,
        private readonly FilterUrlBuilder $filterUrlBuilder,
    ) {}

    public function index(Request $request, ?string $categorySlug = null, ?string $filterSegments = null): View
    {
        $category = $categorySlug
            ? Category::query()->where('slug', $categorySlug)->where('is_active', true)->firstOrFail()
            : null;
        $storeSettings = $this->settings->get('store');
        $storeName = $storeSettings['store_name'] ?? 'DomMood';
        $activeFilters = $this->filterUrlBuilder->parseSegments(
            array_values(array_filter(explode('/', trim((string) $filterSegments, '/'))))
        );
        $selectedCategorySlugs = $this->selectedCategorySlugs($request);
        $selectedCategoryIds = $this->selectedCategoryIds($selectedCategorySlugs);
        $baseQuery = $this->catalogProductQuery($request, $category, $selectedCategoryIds, $selectedCategorySlugs !== []);
        $priceFilter = $this->priceFilter($request, clone $baseQuery, $category, $activeFilters);
        $categoryFilters = $this->categoryFilters($request, $category, $selectedCategorySlugs, $activeFilters);
        $filterGroups = $this->filterGroups($request, $category, clone $baseQuery, $activeFilters);
        $filteredQuery = clone $baseQuery;

        $this->applyPriceFilter($filteredQuery, $priceFilter);

        if ($activeFilters !== []) {
            $this->productFilterQuery->apply($filteredQuery, $activeFilters);
        }

        $cardCategory = $selectedCategoryIds === [] ? $category : null;
        $products = $filteredQuery
            ->ordered()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->serializeProduct($product, $cardCategory, $selectedCategoryIds));
        $filterSeoPage = $this->filterSeoPage($category, $activeFilters);
        $seo = $this->catalogSeo($category, $filterSeoPage, $activeFilters, $storeName);
        $hasQueryFilters = $priceFilter['is_active'] || $selectedCategorySlugs !== [];

        if ($hasQueryFilters) {
            $seo['canonical_url'] = $category ? url('/catalog/'.$category->slug) : url('/catalog');
        }

        $heading = $filterSeoPage?->h1 ?: $filterSeoPage?->title ?: ($category?->name ?: 'Каталог');
        $intro = $this->categoryIntro($category, $filterSeoPage, $request);
        $seoText = $filterSeoPage?->seo_text ?: $category?->seo_text;
        $activeFilterLabels = $this->activeFilterLabels($priceFilter, $categoryFilters, $filterGroups);

        return view('storefront.catalog.index', [
            'storeName' => $storeName,
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility'),
            'mobileMenuItems' => $this->menuItems('mobile'),
            'footerMenuItems' => $this->menuItems('footer'),
            'category' => $category,
            'products' => $products,
            'query' => trim((string) $request->query('q')),
            'seo' => $seo,
            'metaRobots' => ($hasQueryFilters || ($activeFilters !== [] && ! ($filterSeoPage?->is_indexable))) ? 'noindex,follow' : null,
            'heading' => $heading,
            'intro' => $intro,
            'seoText' => $seoText,
            'priceFilter' => $priceFilter,
            'categoryFilters' => $categoryFilters,
            'filterGroups' => $filterGroups,
            'activeFilters' => $activeFilters,
            'activeFilterLabels' => $activeFilterLabels,
            'clearFiltersUrl' => $this->catalogUrlWithQuery(
                $request,
                $this->filterUrlBuilder->build($category?->slug ?: 'catalog', []),
                [],
                ['categories', 'price_from', 'price_to']
            ),
            'schemas' => $this->catalogSchemas($category, $products->items(), $heading, $seo['canonical_url'] ?? url('/catalog')),
        ]);
    }

    public function show(string $categorySlug, string $productSlug): View
    {
        $storeSettings = $this->settings->get('store');
        $storeName = $storeSettings['store_name'] ?? 'DomMood';
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->first();

        $product = null;

        if ($category) {
            $product = Product::query()
                ->with([
                    'brand:id,name,slug',
                    'categories:id,name,slug',
                    'colorGroup:id,name,code,description',
                    'primaryCategory:id,name,slug',
                    'sizeChart:id,title,code,description,content_json,content_html,image_path',
                    'attributeValues.attribute:id,name,slug,type,sort_order',
                    'images' => fn ($query) => $query
                        ->select(['id', 'product_id', 'product_variant_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                        ->orderByDesc('is_main')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'variants' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'variants.images' => fn ($query) => $query
                        ->select(['id', 'product_id', 'product_variant_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                        ->orderByDesc('is_main')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'variants.attributeValues.attribute:id,name,slug,type,sort_order',
                    'reviews' => fn ($query) => $query
                        ->approved()
                        ->latest('published_at')
                        ->limit(8),
                    'relatedProducts' => fn ($query) => $query
                        ->select([
                            'products.id',
                            'products.primary_category_id',
                            'products.name',
                            'products.slug',
                            'products.sku',
                            'products.short_description',
                            'products.price_cents',
                            'products.old_price_cents',
                            'products.currency',
                            'products.stock_status',
                            'products.is_new',
                            'products.is_bestseller',
                            'products.is_featured',
                            'products.status',
                            'products.published_at',
                            'products.created_at',
                        ])
                        ->active()
                        ->where(fn (Builder $query) => $this->published($query))
                        ->orderBy('product_relations.sort_order')
                        ->limit(4),
                    'relatedProducts.primaryCategory:id,name,slug',
                    'relatedProducts.images' => fn ($query) => $query
                        ->select(['id', 'product_id', 'disk', 'path', 'alt', 'is_main', 'sort_order'])
                        ->orderByDesc('is_main')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->active()
                ->where(fn ($query) => $this->published($query))
                ->where(fn (Builder $query) => $this->whereInCategory($query, $category))
                ->where('slug', $productSlug)
                ->first();
        }

        if (! $product) {
            $fallbackProduct = $this->fallbackPromoProduct($categorySlug, $productSlug);

            abort_unless($fallbackProduct, 404);

            $fallbackCategory = $category ?: (object) [
                'name' => 'Вуличні тапочки',
                'slug' => $categorySlug,
            ];
            $canonicalUrl = url('/catalog/'.$categorySlug.'/'.$productSlug);

            return view('storefront.catalog.show', [
                'storeName' => $storeName,
                'supportEmail' => $storeSettings['support_email'] ?? null,
                'supportPhone' => $storeSettings['support_phone'] ?? null,
                'canLogin' => Route::has('login'),
                'menuItems' => $this->menuItems('main', withFallback: true),
                'utilityLinks' => $this->menuItems('utility'),
                'mobileMenuItems' => $this->menuItems('mobile'),
                'footerMenuItems' => $this->menuItems('footer'),
                'category' => $fallbackCategory,
                'product' => array_merge($fallbackProduct, ['color_options' => []]),
                'faqItems' => $this->productFaq($fallbackProduct, 'Вуличні тапочки'),
                'relatedProducts' => [],
                'freeShippingThresholdCents' => 120000,
                'seo' => [
                    'title' => $fallbackProduct['name'].' | '.$storeName,
                    'meta_description' => $fallbackProduct['short_description'] ?? $fallbackProduct['name'],
                    'canonical_url' => $canonicalUrl,
                ],
                'schemas' => $this->productSchemas($fallbackProduct, $fallbackCategory, $canonicalUrl, $storeName),
            ]);
        }

        $serializedProduct = $this->serializeProduct($product);
        $serializedProduct['color_options'] = $this->colorOptions($product, $category);
        $seo = $this->seo->metaForProduct($product);
        $canonicalUrl = $seo['canonical_url'] ?? url('/catalog/'.$category->slug.'/'.$product->slug);

        return view('storefront.catalog.show', [
            'storeName' => $storeName,
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility'),
            'mobileMenuItems' => $this->menuItems('mobile'),
            'footerMenuItems' => $this->menuItems('footer'),
            'category' => $category,
            'product' => $serializedProduct,
            'faqItems' => $this->productFaq($serializedProduct, $category->name),
            'relatedProducts' => $this->relatedProductCards($product, $category),
            'freeShippingThresholdCents' => $this->freeShippingThresholdCents(),
            'seo' => $seo,
            'schemas' => $this->productSchemas($serializedProduct, $category, $canonicalUrl, $storeName),
        ]);
    }

    private function catalogProductQuery(Request $request, ?Category $category, array $categoryIds = [], bool $hasExplicitCategoryFilter = false): Builder
    {
        return Product::query()
            ->select([
                'id',
                'primary_category_id',
                'name',
                'slug',
                'sku',
                'short_description',
                'status',
                'price_cents',
                'old_price_cents',
                'currency',
                'stock_status',
                'is_featured',
                'is_new',
                'is_bestseller',
                'sort_order',
                'published_at',
                'created_at',
            ])
            ->with([
                'primaryCategory:id,name,slug',
                'categories:id,name,slug',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn ($query) => $this->published($query))
            ->when($categoryIds !== [], fn (Builder $query) => $this->whereInCategories($query, $categoryIds))
            ->when($categoryIds === [] && $hasExplicitCategoryFilter, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($categoryIds === [] && ! $hasExplicitCategoryFilter && $category, fn (Builder $query) => $this->whereInCategory($query, $category))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('q'));

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%");
                });
            })
            ->when($request->query('filter') === 'new', fn (Builder $query) => $query->where('is_new', true));
    }

    private function selectedCategorySlugs(Request $request): array
    {
        $categories = $request->query('categories', []);

        if (is_string($categories)) {
            $categories = str_contains($categories, ',') ? explode(',', $categories) : [$categories];
        }

        if (! is_array($categories)) {
            return [];
        }

        return collect($categories)
            ->flatten()
            ->map(fn (mixed $slug): string => trim((string) $slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function selectedCategoryIds(array $selectedCategorySlugs): array
    {
        if ($selectedCategorySlugs === []) {
            return [];
        }

        return Category::query()
            ->where('is_active', true)
            ->whereIn('slug', $selectedCategorySlugs)
            ->pluck('id')
            ->all();
    }

    private function priceFilter(Request $request, Builder $baseQuery, ?Category $category, array $activeFilters): array
    {
        $bounds = $this->priceBounds($baseQuery);
        $minPrice = (int) floor(($bounds['min_cents'] ?? 0) / 100);
        $maxPrice = (int) ceil(($bounds['max_cents'] ?? 0) / 100);
        $isAvailable = $bounds['min_cents'] !== null && $bounds['max_cents'] !== null;
        $from = $this->priceQueryValue($request->query('price_from')) ?? $minPrice;
        $to = $this->priceQueryValue($request->query('price_to')) ?? $maxPrice;

        if ($isAvailable) {
            $from = max($minPrice, min($from, $maxPrice));
            $to = max($minPrice, min($to, $maxPrice));
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filterPath = $this->filterUrlBuilder->build($category?->slug ?: 'catalog', $activeFilters);

        return [
            'is_available' => $isAvailable,
            'is_active' => $isAvailable && ($from > $minPrice || $to < $maxPrice),
            'min' => $minPrice,
            'max' => $maxPrice,
            'from' => $from,
            'to' => $to,
            'step' => 1,
            'action' => url($filterPath),
            'hidden_inputs' => $this->hiddenFilterInputs($request, ['price_from', 'price_to']),
            'reset_url' => $this->catalogUrlWithQuery($request, $filterPath, [], ['price_from', 'price_to']),
        ];
    }

    private function priceBounds(Builder $query): array
    {
        return [
            'min_cents' => (clone $query)->min('price_cents'),
            'max_cents' => (clone $query)->max('price_cents'),
        ];
    }

    private function priceQueryValue(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT);

        return $value === false ? null : max(0, (int) $value);
    }

    private function applyPriceFilter(Builder $query, array $priceFilter): void
    {
        if (! $priceFilter['is_active']) {
            return;
        }

        $query
            ->where('price_cents', '>=', ((int) $priceFilter['from']) * 100)
            ->where('price_cents', '<=', ((int) $priceFilter['to']) * 100);
    }

    private function categoryFilters(Request $request, ?Category $category, array $selectedCategorySlugs, array $activeFilters): array
    {
        $options = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $effectiveSelectedSlugs = $selectedCategorySlugs !== []
            ? $selectedCategorySlugs
            : ($category ? [$category->slug] : []);

        return $options
            ->map(function (Category $option) use ($request, $category, $selectedCategorySlugs, $effectiveSelectedSlugs, $activeFilters): ?array {
                $isActive = in_array($option->slug, $effectiveSelectedSlugs, true);
                $count = $this->catalogProductQuery($request, null, [$option->id])->count();

                if ($count === 0 && ! $isActive) {
                    return null;
                }

                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'slug' => $option->slug,
                    'count' => $count,
                    'is_active' => $isActive,
                    'is_query_active' => in_array($option->slug, $selectedCategorySlugs, true),
                    'url' => $this->categoryToggleUrl($request, $category, $activeFilters, $option->slug, $selectedCategorySlugs),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filterGroups(Request $request, ?Category $category, Builder $baseQuery, array $activeFilters): array
    {
        if (! $category) {
            return [];
        }

        $attributes = $category->filterAttributes()
            ->wherePivot('is_active', true)
            ->filterable()
            ->orderBy('category_filter_attributes.sort_order')
            ->orderBy('attributes.sort_order')
            ->orderBy('attributes.name')
            ->get(['attributes.id', 'attributes.name', 'attributes.slug', 'attributes.type']);

        if ($attributes->isEmpty()) {
            return [];
        }

        $productIds = $baseQuery->pluck('products.id')->unique()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $valuesByAttribute = AttributeValue::query()
            ->select(['id', 'attribute_id', 'value', 'slug', 'color_hex', 'sort_order'])
            ->whereIn('attribute_id', $attributes->pluck('id')->all())
            ->where(function (Builder $query) use ($productIds): void {
                $query
                    ->whereHas('products', fn (Builder $productQuery) => $productQuery->whereIn('products.id', $productIds))
                    ->orWhereHas('variants', fn (Builder $variantQuery) => $variantQuery
                        ->where('is_active', true)
                        ->whereIn('product_variants.product_id', $productIds));
            })
            ->ordered()
            ->get()
            ->groupBy('attribute_id');

        return $attributes
            ->map(function (ProductAttribute $attribute) use ($request, $category, $activeFilters, $valuesByAttribute): ?array {
                $values = $valuesByAttribute
                    ->get($attribute->id, collect())
                    ->map(function (AttributeValue $value) use ($request, $category, $activeFilters, $attribute): array {
                        $isActive = in_array($value->slug, $activeFilters[$attribute->slug] ?? [], true);

                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'slug' => $value->slug,
                            'color_hex' => $value->color_hex,
                            'is_active' => $isActive,
                            'url' => $this->filterToggleUrl($request, $category, $activeFilters, $attribute->slug, $value->slug),
                        ];
                    })
                    ->values()
                    ->all();

                if ($values === []) {
                    return null;
                }

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'display_type' => $attribute->pivot->display_type ?: ($attribute->type === ProductAttribute::TYPE_COLOR ? 'color' : 'checkbox'),
                    'values' => $values,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filterToggleUrl(Request $request, Category $category, array $activeFilters, string $attributeSlug, string $valueSlug): string
    {
        $nextFilters = $activeFilters;
        $currentValues = $nextFilters[$attributeSlug] ?? [];

        if (in_array($valueSlug, $currentValues, true)) {
            $currentValues = array_values(array_diff($currentValues, [$valueSlug]));
        } else {
            $currentValues[] = $valueSlug;
        }

        if ($currentValues === []) {
            unset($nextFilters[$attributeSlug]);
        } else {
            $nextFilters[$attributeSlug] = $currentValues;
        }

        return $this->catalogUrlWithQuery($request, $this->filterUrlBuilder->build($category->slug, $nextFilters));
    }

    private function categoryToggleUrl(Request $request, ?Category $category, array $activeFilters, string $categorySlug, array $selectedCategorySlugs): string
    {
        $nextSlugs = $selectedCategorySlugs !== []
            ? $selectedCategorySlugs
            : ($category ? [$category->slug] : []);

        if (in_array($categorySlug, $nextSlugs, true)) {
            $nextSlugs = array_values(array_diff($nextSlugs, [$categorySlug]));
        } else {
            $nextSlugs[] = $categorySlug;
        }

        $nextSlugs = array_values(array_unique($nextSlugs));

        return $this->catalogUrlWithQuery(
            $request,
            $this->filterUrlBuilder->build($category?->slug ?: 'catalog', $activeFilters),
            ['categories' => $nextSlugs],
        );
    }

    private function catalogUrlWithQuery(Request $request, string $path, array $overrides = [], array $forget = []): string
    {
        $query = $this->catalogQuery($request);
        $forget[] = 'page';

        foreach ($forget as $key) {
            unset($query[$key]);
        }

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                unset($query[$key]);

                continue;
            }

            $query[$key] = $value;
        }

        return url($path).($query === [] ? '' : '?'.http_build_query($query));
    }

    private function catalogQuery(Request $request): array
    {
        $query = [];
        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query['q'] = $search;
        }

        if ($request->query('filter') === 'new') {
            $query['filter'] = 'new';
        }

        $categories = $this->selectedCategorySlugs($request);

        if ($categories !== []) {
            $query['categories'] = $categories;
        }

        foreach (['price_from', 'price_to'] as $priceKey) {
            $priceValue = $this->priceQueryValue($request->query($priceKey));

            if ($priceValue !== null) {
                $query[$priceKey] = $priceValue;
            }
        }

        return $query;
    }

    private function hiddenFilterInputs(Request $request, array $except = []): array
    {
        return collect($this->catalogQuery($request))
            ->except($except)
            ->all();
    }

    private function filterSeoPage(?Category $category, array $activeFilters): ?FilterSeoPage
    {
        if ($activeFilters === []) {
            return null;
        }

        $signature = $this->filterSignature($category?->id, $activeFilters);

        return FilterSeoPage::query()
            ->with('category:id,name,slug')
            ->when($category, fn ($query) => $query->where('category_id', $category->id), fn ($query) => $query->whereNull('category_id'))
            ->active()
            ->get()
            ->first(fn (FilterSeoPage $page): bool => $this->filterSignature($category?->id, $page->filters ?? []) === $signature);
    }

    private function filterSignature(?int $categoryId, array $filters): string
    {
        return ($categoryId ?: 'catalog').'|'.json_encode($this->filterUrlBuilder->normalizeFilters($filters));
    }

    private function catalogSeo(?Category $category, ?FilterSeoPage $filterSeoPage, array $activeFilters, string $storeName): array
    {
        if ($filterSeoPage) {
            $seo = $this->seo->metaForFilterPage($filterSeoPage);

            if (! $filterSeoPage->is_indexable && blank($filterSeoPage->canonical_url)) {
                $seo['canonical_url'] = $category ? url('/catalog/'.$category->slug) : url('/catalog');
            }

            return $seo;
        }

        if ($category) {
            $seo = $this->seo->metaForCategory($category);

            if ($activeFilters !== []) {
                $seo['canonical_url'] = url('/catalog/'.$category->slug);
            }

            return $seo;
        }

        return [
            'title' => 'Каталог - '.$storeName,
            'meta_description' => 'Каталог товарів '.$storeName.': актуальні ціни, наявність і доставка по Україні.',
            'canonical_url' => url('/catalog'),
        ];
    }

    private function categoryIntro(?Category $category, ?FilterSeoPage $filterSeoPage, Request $request): string
    {
        if (filled($filterSeoPage?->title)) {
            return $filterSeoPage->title;
        }

        if (filled($category?->description)) {
            return $category->description;
        }

        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            return 'Результати пошуку за запитом "'.$search.'".';
        }

        return $category
            ? 'Добірка активних товарів у категорії '.$category->name.'.'
            : 'Добірка актуальних товарів магазину.';
    }

    private function activeFilterLabels(array $priceFilter, array $categoryFilters, array $filterGroups): array
    {
        $labels = [];

        if ($priceFilter['is_active']) {
            $labels[] = [
                'label' => 'Ціна: '.$priceFilter['from'].'-'.$priceFilter['to'].' грн',
                'url' => $priceFilter['reset_url'],
            ];
        }

        foreach ($categoryFilters as $categoryFilter) {
            if ($categoryFilter['is_query_active']) {
                $labels[] = [
                    'label' => 'Категорія: '.$categoryFilter['name'],
                    'url' => $categoryFilter['url'],
                ];
            }
        }

        $attributeLabels = collect($filterGroups)
            ->flatMap(fn (array $group): array => collect($group['values'])
                ->filter(fn (array $value): bool => (bool) $value['is_active'])
                ->map(fn (array $value): array => [
                    'label' => $group['name'].': '.$value['value'],
                    'url' => $value['url'],
                ])
                ->all());

        return collect($labels)
            ->merge($attributeLabels)
            ->values()
            ->all();
    }

    private function catalogSchemas(?Category $category, array $products, string $heading, string $canonicalUrl): array
    {
        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Головна',
                'item' => url('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Каталог',
                'item' => url('/catalog'),
            ],
        ];

        if ($category) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $category->name,
                'item' => url('/catalog/'.$category->slug),
            ];
        }

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbs,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $heading,
                'url' => $canonicalUrl,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $heading,
                'itemListElement' => collect($products)->take(24)->values()->map(fn (array $product, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $product['url'],
                    'name' => $product['name'],
                ])->all(),
            ],
        ];
    }

    private function productSchemas(array $product, mixed $category, string $canonicalUrl, string $storeName): array
    {
        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Головна',
                'item' => url('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $category->name ?? 'Каталог',
                'item' => url('/catalog/'.($category->slug ?? '')),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $product['name'],
                'item' => $canonicalUrl,
            ],
        ];

        $images = collect($product['images'] ?? [])
            ->pluck('url')
            ->filter()
            ->values()
            ->all();

        if ($images === [] && filled($product['image_url'] ?? null)) {
            $images[] = $product['image_url'];
        }

        $productSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'image' => $images,
            'description' => trim(strip_tags((string) (($product['short_description'] ?? null) ?: ($product['description'] ?? '')))),
            'sku' => $product['sku'] ?: (string) ($product['id'] ?? ''),
            'brand' => [
                '@type' => 'Brand',
                'name' => $product['brand']['name'] ?? $storeName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => $product['currency'] ?? 'UAH',
                'price' => number_format(((int) ($product['price_cents'] ?? 0)) / 100, 2, '.', ''),
                'availability' => match ($product['stock_status'] ?? Product::STOCK_IN_STOCK) {
                    Product::STOCK_OUT_OF_STOCK => 'https://schema.org/OutOfStock',
                    Product::STOCK_PREORDER => 'https://schema.org/PreOrder',
                    default => 'https://schema.org/InStock',
                },
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ((int) ($product['reviews_count'] ?? 0) > 0 && (float) ($product['rating_average'] ?? 0) > 0) {
            $productSchema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $product['rating_average'], 1),
                'reviewCount' => (int) $product['reviews_count'],
            ];
        }

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbs,
            ],
            $productSchema,
        ];
    }

    private function serializeProduct(Product $product, ?Category $currentCategory = null, array $preferredCategoryIds = []): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();
        $preferredCategory = $preferredCategoryIds !== [] && $product->relationLoaded('categories')
            ? $product->categories->first(fn (Category $candidate): bool => in_array($candidate->id, $preferredCategoryIds, true))
            : null;
        $category = $currentCategory
            ?: $preferredCategory
            ?: $product->primaryCategory
            ?: ($product->relationLoaded('categories') ? $product->categories->first() : null);
        $categorySlug = $category?->slug ?: ($product->primaryCategory?->slug ?: 'catalog');

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price_cents' => $product->price_cents,
            'old_price_cents' => $product->old_price_cents,
            'currency' => $product->currency ?: 'UAH',
            'stock_status' => $product->stock_status,
            'stock_status_label' => $this->stockStatusLabel($product->stock_status),
            'is_featured' => $product->is_featured,
            'is_new' => $product->is_new,
            'is_bestseller' => $product->is_bestseller,
            'brand' => $product->relationLoaded('brand') && $product->brand ? [
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'size_chart' => $this->serializeSizeChart($product),
            'images' => $product->relationLoaded('images')
                ? $product->images
                    ->map(fn (ProductImage $image): array => $this->serializeImage($image, $product))
                    ->values()
                    ->all()
                : [],
            'variants' => $product->relationLoaded('variants')
                ? $product->variants
                    ->map(fn (ProductVariant $variant): array => $this->serializeVariant($variant, $product))
                    ->values()
                    ->all()
                : [],
            'attributes' => $this->serializeProductAttributes($product),
            'reviews' => $this->serializeReviews($product),
            'reviews_count' => $this->reviewsCount($product),
            'rating_average' => $this->ratingAverage($product),
            'category' => $category ? [
                'name' => $category->name,
                'slug' => $category->slug,
            ] : null,
            'url' => url('/catalog/'.$categorySlug.'/'.$product->slug),
            'image_url' => $this->imageUrl($mainImage, $product, 'card'),
            'image_alt' => $mainImage?->alt ?: $product->name,
        ];
    }

    private function serializeImage(ProductImage $image, Product $product): array
    {
        return [
            'id' => $image->id,
            'url' => $this->imageUrl($image, null),
            'thumb_url' => $this->imageUrl($image, null),
            'alt' => $image->alt ?: $product->name,
            'title' => $image->title,
            'is_main' => (bool) $image->is_main,
            'variant_id' => $image->product_variant_id,
        ];
    }

    private function serializeVariant(ProductVariant $variant, Product $product): array
    {
        $image = $variant->relationLoaded('images')
            ? ($variant->images->firstWhere('is_main', true) ?? $variant->images->first())
            : null;

        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'label' => $this->variantLabel($variant),
            'sku' => $variant->sku ?: $product->sku,
            'size' => $variant->size,
            'color_name' => $variant->color_name,
            'color_hex' => $variant->color_hex,
            'price_cents' => $variant->price_cents ?: $product->price_cents,
            'old_price_cents' => $variant->old_price_cents ?: $product->old_price_cents,
            'stock_quantity' => $variant->stock_quantity,
            'is_available' => $product->stock_status !== Product::STOCK_OUT_OF_STOCK,
            'image_url' => $image ? $this->imageUrl($image, null) : null,
            'attributes' => $variant->relationLoaded('attributeValues')
                ? $variant->attributeValues
                    ->map(fn (AttributeValue $value): array => [
                        'name' => $value->attribute?->name,
                        'slug' => $value->attribute?->slug,
                        'value' => $value->value,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    private function serializeProductAttributes(Product $product): array
    {
        $rows = collect();

        if ($product->relationLoaded('brand') && $product->brand) {
            $rows->push([
                'name' => 'Бренд',
                'value' => $product->brand->name,
            ]);
        }

        if ($product->relationLoaded('colorGroup') && $product->colorGroup) {
            $rows->push([
                'name' => 'Колір',
                'value' => $product->colorGroup->name,
            ]);
        }

        if ($product->relationLoaded('attributeValues')) {
            $attributeRows = $product->attributeValues
                ->groupBy(fn (AttributeValue $value): string => (string) ($value->attribute?->id ?: $value->attribute_id))
                ->map(function ($values): array {
                    $first = $values->first();

                    return [
                        'name' => $first->attribute?->name ?: 'Характеристика',
                        'value' => $values
                            ->pluck('value')
                            ->filter()
                            ->unique()
                            ->implode(', '),
                    ];
                })
                ->filter(fn (array $row): bool => $row['value'] !== '')
                ->values();

            $rows = $rows->merge($attributeRows);
        }

        return $rows
            ->unique(fn (array $row): string => mb_strtolower($row['name']).'|'.mb_strtolower($row['value']))
            ->values()
            ->all();
    }

    private function serializeSizeChart(Product $product): ?array
    {
        if (! $product->relationLoaded('sizeChart') || ! $product->sizeChart) {
            return null;
        }

        return [
            'title' => $product->sizeChart->title,
            'description' => $product->sizeChart->description,
            'content_html' => $product->sizeChart->content_html,
            'content_json' => $product->sizeChart->content_json,
            'image_url' => filled($product->sizeChart->image_path)
                ? Storage::disk('public')->url($product->sizeChart->image_path)
                : null,
        ];
    }

    private function serializeReviews(Product $product): array
    {
        if (! $product->relationLoaded('reviews')) {
            return [];
        }

        return $product->reviews
            ->map(fn (Review $review): array => [
                'author_name' => $review->author_name,
                'rating' => (int) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'is_verified_buyer' => (bool) $review->is_verified_buyer,
                'published_at' => $review->published_at?->format('d.m.Y'),
            ])
            ->values()
            ->all();
    }

    private function reviewsCount(Product $product): int
    {
        if ($product->relationLoaded('reviews')) {
            return $product->reviews->count();
        }

        return 0;
    }

    private function ratingAverage(Product $product): ?float
    {
        if (! $product->relationLoaded('reviews') || $product->reviews->isEmpty()) {
            return null;
        }

        return round((float) $product->reviews->avg('rating'), 1);
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $label = collect([$variant->name, $variant->color_name, $variant->size])
            ->filter()
            ->unique()
            ->implode(' / ');

        return $label !== '' ? $label : 'Варіант #'.$variant->id;
    }

    private function freeShippingThresholdCents(): int
    {
        $threshold = DeliveryMethod::query()
            ->where('is_active', true)
            ->whereNotNull('free_from_cents')
            ->min('free_from_cents');

        return (int) ($threshold ?: 120000);
    }

    private function relatedProductCards(Product $product, Category $category): array
    {
        $relatedProducts = $product->relatedProducts;

        if ($relatedProducts->isEmpty()) {
            $relatedProducts = Product::query()
                ->select([
                    'id',
                    'primary_category_id',
                    'name',
                    'slug',
                    'sku',
                    'short_description',
                    'price_cents',
                    'old_price_cents',
                    'currency',
                    'stock_status',
                    'is_new',
                    'is_bestseller',
                    'is_featured',
                    'status',
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
                ->where('id', '!=', $product->id)
                ->where(fn (Builder $query) => $this->whereInCategory($query, $category))
                ->ordered()
                ->limit(4)
                ->get();
        }

        return $relatedProducts
            ->take(4)
            ->map(fn (Product $related): array => $this->serializeProduct($related))
            ->values()
            ->all();
    }

    private function colorOptions(Product $product, Category $category): array
    {
        if (! $product->color_group_id) {
            return [];
        }

        return Product::query()
            ->select([
                'id',
                'primary_category_id',
                'name',
                'slug',
                'sku',
                'short_description',
                'price_cents',
                'old_price_cents',
                'currency',
                'stock_status',
                'color_group_id',
                'color_sort_order',
                'status',
                'published_at',
                'created_at',
            ])
            ->with([
                'primaryCategory:id,name,slug',
                'categories:id,name,slug',
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants.images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'product_variant_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn (Builder $query) => $this->published($query))
            ->where('color_group_id', $product->color_group_id)
            ->orderBy('color_sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Product $option) use ($product, $category): array {
                $mainImage = $option->images->firstWhere('is_main', true) ?? $option->images->first();
                $variant = $option->variants->first();
                $variantImage = $variant?->relationLoaded('images')
                    ? ($variant->images->firstWhere('is_main', true) ?? $variant->images->first())
                    : null;
                $optionCategory = $option->primaryCategory
                    ?: $option->categories->firstWhere('id', $category->id)
                    ?: $option->categories->first();
                $categorySlug = $optionCategory?->slug ?: $category->slug;

                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'color_name' => $variant?->color_name ?: $option->name,
                    'url' => url('/catalog/'.$categorySlug.'/'.$option->slug),
                    'image_url' => $this->imageUrl($variantImage ?: $mainImage, $option, 'swatch'),
                    'image_alt' => ($variantImage ?: $mainImage)?->alt ?: $option->name,
                    'is_active' => $option->id === $product->id,
                ];
            })
            ->values()
            ->all();
    }

    private function stockStatusLabel(?string $status): string
    {
        return match ($status) {
            Product::STOCK_OUT_OF_STOCK => 'Немає в наявності',
            Product::STOCK_PREORDER => 'Передзамовлення',
            default => 'В наявності',
        };
    }

    private function whereInCategory(Builder $query, Category $category): Builder
    {
        return $query->where(function (Builder $inner) use ($category): void {
            $inner->where('primary_category_id', $category->id)
                ->orWhereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereKey($category->id));
        });
    }

    private function whereInCategories(Builder $query, array $categoryIds): Builder
    {
        return $query->where(function (Builder $inner) use ($categoryIds): void {
            $inner->whereIn('primary_category_id', $categoryIds)
                ->orWhereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds));
        });
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

        if ($product && in_array($variant, ['card', 'thumb', 'swatch'], true)) {
            $variantPath = "products/{$product->id}/{$product->slug}-{$variant}.webp";

            if (Storage::disk($image?->disk ?: 'public')->exists($variantPath)) {
                return Storage::disk($image?->disk ?: 'public')->url($variantPath);
            }

            if ($variant === 'swatch') {
                $thumbPath = "products/{$product->id}/{$product->slug}-thumb.webp";

                if (Storage::disk($image?->disk ?: 'public')->exists($thumbPath)) {
                    return Storage::disk($image?->disk ?: 'public')->url($thumbPath);
                }
            }
        }

        return $path !== '' ? Storage::disk($image?->disk ?: 'public')->url($path) : null;
    }

    private function fallbackPromoProduct(string $categorySlug, string $productSlug): ?array
    {
        if ($categorySlug !== 'vulychni-tapochky') {
            return null;
        }

        $products = [
            'zhinochi-tapochky-dlia-vulytsi-siro-blakytnyi' => [
                'name' => 'Жіночі тапочки для вулиці, сіро-блакитний - на резиновій підошві',
                'price_cents' => 45000,
                'old_price_cents' => 62000,
                'short_description' => 'Пухнасті жіночі тапочки на гумовій підошві для тераси, двору, поїздок і щоденних виходів.',
            ],
            'zhinochi-tapochky-dlia-vulytsi-chorni' => [
                'name' => 'Жіночі тапочки для вулиці, чорні - на резиновій підошві',
                'price_cents' => 45000,
                'old_price_cents' => 62000,
                'short_description' => 'Мʼякі чорні тапочки для дому й коротких виходів на вулицю.',
            ],
            'zhinochi-tapochky-dlia-vulytsi-chervoni' => [
                'name' => 'Жіночі тапочки для вулиці, червоні - на резиновій підошві',
                'price_cents' => 45000,
                'old_price_cents' => 62000,
                'short_description' => 'Яскраві пухнасті тапочки на практичній підошві для щоденного комфорту.',
            ],
            'pukhnasti-tapochky-dlia-prohulianok-molochnyi' => [
                'name' => 'Пухнасті тапочки для прогулянок, молочний колір',
                'price_cents' => 52000,
                'old_price_cents' => 69000,
                'short_description' => 'Молочні пухнасті тапочки з теплою посадкою і гумовою підошвою.',
            ],
            'vulychni-tapochky-halluci-rozhevi' => [
                'name' => 'Вуличні тапочки Halluci, рожеві - для щоденних виходів',
                'price_cents' => 48000,
                'old_price_cents' => 65000,
                'short_description' => 'Рожеві тапочки Halluci для легких щоденних виходів.',
            ],
            'miaki-zhinochi-tapochky-na-humovii-pidoshvi-hrafit' => [
                'name' => 'Мʼякі жіночі тапочки на гумовій підошві, графіт',
                'price_cents' => 54000,
                'old_price_cents' => 72000,
                'short_description' => 'Графітові жіночі тапочки на гумовій підошві для дому та вулиці.',
            ],
        ];

        $product = $products[$productSlug] ?? null;

        if (! $product) {
            return null;
        }

        return array_merge($product, [
            'id' => 0,
            'slug' => $productSlug,
            'sku' => null,
            'description' => null,
            'currency' => 'UAH',
            'stock_status' => Product::STOCK_IN_STOCK,
            'stock_status_label' => $this->stockStatusLabel(Product::STOCK_IN_STOCK),
            'is_featured' => false,
            'is_new' => false,
            'is_bestseller' => false,
            'brand' => null,
            'size_chart' => null,
            'images' => [],
            'variants' => [],
            'attributes' => [
                ['name' => 'Форма', 'value' => 'Пухнасті тапочки'],
                ['name' => 'Матеріал підошви', 'value' => 'Гума'],
                ['name' => 'Призначення', 'value' => 'Дім і короткі виходи надвір'],
            ],
            'reviews' => [],
            'reviews_count' => 0,
            'rating_average' => null,
            'category' => [
                'name' => 'Вуличні тапочки',
                'slug' => $categorySlug,
            ],
            'url' => url('/catalog/'.$categorySlug.'/'.$productSlug),
            'image_url' => null,
            'image_alt' => $product['name'],
        ]);
    }

    /**
     * FAQ тримаємо серверним HTML для SEO, швидкості й доступності без зайвого JS.
     */
    private function productFaq(array $product, string $categoryName): array
    {
        $productName = $product['name'] ?? 'цей товар';
        $category = mb_strtolower($categoryName);
        $isOutdoorSlippers = Str::contains($category, ['вулич'])
            || Str::contains(mb_strtolower($productName), ['вулич', 'підошв', 'прогулян']);

        if (! $isOutdoorSlippers) {
            return [
                [
                    'question' => 'Чи є товар у наявності?',
                    'answer' => 'Актуальна наявність показана на сторінці товару. Якщо потрібного розміру або кольору немає, менеджер підкаже найближчу альтернативу після звернення.',
                ],
                [
                    'question' => 'Як підібрати правильний розмір?',
                    'answer' => 'Орієнтуйтесь на свою звичну розмірну сітку та довжину стопи. Якщо ви між двома розмірами, краще уточнити заміри у менеджера перед оформленням замовлення.',
                ],
                [
                    'question' => 'Як доглядати за товаром?',
                    'answer' => 'Рекомендуємо делікатний догляд без агресивної хімії, сильного віджиму й сушіння біля відкритих джерел тепла. Так матеріал довше зберігає вигляд і форму.',
                ],
                [
                    'question' => 'Чи можна обміняти або повернути товар?',
                    'answer' => 'Так, обмін або повернення можливі згідно з умовами магазину, якщо товар не був у використанні, має збережений товарний вигляд і пакування.',
                ],
            ];
        }

        return [
            [
                'question' => 'Чи підходять ці тапочки для вулиці?',
                'answer' => $productName.' розраховані на короткі виходи надвір, терасу, двір, подорожі або щоденне використання біля дому. Для дощу, снігу чи тривалих прогулянок краще обрати спеціалізоване взуття.',
            ],
            [
                'question' => 'Яка підошва у цих тапочок?',
                'answer' => 'У моделі практична гумова підошва, яка краще тримає форму й захищає стопу під час коротких виходів на тверду поверхню. Вона не призначена для екстремального зносу або слизьких покриттів.',
            ],
            [
                'question' => 'Як підібрати правильний розмір?',
                'answer' => 'Орієнтуйтесь на довжину стопи й звичний розмір взуття. Якщо плануєте носити з теплими шкарпетками або вагаєтесь між двома розмірами, краще обрати більший або уточнити заміри у менеджера.',
            ],
            [
                'question' => 'Чи не ковзають тапочки по підлозі?',
                'answer' => 'Гумова підошва дає стабільніше зчеплення, ніж мʼяка домашня підошва. Водночас на мокрій плитці, льоду або дуже гладких поверхнях варто бути обережними.',
            ],
            [
                'question' => 'Як доглядати за пухнастими тапочками?',
                'answer' => 'Очищуйте поверхню мʼякою щіткою або вологою серветкою, без агресивних засобів і сильного намокання. Сушіть природним способом, подалі від батарей та прямих джерел тепла.',
            ],
            [
                'question' => 'Чи можна обміняти або повернути розмір?',
                'answer' => 'Так, якщо тапочки не були у використанні, збережено товарний вигляд і пакування. Перед відправкою менеджер може допомогти перевірити розмір, щоб зменшити ризик обміну.',
            ],
        ];
    }

    private function menuItems(string $slug, bool $withFallback = false): array
    {
        $menu = Menu::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'items' => fn ($query) => $query
                    ->active()
                    ->with('linkable')
                    ->orderByRaw('parent_id is not null')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->first();

        if (! $menu || $menu->items->isEmpty()) {
            return $withFallback ? $this->fallbackMenuItems() : [];
        }

        $itemsByParent = $menu->items->groupBy(fn (MenuItem $item): string => (string) ($item->parent_id ?: 'root'));

        $items = $itemsByParent
            ->get('root', collect())
            ->map(fn (MenuItem $item): array => $this->serializeMenuItem($item, $itemsByParent))
            ->values()
            ->all();

        return $items ?: ($withFallback ? $this->fallbackMenuItems() : []);
    }

    private function serializeMenuItem(MenuItem $item, $itemsByParent): array
    {
        return [
            'title' => $item->title,
            'url' => $this->menuItemUrl($item),
            'target' => $item->target ?: '_self',
            'badge' => $item->badge,
            'children' => $itemsByParent
                ->get((string) $item->id, collect())
                ->map(fn (MenuItem $child): array => $this->serializeMenuItem($child, $itemsByParent))
                ->values()
                ->all(),
        ];
    }

    private function fallbackMenuItems(): array
    {
        return [
            ['title' => 'Головна', 'url' => url('/'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Каталог', 'url' => url('/catalog'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Новинки', 'url' => url('/catalog?filter=new'), 'target' => '_self', 'badge' => 'New', 'children' => []],
            ['title' => 'Акції', 'url' => url('/sale'), 'target' => '_self', 'badge' => 'Sale', 'children' => []],
        ];
    }

    private function menuItemUrl(MenuItem $item): string
    {
        $url = match ($item->type) {
            'category' => $item->linkable instanceof Category ? '/catalog/'.$item->linkable->slug : null,
            'page' => $item->linkable instanceof ContentPage ? '/'.$item->linkable->slug : null,
            default => $item->url,
        };

        $url = trim((string) $url);

        if ($url === '') {
            return '#';
        }

        if (Str::startsWith($url, ['http://', 'https://', '#'])) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    }

    private function published(Builder $query): void
    {
        $query->whereNull('published_at')
            ->orWhere('published_at', '<=', now());
    }
}
