<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSiteStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_separate_site_structure_menu_groups(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.site-structure.show', 'footer'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteStructure/Index', false)
                ->where('menu.key', 'footer')
                ->where('menu.name', 'Footer')
                ->where('menus.0.key', 'main')
                ->where('menus.1.key', 'footer')
                ->where('menus.2.key', 'mobile'));

        $this->assertDatabaseHas('menus', [
            'slug' => 'footer',
            'name' => 'Footer',
        ]);
    }

    public function test_admin_can_create_menu_item_with_parent(): void
    {
        $user = User::factory()->create();
        $menu = Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $parent = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'title' => 'Каталог',
            'type' => 'custom_url',
            'url' => '/catalog',
            'target' => '_self',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('admin.site-structure.items.store', 'main'), [
            'parent_id' => $parent->id,
            'title' => 'Тапочки',
            'type' => 'custom_url',
            'url' => '/catalog/tapochky',
            'target' => '_self',
            'is_active' => true,
        ]);

        $response
            ->assertRedirect(route('admin.site-structure.show', 'main'))
            ->assertSessionHas('success', 'Пункт меню створено');

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'title' => 'Тапочки',
            'url' => '/catalog/tapochky',
            'is_active' => true,
        ]);
    }

    public function test_category_menu_item_uses_category_as_linkable(): void
    {
        $user = User::factory()->create();
        Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $category = Category::query()->create([
            'name' => 'Жіночі тапочки',
            'slug' => 'zhinochi-tapochky',
        ]);

        $response = $this->actingAs($user)->post(route('admin.site-structure.items.store', 'main'), [
            'title' => '',
            'type' => 'category',
            'linkable_id' => $category->id,
            'target' => '_self',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.site-structure.show', 'main'));

        $this->assertDatabaseHas('menu_items', [
            'title' => 'Жіночі тапочки',
            'type' => 'category',
            'linkable_type' => Category::class,
            'linkable_id' => $category->id,
            'url' => null,
        ]);
    }

    public function test_admin_can_reorder_menu_tree_with_nested_items(): void
    {
        $user = User::factory()->create();
        $menu = Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $first = $this->makeItem($menu, 'Перший', 0);
        $second = $this->makeItem($menu, 'Другий', 1);
        $third = $this->makeItem($menu, 'Третій', 2);

        $response = $this->actingAs($user)->postJson(route('admin.site-structure.reorder', 'main'), [
            'tree' => [
                [
                    'id' => $second->id,
                    'children' => [
                        ['id' => $first->id, 'children' => []],
                    ],
                ],
                ['id' => $third->id, 'children' => []],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson(['message' => 'Порядок меню збережено']);

        $this->assertDatabaseHas('menu_items', [
            'id' => $second->id,
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $first->id,
            'parent_id' => $second->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $third->id,
            'parent_id' => null,
            'sort_order' => 1,
        ]);
    }

    public function test_reorder_rejects_items_from_another_menu_group(): void
    {
        $user = User::factory()->create();
        $main = Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $footer = Menu::query()->create([
            'name' => 'Footer',
            'slug' => 'footer',
        ]);
        $mainItem = $this->makeItem($main, 'Каталог');
        $footerItem = $this->makeItem($footer, 'Доставка');

        $this->actingAs($user)
            ->postJson(route('admin.site-structure.reorder', 'main'), [
                'tree' => [
                    ['id' => $mainItem->id, 'children' => []],
                    ['id' => $footerItem->id, 'children' => []],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tree');
    }

    public function test_admin_cannot_create_parent_cycle_on_update(): void
    {
        $user = User::factory()->create();
        $menu = Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $parent = $this->makeItem($menu, 'Каталог');
        $child = $this->makeItem($menu, 'Тапочки', 0, $parent->id);

        $response = $this->actingAs($user)->put(route('admin.site-structure.items.update', ['main', $parent->id]), [
            'parent_id' => $child->id,
            'title' => 'Каталог',
            'type' => 'custom_url',
            'url' => '/catalog',
            'target' => '_self',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('menu_items', [
            'id' => $parent->id,
            'parent_id' => null,
        ]);
    }

    public function test_deleting_parent_menu_item_moves_children_to_root(): void
    {
        $user = User::factory()->create();
        $menu = Menu::query()->create([
            'name' => 'Footer',
            'slug' => 'footer',
        ]);
        $parent = $this->makeItem($menu, 'Допомога');
        $child = $this->makeItem($menu, 'Доставка', 0, $parent->id);

        $this->actingAs($user)
            ->delete(route('admin.site-structure.items.destroy', ['footer', $parent->id]))
            ->assertRedirect(route('admin.site-structure.show', 'footer'))
            ->assertSessionHas('success', 'Пункт меню видалено');

        $this->assertDatabaseMissing('menu_items', ['id' => $parent->id]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $child->id,
            'parent_id' => null,
        ]);
    }

    public function test_menu_item_changes_are_logged_to_manager_activity(): void
    {
        $user = User::factory()->create(['name' => 'Контент менеджер']);
        $menu = Menu::query()->create([
            'name' => 'Меню',
            'slug' => 'main',
        ]);
        $existing = $this->makeItem($menu, 'Каталог', 0);

        $this->actingAs($user)->post(route('admin.site-structure.items.store', 'main'), [
            'title' => 'Доставка',
            'type' => 'custom_url',
            'url' => '/delivery',
            'target' => '_self',
            'is_active' => true,
        ])->assertRedirect(route('admin.site-structure.show', 'main'));

        $created = MenuItem::query()->where('title', 'Доставка')->firstOrFail();
        $createLog = AdminActivityLog::query()
            ->where('event', 'site_structure.menu_item_created')
            ->firstOrFail();

        $this->assertSame($user->id, $createLog->user_id);
        $this->assertSame(MenuItem::class, $createLog->subject_type);
        $this->assertSame($created->id, $createLog->subject_id);
        $this->assertSame('Доставка', $createLog->new_values['title']);
        $this->assertSame('Меню', $createLog->new_values['menu']);

        $this->actingAs($user)->put(route('admin.site-structure.items.update', ['main', $created->id]), [
            'title' => 'Оплата і доставка',
            'type' => 'custom_url',
            'url' => '/payment-delivery',
            'target' => '_self',
            'is_active' => true,
        ])->assertRedirect(route('admin.site-structure.show', 'main'));

        $updateLog = AdminActivityLog::query()
            ->where('event', 'site_structure.menu_item_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Доставка', $updateLog->old_values['title']);
        $this->assertSame('Оплата і доставка', $updateLog->new_values['title']);
        $this->assertSame('/delivery', $updateLog->old_values['url']);
        $this->assertSame('/payment-delivery', $updateLog->new_values['url']);

        $this->actingAs($user)->postJson(route('admin.site-structure.reorder', 'main'), [
            'tree' => [
                ['id' => $created->id, 'children' => []],
                ['id' => $existing->id, 'children' => []],
            ],
        ])->assertOk();

        $reorderLog = AdminActivityLog::query()
            ->where('event', 'site_structure.menu_reordered')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(Menu::class, $reorderLog->subject_type);
        $this->assertSame($menu->id, $reorderLog->subject_id);
        $this->assertSame('Меню', $reorderLog->new_values['menu']);
        $this->assertCount(2, $reorderLog->new_values['structure']);

        $this->actingAs($user)
            ->delete(route('admin.site-structure.items.destroy', ['main', $created->id]))
            ->assertRedirect(route('admin.site-structure.show', 'main'));

        $deleteLog = AdminActivityLog::query()
            ->where('event', 'site_structure.menu_item_deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($created->id, $deleteLog->subject_id);
        $this->assertSame('Оплата і доставка', $deleteLog->old_values['title']);
        $this->assertTrue($deleteLog->new_values['deleted']);
    }

    private function makeItem(Menu $menu, string $title, int $sort = 0, ?int $parentId = null): MenuItem
    {
        return MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'title' => $title,
            'type' => 'custom_url',
            'url' => '/'.str($title)->slug(),
            'target' => '_self',
            'is_active' => true,
            'sort_order' => $sort,
        ]);
    }
}
