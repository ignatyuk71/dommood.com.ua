<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductColorGroupRequest;
use App\Http\Requests\Admin\UpdateProductColorGroupRequest;
use App\Models\ProductColorGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductColorGroupController extends Controller
{
    public function index(): Response
    {
        $groups = ProductColorGroup::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductColorGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'description' => $group->description,
                'is_active' => $group->is_active,
                'sort_order' => $group->sort_order,
                'products_count' => $group->products_count,
            ]);

        return Inertia::render('Admin/Catalog/ColorGroups/Index', [
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/ColorGroups/Form', [
            'mode' => 'create',
            'group' => [
                'name' => '',
                'code' => '',
                'description' => '',
                'is_active' => true,
                'sort_order' => 0,
            ],
        ]);
    }

    public function store(StoreProductColorGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ProductColorGroup::query()->create([
            'name' => $data['name'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.color-groups.index')
            ->with('success', 'Групу кольорів створено');
    }

    public function edit(ProductColorGroup $color_group): Response
    {
        return Inertia::render('Admin/Catalog/ColorGroups/Form', [
            'mode' => 'edit',
            'group' => [
                'id' => $color_group->id,
                'name' => $color_group->name,
                'code' => $color_group->code,
                'description' => $color_group->description,
                'is_active' => $color_group->is_active,
                'sort_order' => $color_group->sort_order,
            ],
        ]);
    }

    public function update(UpdateProductColorGroupRequest $request, ProductColorGroup $color_group): RedirectResponse
    {
        $data = $request->validated();

        $color_group->update([
            'name' => $data['name'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['name'], $color_group->id),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.color-groups.index')
            ->with('success', 'Групу кольорів оновлено');
    }

    public function destroy(ProductColorGroup $color_group): RedirectResponse
    {
        $color_group->delete();

        return redirect()
            ->route('admin.color-groups.index')
            ->with('success', 'Групу кольорів видалено');
    }

    private function resolveCode(?string $code, string $name, ?int $ignoreId = null): string
    {
        $base = trim((string) $code);
        $base = $base !== '' ? $base : Str::slug($name, '_');
        $base = $base !== '' ? $base : 'color_group';
        $candidate = $base;
        $counter = 1;

        while (ProductColorGroup::query()
            ->where('code', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'_'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
