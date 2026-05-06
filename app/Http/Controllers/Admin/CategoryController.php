<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $categories = Category::query()
            ->with(['parent:id,name'])
            ->withCount(['children', 'products', 'primaryProducts'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Category $category): array => $this->serializeCategory($category));

        return Inertia::render('Admin/Catalog/Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/Categories/Form', [
            'mode' => 'create',
            'category' => $this->emptyCategory(),
            'parentOptions' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Category::query()->create($this->payload($data));

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Категорію створено');
    }

    public function edit(Category $category): Response
    {
        $category->load(['parent:id,name']);

        return Inertia::render('Admin/Catalog/Categories/Form', [
            'mode' => 'edit',
            'category' => $this->serializeCategory($category, full: true),
            'parentOptions' => $this->categoryOptions($category->id),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        if ($parentId && $this->wouldCreateCycle($category->id, (int) $parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Не можна вибрати дочірню категорію як батьківську.',
            ]);
        }

        $category->update($this->payload($data, $category->id));

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Категорію оновлено');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Спочатку перенеси або видали дочірні категорії.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Категорію видалено');
    }

    private function payload(array $data, ?int $ignoreId = null): array
    {
        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $ignoreId),
            'description' => $this->nullableString($data['description'] ?? null),
            'image_path' => $this->nullableString($data['image_path'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $this->nullableString($data['meta_title'] ?? null),
            'meta_description' => $this->nullableString($data['meta_description'] ?? null),
            'seo_text' => $this->nullableString($data['seo_text'] ?? null),
        ];
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = trim((string) $slug);
        $base = $base !== '' ? Str::slug($base) : Str::slug($name);
        $base = $base !== '' ? $base : 'category';
        $candidate = $base;
        $counter = 1;

        while (Category::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function categoryOptions(?int $excludeId = null): array
    {
        $categories = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug'])
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories
            ->reject(fn (Category $category) => $excludeId && $category->id === $excludeId)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $this->optionLabel($category, $categories),
            ])
            ->values()
            ->all();
    }

    private function optionLabel(Category $category, $categories): string
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

    private function wouldCreateCycle(int $categoryId, int $parentId): bool
    {
        $visited = [];

        while ($parentId) {
            if ($parentId === $categoryId || in_array($parentId, $visited, true)) {
                return true;
            }

            $visited[] = $parentId;
            $parentId = (int) (Category::query()->whereKey($parentId)->value('parent_id') ?? 0);
        }

        return false;
    }

    private function serializeCategory(Category $category, bool $full = false): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
            ] : null,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_path' => $category->image_path,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'seo_text' => $full ? $category->seo_text : null,
            'children_count' => $category->children_count ?? 0,
            'products_count' => $category->products_count ?? 0,
            'primary_products_count' => $category->primary_products_count ?? 0,
            'created_at' => $category->created_at?->toDateTimeString(),
        ];
    }

    private function emptyCategory(): array
    {
        return [
            'parent_id' => null,
            'name' => '',
            'slug' => '',
            'description' => '',
            'image_path' => '',
            'is_active' => true,
            'sort_order' => 0,
            'meta_title' => '',
            'meta_description' => '',
            'seo_text' => '',
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
