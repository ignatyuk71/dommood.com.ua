<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Seo\SeoResolver;
use App\Services\SiteSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __construct(
        private readonly SeoResolver $seo,
        private readonly SiteSettingsService $settings,
    ) {
    }

    public function show(string $slug): View
    {
        $page = ContentPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $storeSettings = $this->settings->get('store');

        return view('storefront.page', [
            'page' => $page,
            'seo' => $this->seo->metaForPage($page),
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility'),
            'mobileMenuItems' => $this->menuItems('mobile'),
            'footerMenuItems' => $this->menuItems('footer'),
        ]);
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
}
