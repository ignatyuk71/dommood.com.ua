<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_pending_review(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $response = $this->actingAs($user)->post(route('admin.reviews.store'), [
            'product_id' => $product->id,
            'author_name' => 'Олена',
            'author_email' => 'olena@example.com',
            'rating' => 5,
            'title' => 'Якісний товар',
            'body' => 'Все сподобалось, швидко відправили.',
            'status' => Review::STATUS_PENDING,
            'is_verified_buyer' => true,
            'source' => 'site',
        ]);

        $response->assertRedirect(route('admin.reviews.index', ['status' => Review::STATUS_PENDING]));

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'author_name' => 'Олена',
            'status' => Review::STATUS_PENDING,
            'is_verified_buyer' => true,
        ]);
    }

    public function test_admin_can_approve_review(): void
    {
        $user = User::factory()->create();
        $review = Review::query()->create([
            'product_id' => $this->createProduct()->id,
            'author_name' => 'Ірина',
            'rating' => 5,
            'body' => 'Товар хороший.',
            'status' => Review::STATUS_PENDING,
            'source' => 'site',
        ]);

        $response = $this->actingAs($user)->patch(route('admin.reviews.approve', $review));

        $response->assertRedirect();

        $review->refresh();

        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertSame($user->id, $review->moderated_by);
        $this->assertNotNull($review->published_at);
        $this->assertNotNull($review->moderated_at);
    }

    public function test_admin_can_reject_review(): void
    {
        $user = User::factory()->create();
        $review = Review::query()->create([
            'product_id' => $this->createProduct()->id,
            'author_name' => 'Марія',
            'rating' => 2,
            'body' => 'Некоректний текст.',
            'status' => Review::STATUS_PENDING,
            'source' => 'site',
        ]);

        $response = $this->actingAs($user)->patch(route('admin.reviews.reject', $review), [
            'moderation_note' => 'Спам',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_REJECTED,
            'moderated_by' => $user->id,
            'moderation_note' => 'Спам',
        ]);
    }

    public function test_reviews_index_is_available_for_admin(): void
    {
        $user = User::factory()->create();

        Review::query()->create([
            'product_id' => $this->createProduct()->id,
            'author_name' => 'Наталія',
            'rating' => 4,
            'body' => 'Все добре.',
            'status' => Review::STATUS_APPROVED,
            'source' => 'manual',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.reviews.index', ['status' => Review::STATUS_APPROVED]))
            ->assertOk();
    }

    private function createProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Тестовий товар',
            'slug' => 'test-product-'.fake()->unique()->numberBetween(1000, 9999),
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'active',
            'price_cents' => 129900,
            'stock_status' => 'in_stock',
        ]);
    }
}
