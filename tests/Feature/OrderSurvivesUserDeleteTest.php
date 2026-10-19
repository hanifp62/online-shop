<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderSurvivesUserDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_and_items_survive_user_delete(): void
    {
        $user = User::factory()->create();

        $category = Category::create(['cat_title' => 'Survive']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra',
            'product_slug' => 'sutra-'.uniqid(),
            'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'paid',
            'subtotal' => 125000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 125000,
            'idempotency_key' => 'survive-'.uniqid(),
            'payload_hash' => str_repeat('a', 64),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->product_id,
            'product_title' => 'Sutra',
            'unit_price' => 125000,
            'quantity' => 1,
            'subtotal' => 125000,
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $user->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
        $this->assertNull(Order::findOrFail($order->id)->user_id);
    }
}
