<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reviews_preserve_content_and_pagination_without_purchase_identifiers(): void
    {
        $user = User::factory()->create(['name' => 'Klien Sutra']);
        $category = Category::create(['cat_title' => 'Sutra']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'sutra-review', 'product_price' => '125000.00',
        ]);
        for ($index = 0; $index < 21; $index++) {
            $order = Order::create([
                'user_id' => $user->id, 'status' => 'completed',
                'idempotency_key' => 'review-'.$index, 'payload_hash' => hash('sha256', (string) $index),
            ]);
            $review = Review::create([
                'user_id' => $user->id, 'order_id' => $order->id,
                'product_id' => $product->product_id, 'rating' => 5, 'body' => 'Sutra indah ✨',
            ]);
        }

        $response = $this->getJson('/reviews?product_id='.$product->product_id)
            ->assertOk()
            ->assertJsonCount(20, 'reviews.data')
            ->assertJsonPath('reviews.current_page', 1)
            ->assertJsonPath('reviews.per_page', 20)
            ->assertJsonPath('reviews.total', 21)
            ->assertJsonPath('reviews.last_page', 2)
            ->assertJsonPath('reviews.data.0.rating', 5)
            ->assertJsonPath('reviews.data.0.body', 'Sutra indah ✨')
            ->assertJsonPath('reviews.data.0.created_at', $review->created_at->toJSON())
            ->assertJsonPath('reviews.data.0.user.name', 'Klien Sutra')
            ->assertJsonStructure(['reviews' => ['links', 'next_page_url', 'prev_page_url']]);

        foreach ($response->json('reviews.data') as $item) {
            $this->assertArrayNotHasKey('order_id', $item);
            $this->assertArrayNotHasKey('user_id', $item);
            $this->assertArrayNotHasKey('id', $item['user']);
        }

        $this->getJson('/reviews?product_id='.$product->product_id.'&page=2')
            ->assertOk()->assertJsonCount(1, 'reviews.data')->assertJsonPath('reviews.current_page', 2);
        $this->getJson('/reviews?product_id=0')
            ->assertOk()->assertJsonCount(0, 'reviews.data')->assertJsonPath('reviews.total', 0);
    }
}
