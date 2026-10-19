<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewOwnershipRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_submit_review_for_another_users_completed_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::create(['cat_title' => 'Sutra']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'sutra-ownership-'.uniqid(), 'product_price' => '125000.00',
        ]);
        $order = Order::create([
            'user_id' => $owner->id, 'status' => 'completed', 'total' => 125000,
            'idempotency_key' => 'ownership-review-'.uniqid(), 'payload_hash' => str_repeat('a', 64),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->product_id, 'product_title' => 'Sutra',
            'unit_price' => 125000, 'quantity' => 1, 'subtotal' => 125000,
        ]);

        $this->actingAs($other)->postJson('/reviews', [
            'order_id' => $order->id, 'product_id' => $product->product_id, 'rating' => 5, 'body' => 'Bukan milik saya',
        ])->assertNotFound();

        $this->assertDatabaseCount('reviews', 0);
    }
}
