<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartNullableIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_guest_reads_do_not_create_a_cart_and_first_write_uses_a_real_parent(): void
    {
        $category = Category::firstOrCreate(['cat_title' => 'Fresh']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra', 'product_slug' => 'fresh-cart', 'product_price' => '125000.00']);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5]);

        $this->get('/belanja')->assertOk();
        $this->getJson('/cart')->assertOk()->assertJsonCount(0, 'items');
        $this->assertDatabaseCount('carts', 0);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk()->assertJsonPath('items.0.quantity', 2);
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseHas('cart_items', ['cart_id' => DB::table('carts')->value('id'), 'product_id' => $product->product_id, 'quantity' => 2]);
    }

    public function test_guest_cart_merges_through_real_login(): void
    {
        $category = Category::firstOrCreate(['cat_title' => 'Merge']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra', 'product_slug' => 'merge-cart', 'product_price' => '125000.00']);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5]);
        $user = User::factory()->create(['email' => 'merge@example.test']);

        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();
        $this->post('/login', ['email' => 'merge@example.test', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->getJson('/cart')->assertOk()->assertJsonPath('subtotal', 250000);
        $this->assertNotNull(DB::table('carts')->whereNull('user_id')->value('consumed_at'));
    }

    public function test_fresh_guest_delete_clear_and_empty_checkout_do_not_create_cart(): void
    {
        $this->deleteJson('/cart/items/1')->assertOk()->assertJsonCount(0, 'items');
        $this->deleteJson('/cart')->assertOk();
        $this->postJson('/checkout', [
            'idempotency_key' => 'empty-cart-test', 'customer_name' => 'Sutra', 'customer_email' => 'sutra@example.test',
            'customer_phone' => '081234567890', 'phone' => '081234567890', 'label' => 'Rumah',
            'recipient_name' => 'Sutra', 'line1' => 'Jalan 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12345',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('carts', 0);
    }
}
