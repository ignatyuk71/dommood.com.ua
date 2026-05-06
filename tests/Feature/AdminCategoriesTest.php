<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_root_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.categories.store'), [
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'description' => 'Мʼякі домашні капці для жінок.',
            'is_active' => true,
            'sort_order' => 10,
            'meta_title' => 'Жіночі капці купити в Україні',
            'meta_description' => 'Жіночі домашні капці DomMood з доставкою по Україні.',
            'seo_text' => 'SEO текст категорії.',
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_create_child_category_with_generated_slug(): void
    {
        $user = User::factory()->create();
        $parent = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);

        $response = $this->actingAs($user)->post(route('admin.categories.store'), [
            'parent_id' => $parent->id,
            'name' => 'Теплі моделі',
            'slug' => '',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'parent_id' => $parent->id,
            'name' => 'Теплі моделі',
            'slug' => 'tepli-modeli',
        ]);
    }

    public function test_admin_can_upload_category_image_to_category_directory(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.categories.store'), [
            'name' => 'Жіночі капці',
            'slug' => 'zhinochi-kaptsi',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('original.jpg', 900, 700),
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'zhinochi-kaptsi')->firstOrFail();

        $this->assertStringStartsWith("categories/{$category->id}/zhinochi-kaptsi-dommood-", $category->image_path);
        $this->assertStringEndsWith('.jpg', $category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_replacing_category_image_deletes_old_file(): void
    {
        Storage::fake('public');
        config(['app.name' => 'DomMood']);

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);
        $oldPath = "categories/{$category->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old-image');
        $category->update(['image_path' => $oldPath]);

        $response = $this->actingAs($user)->post(route('admin.categories.update', $category), [
            '_method' => 'put',
            'name' => 'Капці',
            'slug' => 'kaptsi',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.webp', 900, 700),
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('success', 'Категорію оновлено');

        $category->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($category->image_path);
        $this->assertStringEndsWith('.webp', $category->image_path);
    }

    public function test_updating_category_flashes_success_message_for_toast(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Капці',
            'slug' => 'kaptsi',
        ]);

        $response = $this->actingAs($user)->put(route('admin.categories.update', $category), [
            'name' => 'Капці для дому',
            'slug' => 'kaptsi-dlia-domu',
            'is_active' => true,
        ]);

        $response
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Категорію оновлено');
    }

    public function test_deleting_category_image_removes_file_from_storage(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Піжами',
            'slug' => 'pizhamy',
        ]);
        $oldPath = "categories/{$category->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old-image');
        $category->update(['image_path' => $oldPath]);

        $response = $this->actingAs($user)->post(route('admin.categories.update', $category), [
            '_method' => 'put',
            'name' => 'Піжами',
            'slug' => 'pizhamy',
            'is_active' => true,
            'delete_image' => true,
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $category->refresh();
        $this->assertNull($category->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_cannot_create_category_parent_cycle(): void
    {
        $user = User::factory()->create();
        $parent = Category::query()->create([
            'name' => 'Одяг',
            'slug' => 'odyag',
        ]);
        $child = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Піжами',
            'slug' => 'pizhamy',
        ]);

        $response = $this->actingAs($user)->put(route('admin.categories.update', $parent), [
            'parent_id' => $child->id,
            'name' => 'Одяг',
            'slug' => 'odyag',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
            'parent_id' => null,
        ]);
    }

    public function test_admin_cannot_delete_category_with_children(): void
    {
        $user = User::factory()->create();
        $parent = Category::query()->create([
            'name' => 'Домашній одяг',
            'slug' => 'domashnii-odyag',
        ]);
        Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Халати',
            'slug' => 'halaty',
        ]);

        $response = $this->actingAs($user)->delete(route('admin.categories.destroy', $parent));

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_categories_index_is_available_for_admin(): void
    {
        $user = User::factory()->create();

        Category::query()->create([
            'name' => 'Піжами',
            'slug' => 'pizhamy',
        ]);

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertOk();
    }
}
