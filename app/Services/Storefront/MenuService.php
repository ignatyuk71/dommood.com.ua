<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MenuService
{
    /**
     * Return a serialized tree of items for the given menu slug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSlug(string $slug, bool $withFallback = false): array
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

        $itemsByParent = $menu->items->groupBy(
            fn (MenuItem $item): string => (string) ($item->parent_id ?: 'root')
        );

        $items = $itemsByParent
            ->get('root', collect())
            ->map(fn (MenuItem $item): array => $this->serializeMenuItem($item, $itemsByParent))
            ->values()
            ->all();

        return $items ?: ($withFallback ? $this->fallbackMenuItems() : []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackMenuItems(): array
    {
        return [
            ['title' => 'Головна', 'url' => url('/'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Каталог', 'url' => url('/catalog'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Новинки', 'url' => url('/catalog?filter=new'), 'target' => '_self', 'badge' => 'New', 'children' => []],
        ];
    }

    /**
     * @param  Collection<string, Collection<int, MenuItem>>  $itemsByParent
     * @return array<string, mixed>
     */
    private function serializeMenuItem(MenuItem $item, Collection $itemsByParent): array
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
