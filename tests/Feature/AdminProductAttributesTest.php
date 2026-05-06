<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_filterable_attribute_with_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.attributes.store'), [
            'name' => 'Матеріал',
            'slug' => 'material',
            'type' => ProductAttribute::TYPE_MULTI_SELECT,
            'is_filterable' => true,
            'is_variant_option' => false,
            'sort_order' => 20,
            'values' => [
                [
                    'value' => 'Штучне хутро',
                    'slug' => 'shtuchne-hutro',
                    'sort_order' => 10,
                ],
                [
                    'value' => 'Еко-шкіра',
                    'slug' => '',
                    'sort_order' => 20,
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHas('success', 'Характеристику створено');

        $attribute = ProductAttribute::query()->where('slug', 'material')->firstOrFail();

        $this->assertTrue($attribute->is_filterable);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->id,
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->id,
            'value' => 'Еко-шкіра',
            'slug' => 'eko-shkira',
        ]);
    }

    public function test_admin_can_update_attribute_values_and_keep_unique_value_slugs(): void
    {
        $user = User::factory()->create();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Сезон',
            'slug' => 'sezon',
        ]);
        $winter = $attribute->values()->create([
            'value' => 'Зима',
            'slug' => 'zyma',
        ]);
        $attribute->values()->create([
            'value' => 'Літо',
            'slug' => 'lito',
        ]);

        $response = $this->actingAs($user)->put(route('admin.attributes.update', $attribute), [
            'name' => 'Сезонність',
            'slug' => 'sezonnist',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
            'is_variant_option' => false,
            'values' => [
                [
                    'id' => $winter->id,
                    'value' => 'Зимові',
                    'slug' => 'zyma',
                    'sort_order' => 10,
                ],
                [
                    'value' => 'Зима',
                    'slug' => 'zyma',
                    'sort_order' => 20,
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHas('success', 'Характеристику оновлено');

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
            'name' => 'Сезонність',
            'slug' => 'sezonnist',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'id' => $winter->id,
            'value' => 'Зимові',
            'slug' => 'zyma',
        ]);
        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->id,
            'value' => 'Зима',
            'slug' => 'zyma-1',
        ]);
        $this->assertDatabaseMissing('attribute_values', [
            'attribute_id' => $attribute->id,
            'slug' => 'lito',
        ]);
    }

    public function test_admin_cannot_remove_attribute_value_used_by_product(): void
    {
        $user = User::factory()->create();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
        ]);
        $value = $attribute->values()->create([
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);
        $product = Product::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
        ]);
        $product->attributeValues()->attach($value->id, [
            'attribute_id' => $attribute->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.attributes.update', $attribute), [
            'name' => 'Матеріал',
            'slug' => 'material',
            'type' => ProductAttribute::TYPE_SELECT,
            'is_filterable' => true,
            'is_variant_option' => false,
            'values' => [],
        ]);

        $response->assertSessionHasErrors('values');

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->id,
        ]);
    }

    public function test_admin_cannot_delete_attribute_used_by_product(): void
    {
        $user = User::factory()->create();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
        ]);
        $value = $attribute->values()->create([
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);
        $product = Product::query()->create([
            'name' => 'Домашні капці',
            'slug' => 'domashni-kaptsi',
        ]);
        $product->attributeValues()->attach($value->id, [
            'attribute_id' => $attribute->id,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.attributes.destroy', $attribute));

        $response->assertSessionHasErrors('attribute');
        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
        ]);
    }

    public function test_attributes_index_is_available_for_admin(): void
    {
        $user = User::factory()->create();

        ProductAttribute::query()->create([
            'name' => 'Матеріал',
            'slug' => 'material',
        ])->values()->create([
            'value' => 'Штучне хутро',
            'slug' => 'shtuchne-hutro',
        ]);

        $this->actingAs($user)
            ->get(route('admin.attributes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/Attributes/Index', false)
                ->where('attributes.data.0.slug', 'material')
                ->where('attributes.data.0.values.0.slug', 'shtuchne-hutro')
            );
    }
}
