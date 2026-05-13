<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_customers_page(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        Customer::query()->create([
            'first_name' => 'Олена',
            'last_name' => 'Петренко',
            'phone' => '380991111111',
            'email' => 'olena@example.com',
            'orders_count' => 2,
            'total_spent_cents' => 120000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk();
    }
}
