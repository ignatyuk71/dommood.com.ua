<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBannersTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_form_uses_storefront_image_recommendations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Content/Banners/Index', false)
                ->has('placementOptions', 3)
                ->where('placementOptions.0.value', 'home_hero_main')
                ->where('placementOptions.0.desktop_size', '2400×1200 px')
                ->where('placementOptions.0.mobile_size', '1080×1200 px')
                ->where('placementOptions.1.value', 'home_hero_side_top')
                ->where('placementOptions.1.desktop_size', '1200×720 px')
                ->where('placementOptions.1.mobile_size', '1200×560 px')
                ->where('placementOptions.2.value', 'home_hero_side_bottom')
                ->where('placementOptions.2.desktop_size', '1200×720 px')
                ->where('placementOptions.2.mobile_size', '1200×560 px')
                ->where('placementOptions.0.desktop_note', 'Hero кропиться в діапазоні ≈2:1-2.25:1: ключовий товар тримати правіше центру, лівий край чистий під текст.')
                ->where('placementOptions.1.mobile_note', 'На телефоні цей слот лишається широким ≈2:1, тому не використовуйте вертикальні креативи.'));
    }
}
