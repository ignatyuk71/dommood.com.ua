<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Support\Catalog\ProductFilterQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_products_by_product_level_attribute_values(): void
    {
        [$material, $fur, $leather] = $this->attributeWithValues('Матеріал', 'material', [
            'Штучне хутро' => 'shtuchne-hutro',
            'Еко-шкіра' => 'eko-shkira',
        ]);
        [$color, $beige, $black] = $this->attributeWithValues('Колір', 'kolir', [
            'Бежевий' => 'bezhevyi',
            'Чорний' => 'chornyi',
        ]);

        $cozy = Product::query()->create([
            'name' => 'Cozy капці',
            'slug' => 'cozy-kaptsi',
        ]);
        $cozy->attributeValues()->attach($fur->id, ['attribute_id' => $material->id]);
        $cozy->attributeValues()->attach($beige->id, ['attribute_id' => $color->id]);

        $classic = Product::query()->create([
            'name' => 'Classic капці',
            'slug' => 'classic-kaptsi',
        ]);
        $classic->attributeValues()->attach($leather->id, ['attribute_id' => $material->id]);
        $classic->attributeValues()->attach($black->id, ['attribute_id' => $color->id]);

        $slugs = app(ProductFilterQuery::class)
            ->apply(Product::query(), [
                'material' => ['shtuchne-hutro'],
                'kolir' => ['bezhevyi', 'chornyi'],
            ])
            ->pluck('slug')
            ->all();

        $this->assertSame(['cozy-kaptsi'], $slugs);
    }

    public function test_it_filters_products_by_active_variant_attribute_values(): void
    {
        [$size, $size3637, $size3839] = $this->attributeWithValues('Розмір', 'rozmir', [
            '36-37' => '36-37',
            '38-39' => '38-39',
        ], isVariantOption: true);

        $product = Product::query()->create([
            'name' => 'Капці з варіантами',
            'slug' => 'kaptsi-z-variantamy',
        ]);
        $activeVariant = $product->variants()->create([
            'sku' => 'SLP-36',
            'is_active' => true,
        ]);
        $activeVariant->attributeValues()->attach($size3637->id, [
            'attribute_id' => $size->id,
        ]);

        $inactiveVariant = $product->variants()->create([
            'sku' => 'SLP-38',
            'is_active' => false,
        ]);
        $inactiveVariant->attributeValues()->attach($size3839->id, [
            'attribute_id' => $size->id,
        ]);

        $matchingSlugs = app(ProductFilterQuery::class)
            ->apply(Product::query(), ['rozmir' => ['36-37']])
            ->pluck('slug')
            ->all();

        $missingSlugs = app(ProductFilterQuery::class)
            ->apply(Product::query(), ['rozmir' => ['38-39']])
            ->pluck('slug')
            ->all();

        $this->assertSame(['kaptsi-z-variantamy'], $matchingSlugs);
        $this->assertSame([], $missingSlugs);
    }

    public function test_it_ignores_non_filterable_attributes(): void
    {
        [$internal, $value] = $this->attributeWithValues('Внутрішня ознака', 'internal', [
            'Тест' => 'test',
        ], isFilterable: false);
        $product = Product::query()->create([
            'name' => 'Тестовий товар',
            'slug' => 'testovyi-tovar',
        ]);
        $product->attributeValues()->attach($value->id, [
            'attribute_id' => $internal->id,
        ]);

        $slugs = app(ProductFilterQuery::class)
            ->apply(Product::query(), ['internal' => ['test']])
            ->pluck('slug')
            ->all();

        $this->assertSame([], $slugs);
    }

    private function attributeWithValues(
        string $name,
        string $slug,
        array $values,
        bool $isFilterable = true,
        bool $isVariantOption = false,
    ): array {
        $attribute = ProductAttribute::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_filterable' => $isFilterable,
            'is_variant_option' => $isVariantOption,
        ]);

        $createdValues = collect($values)
            ->map(fn (string $valueSlug, string $valueName) => $attribute->values()->create([
                'value' => $valueName,
                'slug' => $valueSlug,
            ]))
            ->values()
            ->all();

        return [$attribute, ...$createdValues];
    }
}
