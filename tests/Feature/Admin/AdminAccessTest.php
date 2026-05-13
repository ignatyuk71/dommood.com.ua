<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_open_admin_area(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $user = User::factory()->create(['role' => 'customer']);
        $user->assignRole('customer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_manager_can_open_orders_but_not_roles(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $user = User::factory()->create(['role' => 'manager']);
        $user->assignRole('manager');

        $this->actingAs($user)
            ->get(route('admin.orders.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.payment-delivery.show', 'transactions'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.payment-delivery.show', 'payment-methods'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_manager_permission_changes_are_not_reset_by_role_sync(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $admin->assignRole('admin');
        $manager->assignRole('manager');

        $managerRole = Role::query()
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $managerRole), [
                'permissions' => [AdminPermissions::SEO_REDIRECTS_MANAGE],
            ])
            ->assertRedirect();

        app(SyncAdminRolesAndPermissions::class)();

        $this->actingAs($manager)
            ->get(route('admin.seo.show', 'redirects'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('admin.seo.show', 'meta'))
            ->assertForbidden();
    }

    public function test_admin_can_open_analytics_and_manager_cannot_by_default(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $admin->assignRole('admin');
        $manager->assignRole('manager');

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', 'google'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('activeChannel', 'google')
                ->has('channels', 3)
            );

        $this->actingAs($manager)
            ->get(route('admin.analytics.show', 'google'))
            ->assertForbidden();
    }

    public function test_admin_can_manage_role_permissions(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk();
    }

    public function test_admin_route_permissions_match_role_matrix(): void
    {
        $knownPermissions = AdminPermissions::all();
        $routePermissions = [];
        $routesWithoutPermission = [];
        $unknownPermissions = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            $permissionMiddleware = collect($route->gatherMiddleware())
                ->first(fn (string $middleware): bool => str_contains($middleware, 'EnsureAdminPermission')
                    || str_starts_with($middleware, 'admin.permission:'));

            if (! $permissionMiddleware) {
                $routesWithoutPermission[] = implode('|', $route->methods()).' '.$route->uri();

                continue;
            }

            $rawPermissions = str($permissionMiddleware)->after(':')->explode('|')->filter()->values();

            foreach ($rawPermissions as $permission) {
                $routePermissions[] = $permission;

                if (! in_array($permission, $knownPermissions, true)) {
                    $unknownPermissions[] = $permission;
                }
            }
        }

        $this->assertSame([], $routesWithoutPermission, 'Кожен admin route має мати admin.permission middleware.');
        $this->assertSame([], array_values(array_unique($unknownPermissions)), 'Routes використовують permission, якого немає в матриці ролей.');
        $this->assertSame([], array_values(array_diff($knownPermissions, array_unique($routePermissions))), 'У матриці ролей є permission без admin route.');
    }

    public function test_roles_page_shows_only_staff_roles_and_staff_users(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $customer = User::factory()->create(['role' => 'customer']);

        $admin->assignRole('admin');
        $manager->assignRole('manager');
        $customer->assignRole('customer');

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Roles/Index')
                ->has('roles', 2)
                ->where('roles.0.name', 'admin')
                ->where('roles.1.name', 'manager')
                ->where('staffTotal', 2)
            );
    }

    public function test_admin_can_create_staff_manager_from_roles_page(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.roles.staff.store'), [
                'name' => 'Олена Менеджер',
                'email' => 'olena.manager@example.com',
                'phone' => '+380991112233',
                'role' => 'manager',
                'password' => 'Password123!',
                'is_active' => true,
            ])
            ->assertRedirect();

        $manager = User::query()->where('email', 'olena.manager@example.com')->firstOrFail();

        $this->assertSame('manager', $manager->role);
        $this->assertTrue($manager->is_active);
        $this->assertTrue($manager->hasRole('manager'));
        $this->assertTrue(Hash::check('Password123!', $manager->password));
    }

    public function test_admin_can_toggle_staff_access(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $admin->assignRole('admin');
        $manager->assignRole('manager');

        $this->actingAs($admin)
            ->patch(route('admin.roles.staff.update', $manager), [
                'role' => 'manager',
                'is_active' => false,
            ])
            ->assertRedirect();

        $manager->refresh();

        $this->assertFalse($manager->is_active);
        $this->assertTrue($manager->hasRole('manager'));
    }

    public function test_manager_cannot_create_staff_members(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->post(route('admin.roles.staff.store'), [
                'name' => 'Новий менеджер',
                'email' => 'new.manager@example.com',
                'role' => 'manager',
                'password' => 'Password123!',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_only_admin_can_clear_system_cache(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $admin->assignRole('admin');
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->postJson(route('admin.system.cache.clear'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.system.cache.clear'))
            ->assertOk()
            ->assertJson(['message' => 'Кеш очищено']);
    }
}
