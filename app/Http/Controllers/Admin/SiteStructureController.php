<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SiteStructureController extends Controller
{
    private const MENUS = [
        'main' => [
            'name' => 'Меню',
            'description' => 'Головна навігація сайту.',
        ],
        'utility' => [
            'name' => 'Верхня полоска',
            'description' => 'Сервісні посилання над основною шапкою.',
        ],
        'footer' => [
            'name' => 'Footer',
            'description' => 'Посилання у футері сайту.',
        ],
        'mobile' => [
            'name' => 'Mobile menu',
            'description' => 'Окрема структура для мобільного меню.',
        ],
    ];

    public function show(string $menu): Response
    {
        $menuModel = $this->menuModel($menu);
        $items = $this->menuItems($menuModel);

        return Inertia::render('Admin/SiteStructure/Index', [
            'menu' => $this->serializeMenu($menu, $menuModel),
            'menus' => collect(self::MENUS)
                ->map(fn (array $config, string $key): array => [
                    'key' => $key,
                    'name' => $config['name'],
                    'description' => $config['description'],
                    'url' => route('admin.site-structure.show', $key),
                ])
                ->values(),
            'tree' => $this->buildTree($items),
            'items' => $items->map(fn (MenuItem $item): array => $this->serializeItem($item))->values(),
            'categoryOptions' => $this->categoryOptions(),
            'pageOptions' => $this->pageOptions(),
        ]);
    }

    public function store(Request $request, string $menu): RedirectResponse
    {
        $menuModel = $this->menuModel($menu);
        $data = $this->validatedItem($request, $menuModel);
        $linkable = $this->resolveLinkable($data);

        $item = MenuItem::query()->create([
            ...$this->itemPayload($data, $linkable),
            'menu_id' => $menuModel->id,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $this->nextSortOrder($menuModel, $data['parent_id'] ?? null),
        ]);

        app(AdminActivityLogger::class)->log(
            $request,
            'site_structure.menu_item_created',
            $item,
            newValues: $this->menuItemActivitySnapshot($item, $menuModel),
            description: 'Менеджер створив пункт меню',
        );

        return redirect()
            ->route('admin.site-structure.show', $menu)
            ->with('success', 'Пункт меню створено');
    }

    public function update(Request $request, string $menu, MenuItem $item): RedirectResponse
    {
        $menuModel = $this->menuModel($menu);
        $this->ensureMenuItem($menuModel, $item);
        $oldValues = $this->menuItemActivitySnapshot($item, $menuModel);

        $data = $this->validatedItem($request, $menuModel, $item);
        $linkable = $this->resolveLinkable($data);

        if (($data['parent_id'] ?? null) && $this->wouldCreateCycle($item->id, (int) $data['parent_id'])) {
            throw ValidationException::withMessages([
                'parent_id' => 'Не можна вибрати дочірній пункт як батьківський.',
            ]);
        }

        $item->update([
            ...$this->itemPayload($data, $linkable),
            'parent_id' => $data['parent_id'] ?? null,
        ]);
        $item->refresh();

        app(AdminActivityLogger::class)->log(
            $request,
            'site_structure.menu_item_updated',
            $item,
            oldValues: $oldValues,
            newValues: $this->menuItemActivitySnapshot($item, $menuModel),
            description: 'Менеджер оновив пункт меню',
        );

        return redirect()
            ->route('admin.site-structure.show', $menu)
            ->with('success', 'Пункт меню оновлено');
    }

    public function destroy(Request $request, string $menu, MenuItem $item): RedirectResponse
    {
        $menuModel = $this->menuModel($menu);
        $this->ensureMenuItem($menuModel, $item);
        $oldValues = $this->menuItemActivitySnapshot($item, $menuModel);
        $childrenCount = $item->children()->count();

        DB::transaction(function () use ($menuModel, $item): void {
            $nextSort = $this->nextSortOrder($menuModel);

            $item->children()
                ->orderBy('sort_order')
                ->get()
                ->each(function (MenuItem $child) use (&$nextSort): void {
                    // Дочірні пункти переносимо в корінь, щоб менеджер не втрачав структуру.
                    $child->update([
                        'parent_id' => null,
                        'sort_order' => $nextSort++,
                    ]);
                });

            $item->delete();
        });

        app(AdminActivityLogger::class)->log(
            $request,
            'site_structure.menu_item_deleted',
            $item,
            oldValues: $oldValues,
            newValues: ['deleted' => true],
            metadata: [
                'children_moved_to_root' => $childrenCount,
            ],
            description: 'Менеджер видалив пункт меню',
        );

        return redirect()
            ->route('admin.site-structure.show', $menu)
            ->with('success', 'Пункт меню видалено');
    }

    public function reorder(Request $request, string $menu): JsonResponse
    {
        $menuModel = $this->menuModel($menu);

        $data = $request->validate([
            'tree' => ['required', 'array'],
        ]);

        $nodes = $this->flattenTreePayload($data['tree']);
        $ids = collect($nodes)->pluck('id');

        if ($ids->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'tree' => 'У дереві меню є дублікати пунктів.',
            ]);
        }

        $existingIds = $menuModel->items()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->sort()
            ->values();

        if ($existingIds->count() !== $ids->count() || $existingIds->all() !== $ids->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'tree' => 'Деякі пункти не належать до цього меню.',
            ]);
        }

        if ($menuModel->items()->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'tree' => 'Передано неповну структуру меню.',
            ]);
        }
        $oldStructure = $this->menuStructureActivitySnapshot($menuModel);

        DB::transaction(function () use ($nodes): void {
            foreach ($nodes as $node) {
                MenuItem::query()
                    ->whereKey($node['id'])
                    ->update([
                        'parent_id' => $node['parent_id'],
                        'sort_order' => $node['sort_order'],
                    ]);
            }
        });
        $menuModel->refresh();

        app(AdminActivityLogger::class)->log(
            $request,
            'site_structure.menu_reordered',
            $menuModel,
            oldValues: [
                'menu' => self::MENUS[$menu]['name'],
                'structure' => $oldStructure,
            ],
            newValues: [
                'menu' => self::MENUS[$menu]['name'],
                'structure' => $this->menuStructureActivitySnapshot($menuModel),
            ],
            description: 'Менеджер змінив порядок меню',
        );

        return response()->json([
            'message' => 'Порядок меню збережено',
        ]);
    }

    private function menuModel(string $menu): Menu
    {
        abort_unless(array_key_exists($menu, self::MENUS), 404);

        return Menu::query()->firstOrCreate(
            ['slug' => $menu],
            [
                'name' => self::MENUS[$menu]['name'],
                'is_active' => true,
                'settings' => [
                    'description' => self::MENUS[$menu]['description'],
                ],
            ],
        );
    }

    private function menuItems(Menu $menu): Collection
    {
        return $menu->items()
            ->with('linkable')
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function serializeMenu(string $key, Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'key' => $key,
            'name' => self::MENUS[$key]['name'],
            'description' => self::MENUS[$key]['description'],
            'is_active' => $menu->is_active,
        ];
    }

    private function buildTree(Collection $items): array
    {
        $children = $items->groupBy(fn (MenuItem $item): int => (int) ($item->parent_id ?? 0));

        $build = function (int $parentId = 0) use (&$build, $children): array {
            return $children
                ->get($parentId, collect())
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['title', 'asc'],
                ])
                ->map(function (MenuItem $item) use (&$build): array {
                    return [
                        ...$this->serializeItem($item),
                        'children' => $build($item->id),
                    ];
                })
                ->values()
                ->all();
        };

        return $build();
    }

    private function serializeItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'title' => $item->title,
            'type' => $item->type,
            'type_label' => $this->typeLabel($item->type),
            'linkable_id' => $item->linkable_id,
            'linkable_title' => $this->linkableTitle($item),
            'url' => $item->url,
            'resolved_url' => $this->resolvedUrl($item),
            'target' => $item->target,
            'badge' => $item->badge,
            'is_active' => $item->is_active,
            'sort_order' => $item->sort_order,
        ];
    }

    private function menuItemActivitySnapshot(MenuItem $item, Menu $menu): array
    {
        return [
            'id' => $item->id,
            'menu' => self::MENUS[$menu->slug]['name'] ?? $menu->name,
            'title' => $item->title,
            'type' => $this->typeLabel($item->type),
            'url' => $this->resolvedUrl($item) ?? $item->url,
            'target' => $item->target,
            'badge' => $item->badge,
            'is_active' => (bool) $item->is_active,
            'parent_id' => $item->parent_id,
            'sort_order' => (int) $item->sort_order,
        ];
    }

    private function menuStructureActivitySnapshot(Menu $menu): array
    {
        return $menu->items()
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title', 'sort_order'])
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'title' => $item->title,
                'sort_order' => (int) $item->sort_order,
            ])
            ->values()
            ->all();
    }

    private function validatedItem(Request $request, Menu $menu, ?MenuItem $item = null): array
    {
        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')->where(fn ($query) => $query->where('menu_id', $menu->id)),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['custom_url', 'category', 'page'])],
            'linkable_id' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:255'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'badge' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        if ($item && (int) ($data['parent_id'] ?? 0) === $item->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Пункт не може бути батьківським сам для себе.',
            ]);
        }

        if ($data['type'] === 'custom_url' && ! $this->isValidCustomUrl($data['url'] ?? null)) {
            throw ValidationException::withMessages([
                'url' => 'Вкажи URL у форматі /path, #anchor або https://example.com.',
            ]);
        }

        if ($data['type'] === 'category') {
            $request->validate([
                'linkable_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            ]);
        }

        if ($data['type'] === 'page') {
            $request->validate([
                'linkable_id' => ['required', 'integer', Rule::exists('content_pages', 'id')->whereNull('deleted_at')],
            ]);
        }

        return $data;
    }

    private function resolveLinkable(array $data): Category|ContentPage|null
    {
        return match ($data['type']) {
            'category' => Category::query()->findOrFail($data['linkable_id']),
            'page' => ContentPage::query()->findOrFail($data['linkable_id']),
            default => null,
        };
    }

    private function itemPayload(array $data, Category|ContentPage|null $linkable): array
    {
        $title = trim((string) ($data['title'] ?? ''));

        return [
            'title' => $title !== '' ? $title : $this->fallbackTitle($linkable),
            'type' => $data['type'],
            'linkable_type' => $linkable ? $linkable::class : null,
            'linkable_id' => $linkable?->id,
            'url' => $data['type'] === 'custom_url' ? trim((string) $data['url']) : null,
            'target' => $data['target'] ?? '_self',
            'badge' => $this->nullableString($data['badge'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function fallbackTitle(Category|ContentPage|null $linkable): string
    {
        return match (true) {
            $linkable instanceof Category => $linkable->name,
            $linkable instanceof ContentPage => $linkable->title,
            default => 'Пункт меню',
        };
    }

    private function flattenTreePayload(array $tree): array
    {
        $nodes = [];

        $walk = function (array $items, ?int $parentId = null, int $depth = 0) use (&$walk, &$nodes): void {
            if ($depth > 4) {
                throw ValidationException::withMessages([
                    'tree' => 'Максимальна вкладеність меню — 5 рівнів.',
                ]);
            }

            foreach ($items as $position => $item) {
                if (! is_array($item) || ! isset($item['id'])) {
                    throw ValidationException::withMessages([
                        'tree' => 'Некоректна структура меню.',
                    ]);
                }

                $id = (int) $item['id'];

                $nodes[] = [
                    'id' => $id,
                    'parent_id' => $parentId,
                    'sort_order' => $position,
                ];

                $children = $item['children'] ?? [];

                if (! is_array($children)) {
                    throw ValidationException::withMessages([
                        'tree' => 'Некоректний список дочірніх пунктів.',
                    ]);
                }

                $walk($children, $id, $depth + 1);
            }
        };

        $walk($tree);

        return $nodes;
    }

    private function nextSortOrder(Menu $menu, ?int $parentId = null): int
    {
        return (int) $menu->items()
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;
    }

    private function wouldCreateCycle(int $itemId, int $parentId): bool
    {
        $visited = [];

        while ($parentId) {
            if ($parentId === $itemId || in_array($parentId, $visited, true)) {
                return true;
            }

            $visited[] = $parentId;
            $parentId = (int) (MenuItem::query()->whereKey($parentId)->value('parent_id') ?? 0);
        }

        return false;
    }

    private function ensureMenuItem(Menu $menu, MenuItem $item): void
    {
        abort_unless((int) $item->menu_id === (int) $menu->id, 404);
    }

    private function isValidCustomUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function typeLabel(string $type): string
    {
        return [
            'custom_url' => 'URL',
            'category' => 'Категорія',
            'page' => 'Сторінка',
        ][$type] ?? 'URL';
    }

    private function linkableTitle(MenuItem $item): ?string
    {
        return match ($item->type) {
            'category' => $item->linkable instanceof Category ? $item->linkable->name : null,
            'page' => $item->linkable instanceof ContentPage ? $item->linkable->title : null,
            default => null,
        };
    }

    private function resolvedUrl(MenuItem $item): ?string
    {
        return match ($item->type) {
            'category' => $item->linkable instanceof Category ? '/catalog/'.$item->linkable->slug : null,
            'page' => $item->linkable instanceof ContentPage ? '/'.$item->linkable->slug : null,
            default => $item->url,
        };
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
                'label' => $this->categoryOptionLabel($category, $categories),
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    private function categoryOptionLabel(Category $category, Collection $categories): string
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

    private function pageOptions(): array
    {
        return ContentPage::query()
            ->select(['id', 'title', 'slug', 'status'])
            ->orderBy('title')
            ->get()
            ->map(fn (ContentPage $page): array => [
                'id' => $page->id,
                'label' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
            ])
            ->values()
            ->all();
    }
}
