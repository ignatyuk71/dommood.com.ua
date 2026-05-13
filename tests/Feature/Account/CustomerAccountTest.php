<?php

namespace Tests\Feature\Account;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_account_dashboard(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $user = User::factory()->create([
            'name' => 'Покупець',
            'email' => 'buyer@example.com',
            'role' => 'customer',
        ]);
        $user->assignRole('customer');

        Customer::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Покупець',
            'email' => 'buyer@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('account.dashboard'))
            ->assertOk();
    }
}
