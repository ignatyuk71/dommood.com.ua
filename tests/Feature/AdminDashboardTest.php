<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_is_available_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_legacy_dashboard_url_redirects_to_admin(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/admin');
    }
}
