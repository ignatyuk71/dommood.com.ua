<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\SiteSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HomeController extends Controller
{
    public function __construct(private readonly SiteSettingsService $settings) {}

    public function __invoke(Request $request): View|SymfonyResponse
    {
        $storeSettings = $this->settings->get('store');
        $user = $request->user();
        $canPreviewStorefront = $user && in_array($user->role, ['admin', 'manager'], true);

        if (($storeSettings['maintenance_mode'] ?? false) && ! $canPreviewStorefront) {
            return response()->view('storefront.maintenance', [
                'storeName' => $storeSettings['store_name'] ?? 'DomMood',
                'message' => $storeSettings['maintenance_message'] ?: 'Ми оновлюємо сайт і скоро повернемося.',
                'supportEmail' => $storeSettings['support_email'] ?? null,
                'canLogin' => Route::has('login'),
            ], 503);
        }

        $products = $this->homeProducts();
        $categories = $this->homeCategories($products);
        $outdoorCategory = $this->categoryBySlug('zhinochi-kaptsi-dlia-vulytsi');
        $pajamasCategory = $this->categoryBySlug('zhinochi-pizhamy');

        return view('storefront.home', [
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'homeBanners' => $this->homeBanners($storeSettings, $categories),
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility', withFallback: false),
            'mobileMenuItems' => $this->menuItems('mobile', withFallback: false),
            'footerMenuItems' => $this->menuItems('footer', withFallback: false),
            'categories' => $categories,
            'products' => $products,
            'newProducts' => $this->homeNewProducts(),
            'outdoorCategory' => $outdoorCategory ? $this->serializeCategory($outdoorCategory) : null,
            'outdoorPromoProducts' => $this->categoryProducts($outdoorCategory),
            'pajamasCategory' => $pajamasCategory ? $this->serializeCategory($pajamasCategory) : null,
            'pajamasPromoProducts' => $this->categoryProducts($pajamasCategory),
        ]);
    }

    private function homeProducts(): array
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
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn ($query) => $this->published($query))
            ->orderByDesc('is_featured')
            ->orderByDesc('is_bestseller')
            ->ordered()
            ->limit(12)
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values()
            ->all();
    }

    private function homeNewProducts(): array
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
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where('is_new', true)
            ->where(fn ($query) => $this->published($query))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->ordered()
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values()
            ->all();
    }

    private function homeBanners(array $settings, array $categories): array
    {
        $placements = ['home_hero_main', 'home_hero_side_top', 'home_hero_side_bottom'];
        $banners = Banner::query()
            ->whereIn('placement', [...$placements, 'home_hero'])
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->groupBy('placement');

        return [
            'main' => $this->homeBannerSlot($banners->get('home_hero_main')?->first() ?? $banners->get('home_hero')?->first(), $categories[0] ?? null, $settings),
            'side_top' => $this->homeBannerSlot($banners->get('home_hero_side_top')?->first(), $categories[1] ?? ($categories[0] ?? null), $settings),
            'side_bottom' => $this->homeBannerSlot($banners->get('home_hero_side_bottom')?->first(), $categories[2] ?? ($categories[1] ?? null), $settings),
        ];
    }

    private function homeBannerSlot(?Banner $banner, ?array $fallbackCategory, array $settings): array
    {
        return [
            'title' => $banner?->title ?: ($fallbackCategory['name'] ?? ($settings['store_name'] ?? 'DomMood')),
            'subtitle' => $banner?->button_text ?: $this->categoryProductsLabel($fallbackCategory),
            'image_url' => $this->storageUrl($banner?->image_path) ?: ($fallbackCategory['image_url'] ?? null),
            'mobile_image_url' => $this->storageUrl($banner?->mobile_image_path),
            'url' => $this->absoluteUrl((string) ($banner?->url ?: ($fallbackCategory['url'] ?? '/catalog'))),
            'alt' => $banner?->title ?: ($fallbackCategory['name'] ?? ($settings['store_name'] ?? 'DomMood')),
            'is_banner' => $banner !== null,
        ];
    }

    private function categoryProductsLabel(?array $category): ?string
    {
        $count = (int) ($category['products_count'] ?? 0);

        if ($count <= 0) {
            return null;
        }

        return $count.' '.$this->plural($count, 'товар', 'товари', 'товарів');
    }

    private function homeCategories(array $products): array
    {
        $fallbackImages = collect($products)
            ->filter(fn (array $product): bool => filled($product['category']['id'] ?? null) && filled($product['image_large_url'] ?? null))
            ->groupBy(fn (array $product): int => (int) $product['category']['id'])
            ->map(fn ($group): ?string => $group->first()['image_large_url'] ?? null);

        return Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order'])
            ->where('is_active', true)
            ->withCount([
                'primaryProducts as active_products_count' => fn ($query) => $query
                    ->active()
                    ->where(fn ($inner) => $this->published($inner)),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($fallbackImages): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products_count' => (int) $category->active_products_count,
                    'url' => url('/catalog/'.$category->slug),
                    'image_url' => $this->storageUrl($category->image_path) ?: $fallbackImages->get($category->id),
                ];
            })
            ->values()
            ->all();
    }

    private function categoryBySlug(string $slug): ?Category
    {
        return Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    private function categoryProducts(?Category $category, int $limit = 12): array
    {
        if (! $category) {
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
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'alt', 'title', 'is_main', 'sort_order'])
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->active()
            ->where(fn ($query) => $this->published($query))
            ->where(function (Builder $query) use ($category): void {
                $query->where('primary_category_id', $category->id)
                    ->orWhereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereKey($category->id));
            })
            ->ordered()
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->serializePromoProduct($product, $category))
            ->values()
            ->all();
    }

    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'url' => url('/catalog/'.$category->slug),
            'image_url' => $this->storageUrl($category->image_path),
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

    private function serializeProduct(Product $product): array
    {
        $mainImage = $product->images->firstWhere('is_main', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description,
            'price_cents' => $product->price_cents,
            'old_price_cents' => $product->old_price_cents,
            'currency' => $product->currency ?: 'UAH',
            'stock_status' => $product->stock_status,
            'stock_status_label' => $this->stockStatusLabel($product->stock_status),
            'is_featured' => $product->is_featured,
            'is_new' => $product->is_new,
            'is_bestseller' => $product->is_bestseller,
            'category' => $product->primaryCategory ? [
                'id' => $product->primaryCategory->id,
                'name' => $product->primaryCategory->name,
                'slug' => $product->primaryCategory->slug,
            ] : null,
            'image_url' => $this->imageUrl($mainImage, $product, 'card'),
            'image_large_url' => $this->imageUrl($mainImage, $product),
            'image_alt' => $mainImage?->alt ?: $product->name,
        ];
    }

    private function serializePromoProduct(Product $product, Category $category): array
    {
        $payload = $this->serializeProduct($product);
        $oldPrice = (int) ($payload['old_price_cents'] ?? 0);
        $price = (int) ($payload['price_cents'] ?? 0);

        return [
            ...$payload,
            'url' => url('/catalog/'.$category->slug.'/'.$product->slug),
            'discount' => $oldPrice > $price && $price > 0
                ? '-'.(int) round((($oldPrice - $price) / $oldPrice) * 100).'%'
                : null,
        ];
    }

    private function stockStatusLabel(?string $status): string
    {
        return match ($status) {
            Product::STOCK_OUT_OF_STOCK => 'Немає в наявності',
            Product::STOCK_PREORDER => 'Передзамовлення',
            default => 'В наявності',
        };
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
            $variantPath = $this->productImageVersionPath($product, $variant);

            if (Storage::disk($image?->disk ?: 'public')->exists($variantPath)) {
                return Storage::disk($image?->disk ?: 'public')->url($variantPath);
            }

            if ($variant === 'swatch') {
                $thumbPath = $this->productImageVersionPath($product, 'thumb');

                if (Storage::disk($image?->disk ?: 'public')->exists($thumbPath)) {
                    return Storage::disk($image?->disk ?: 'public')->url($thumbPath);
                }
            }
        }

        return $path !== '' ? Storage::disk($image?->disk ?: 'public')->url($path) : null;
    }

    private function productImageVersionPath(Product $product, string $variant): string
    {
        $slug = trim((string) $product->slug) ?: 'product-'.$product->id;

        return "products/{$product->id}/{$slug}-{$variant}.webp";
    }

    private function storageUrl(?string $path, string $disk = 'public'): ?string
    {
        $path = trim((string) $path);

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

        return $path !== '' ? Storage::disk($disk)->url($path) : null;
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return url('/catalog');
        }

        if (Str::startsWith($url, ['http://', 'https://', '#'])) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ! in_array($mod100, [12, 13, 14], true)) {
            return $few;
        }

        return $many;
    }

    private function published($query): void
    {
        $query->whereNull('published_at')
            ->orWhere('published_at', '<=', now());
    }
}
