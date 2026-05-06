<?php

namespace Tests\Feature;

use App\Models\ProductColorGroup;
use App\Models\SizeChart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_admin_can_upload_size_chart_image_to_chart_directory(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.size-charts.store'), [
            'title' => 'Капці жіночі',
            'code' => 'slippers_women',
            'content_json' => [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [['36-37', '23.5']],
            ],
            'is_active' => true,
            'image' => UploadedFile::fake()->image('chart.png', 900, 700),
        ]);

        $response->assertRedirect(route('admin.size-charts.index'));

        $chart = SizeChart::query()->where('code', 'slippers_women')->firstOrFail();

        $this->assertStringStartsWith("size-charts/{$chart->id}/slippers-women-dommood-", $chart->image_path);
        $this->assertStringEndsWith('.png', $chart->image_path);
        Storage::disk('public')->assertExists($chart->image_path);
    }

    public function test_size_charts_index_exposes_image_url_for_thumbnail(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $chart = SizeChart::query()->create([
            'title' => 'Капці жіночі',
            'code' => 'slippers_women',
            'image_path' => 'size-charts/1/slippers-women-dommood-20260506-120000.png',
        ]);
        Storage::disk('public')->put($chart->image_path, 'image');

        $this->actingAs($user)
            ->get(route('admin.size-charts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/SizeCharts/Index', false)
                ->where('charts.data.0.image_url', Storage::disk('public')->url($chart->image_path))
            );
    }

    public function test_replacing_size_chart_image_deletes_old_file(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();
        $chart = SizeChart::query()->create([
            'title' => 'Капці жіночі',
            'code' => 'slippers_women',
        ]);
        $oldPath = "size-charts/{$chart->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old-image');
        $chart->update(['image_path' => $oldPath]);

        $response = $this->actingAs($user)->post(route('admin.size-charts.update', $chart), [
            '_method' => 'put',
            'title' => 'Капці жіночі',
            'code' => 'slippers_women',
            'content_json' => [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [['36-37', '23.5']],
            ],
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.webp', 900, 700),
        ]);

        $response
            ->assertRedirect(route('admin.size-charts.index'))
            ->assertSessionHas('success', 'Розмірну сітку оновлено');

        $chart->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($chart->image_path);
        $this->assertStringEndsWith('.webp', $chart->image_path);
    }

    public function test_deleting_size_chart_image_removes_file_from_storage(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $chart = SizeChart::query()->create([
            'title' => 'Піжами',
            'code' => 'pajamas',
        ]);
        $oldPath = "size-charts/{$chart->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old-image');
        $chart->update(['image_path' => $oldPath]);

        $response = $this->actingAs($user)->post(route('admin.size-charts.update', $chart), [
            '_method' => 'put',
            'title' => 'Піжами',
            'code' => 'pajamas',
            'content_json' => [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [['S', '88-92']],
            ],
            'is_active' => true,
            'delete_image' => true,
        ]);

        $response->assertRedirect(route('admin.size-charts.index'));

        $chart->refresh();
        $this->assertNull($chart->image_path);
        Storage::disk('public')->assertMissing($oldPath);
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
