<?php

namespace Tests\Feature;

use App\Models\ProductColorGroup;
use App\Models\SizeChart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogOptionGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_color_group(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.color-groups.store'), [
            'name' => 'Cozy fleece',
            'code' => '',
            'description' => 'Одна модель у кількох кольорах',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response->assertRedirect(route('admin.color-groups.index'));

        $this->assertDatabaseHas('product_color_groups', [
            'name' => 'Cozy fleece',
            'code' => 'cozy_fleece',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_create_size_chart_with_table_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.size-charts.store'), [
            'title' => 'Капці жіночі',
            'code' => 'slippers_women',
            'description' => 'Базова сітка для жіночих капців',
            'content_json' => [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [
                    ['36-37', '23.5'],
                    ['38-39', '24.5'],
                ],
            ],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $response->assertRedirect(route('admin.size-charts.index'));

        $chart = SizeChart::query()->firstOrFail();

        $this->assertSame('slippers_women', $chart->code);
        $this->assertSame(['Розмір', 'Довжина стопи, см'], $chart->content_json['columns']);
        $this->assertSame('36-37', $chart->content_json['rows'][0][0]);
    }

    public function test_deleting_product_color_group_flashes_success_message_for_toast(): void
    {
        $user = User::factory()->create();
        $group = ProductColorGroup::query()->create([
            'name' => 'Base colors',
            'code' => 'base_colors',
        ]);

        $response = $this->actingAs($user)->delete(route('admin.color-groups.destroy', $group));

        $response
            ->assertRedirect(route('admin.color-groups.index'))
            ->assertSessionHas('success', 'Групу кольорів видалено');

        $this->assertSoftDeleted('product_color_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_color_group_and_size_chart_index_pages_are_available(): void
    {
        $user = User::factory()->create();

        ProductColorGroup::query()->create([
            'name' => 'Base colors',
            'code' => 'base_colors',
        ]);
        SizeChart::query()->create([
            'title' => 'Піжами',
            'code' => 'pajamas',
        ]);

        $this->actingAs($user)->get(route('admin.color-groups.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.size-charts.index'))->assertOk();
    }
}
