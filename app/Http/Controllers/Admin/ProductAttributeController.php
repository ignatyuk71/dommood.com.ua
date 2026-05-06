<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductAttributeRequest;
use App\Http\Requests\Admin\UpdateProductAttributeRequest;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;
use App\Support\Catalog\CatalogSlug;
use App\Support\Catalog\FilterUrlBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductAttributeController extends Controller
{
    private const TYPE_LABELS = [
        ProductAttribute::TYPE_SELECT => 'Список',
        ProductAttribute::TYPE_MULTI_SELECT => 'Мультивибір',
        ProductAttribute::TYPE_COLOR => 'Колір',
        ProductAttribute::TYPE_BOOLEAN => 'Так / Ні',
    ];

    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $attributes = ProductAttribute::query()
            ->with('values:id,attribute_id,value,slug,color_hex,sort_order')
            ->withCount('values')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('values', function ($valueQuery) use ($search): void {
                            $valueQuery
                                ->where('value', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                        });
                });
            })
            ->ordered()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductAttribute $attribute): array => $this->serializeAttribute($attribute));

        return Inertia::render('Admin/Catalog/Attributes/Index', [
            'attributes' => $attributes,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/Attributes/Form', [
            'mode' => 'create',
            'attribute' => $this->emptyAttribute(),
            'typeOptions' => $this->typeOptions(),
            'filterExampleUrl' => app(FilterUrlBuilder::class)->build('kaptsi', [
                'material' => ['shtuchne-hutro'],
            ]),
        ]);
    }

    public function store(StoreProductAttributeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $attribute = ProductAttribute::query()->create($this->payload($data));
            $this->syncValues($attribute, $data['values'] ?? []);
        });

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Характеристику створено');
    }

    public function edit(ProductAttribute $attribute): Response
    {
        $attribute->load('values');

        return Inertia::render('Admin/Catalog/Attributes/Form', [
            'mode' => 'edit',
            'attribute' => $this->serializeAttribute($attribute, full: true),
            'typeOptions' => $this->typeOptions(),
            'filterExampleUrl' => $this->filterExampleUrl($attribute),
        ]);
    }

    public function update(UpdateProductAttributeRequest $request, ProductAttribute $attribute): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($attribute, $data): void {
            $attribute->update($this->payload($data, $attribute->id));
            $this->syncValues($attribute->refresh(), $data['values'] ?? []);
        });

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Характеристику оновлено');
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        if ($this->attributeIsUsed($attribute)) {
            throw ValidationException::withMessages([
                'attribute' => 'Характеристика вже використовується в товарах або варіантах. Спочатку прибери привʼязки.',
            ]);
        }

        $attribute->delete();

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Характеристику видалено');
    }

    private function payload(array $data, ?int $ignoreId = null): array
    {
        return [
            'name' => $data['name'],
            'slug' => $this->resolveAttributeSlug($data['slug'] ?? null, $data['name'], $ignoreId),
            'type' => $data['type'] ?? ProductAttribute::TYPE_SELECT,
            'is_filterable' => (bool) ($data['is_filterable'] ?? false),
            'is_variant_option' => (bool) ($data['is_variant_option'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function syncValues(ProductAttribute $attribute, array $values): void
    {
        $existingValues = $attribute->values()->get()->keyBy('id');
        $keptIds = [];
        $reservedSlugs = [];

        foreach ($values as $index => $valueData) {
            $value = trim((string) ($valueData['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $valueId = isset($valueData['id']) ? (int) $valueData['id'] : null;

            if ($valueId && ! $existingValues->has($valueId)) {
                throw ValidationException::withMessages([
                    "values.{$index}.id" => 'Це значення не належить поточній характеристиці.',
                ]);
            }

            $attributeValue = $valueId ? $existingValues->get($valueId) : new AttributeValue([
                'attribute_id' => $attribute->id,
            ]);
            $slug = $this->resolveValueSlug(
                $attribute,
                $valueData['slug'] ?? null,
                $value,
                $attributeValue->exists ? $attributeValue->id : null,
                $reservedSlugs,
            );

            $reservedSlugs[] = $slug;

            $attributeValue->fill([
                'attribute_id' => $attribute->id,
                'value' => $value,
                'slug' => $slug,
                'color_hex' => $attribute->type === ProductAttribute::TYPE_COLOR
                    ? $this->nullableString($valueData['color_hex'] ?? null)
                    : null,
                'sort_order' => (int) ($valueData['sort_order'] ?? 0),
            ]);
            $attributeValue->save();
            $keptIds[] = $attributeValue->id;
        }

        $idsToDelete = $existingValues->keys()
            ->diff($keptIds)
            ->values()
            ->all();

        if ($idsToDelete === []) {
            return;
        }

        if ($this->valuesAreUsed($idsToDelete)) {
            throw ValidationException::withMessages([
                'values' => 'Одне або кілька значень уже використовуються в товарах. Спочатку прибери ці привʼязки.',
            ]);
        }

        AttributeValue::query()
            ->whereIn('id', $idsToDelete)
            ->delete();
    }

    private function resolveAttributeSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = trim((string) $slug);
        $base = $base !== '' ? CatalogSlug::make($base) : CatalogSlug::make($name);
        $base = $base !== '' ? $base : 'attribute';
        $candidate = $base;
        $counter = 1;

        while (ProductAttribute::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function resolveValueSlug(
        ProductAttribute $attribute,
        ?string $slug,
        string $value,
        ?int $ignoreId = null,
        array $reservedSlugs = [],
    ): string {
        $base = trim((string) $slug);
        $base = $base !== '' ? CatalogSlug::make($base) : CatalogSlug::make($value);
        $base = $base !== '' ? $base : 'value';
        $candidate = $base;
        $counter = 1;

        while (
            in_array($candidate, $reservedSlugs, true)
            || AttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function serializeAttribute(ProductAttribute $attribute, bool $full = false): array
    {
        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            'type' => $attribute->type,
            'type_label' => self::TYPE_LABELS[$attribute->type] ?? $attribute->type,
            'is_filterable' => $attribute->is_filterable,
            'is_variant_option' => $attribute->is_variant_option,
            'sort_order' => $attribute->sort_order,
            'values_count' => $attribute->values_count ?? $attribute->values->count(),
            'values' => $attribute->values
                ->map(fn (AttributeValue $value): array => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'color_hex' => $value->color_hex,
                    'sort_order' => $value->sort_order,
                ])
                ->values()
                ->all(),
            'created_at' => $full ? $attribute->created_at?->toDateTimeString() : null,
        ];
    }

    private function emptyAttribute(): array
    {
        return [
            'name' => '',
            'slug' => '',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
            'is_variant_option' => false,
            'sort_order' => 0,
            'values' => [
                [
                    'id' => null,
                    'value' => '',
                    'slug' => '',
                    'color_hex' => '',
                    'sort_order' => 0,
                ],
            ],
        ];
    }

    private function typeOptions(): array
    {
        return collect(self::TYPE_LABELS)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function filterExampleUrl(ProductAttribute $attribute): string
    {
        $valueSlug = $attribute->values->first()?->slug ?: 'znachennia';

        return app(FilterUrlBuilder::class)->build('kaptsi', [
            $attribute->slug => [$valueSlug],
        ]);
    }

    private function attributeIsUsed(ProductAttribute $attribute): bool
    {
        return DB::table('product_attribute_values')->where('attribute_id', $attribute->id)->exists()
            || DB::table('product_variant_attribute_values')->where('attribute_id', $attribute->id)->exists();
    }

    private function valuesAreUsed(array $valueIds): bool
    {
        return DB::table('product_attribute_values')->whereIn('attribute_value_id', $valueIds)->exists()
            || DB::table('product_variant_attribute_values')->whereIn('attribute_value_id', $valueIds)->exists();
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
