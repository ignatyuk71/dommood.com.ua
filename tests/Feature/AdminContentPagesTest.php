<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminContentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_content_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Content/Pages/Index', false)
                ->has('statusOptions', 2));

        $this->actingAs($admin)
            ->post(route('admin.pages.store'), [
                'title' => 'Тестова сторінка доставки',
                'slug' => 'testova-storinka-dostavky',
                'content' => '<p>Доставляємо Новою поштою.</p>',
                'status' => 'published',
                'meta_title' => 'Тестова сторінка доставки DomMood',
                'meta_description' => 'Умови оплати і доставки DomMood.',
                'canonical_url' => '',
                'published_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $page = ContentPage::query()->where('slug', 'testova-storinka-dostavky')->firstOrFail();

        $this->assertSame('Тестова сторінка доставки', $page->title);
        $this->assertSame('testova-storinka-dostavky', $page->slug);
        $this->assertSame('published', $page->status);
    }

    public function test_admin_can_update_content_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = ContentPage::query()->create([
            'title' => 'Стара тестова сторінка',
            'slug' => 'stara-testova-storinka',
            'content' => '<p>Стара версія.</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Про DomMood',
                'slug' => 'pro-dommood',
                'content' => '<p>Оновлена сторінка.</p>',
                'status' => 'published',
                'meta_title' => '',
                'meta_description' => '',
                'canonical_url' => '',
                'published_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.pages.edit', $page));

        $page->refresh();

        $this->assertSame('Про DomMood', $page->title);
        $this->assertSame('pro-dommood', $page->slug);
        $this->assertSame('published', $page->status);
    }
}
