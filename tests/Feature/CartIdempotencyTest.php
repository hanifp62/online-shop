<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug, int $stock = 5): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Tas Tangan']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => $slug,
            'product_slug' => $slug,
            'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock]);

        return $product->fresh();
    }

    public function test_repeated_set_replaces_quantity_preserves_other_items_and_uses_database_price(): void
    {
        $first = $this->product('first');
        $second = $this->product('second');
        $this->postJson('/cart/items', ['product_id' => $second->product_id, 'quantity' => 1])->assertOk();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/cart/items', ['product_id' => $first->product_id, 'quantity' => 2, 'price' => 1])
                ->assertOk()->assertJsonPath('subtotal', 375000)->assertJsonCount(2, 'items');
        }
        $this->getJson('/cart')->assertJsonPath('items.0.product_id', $second->product_id)
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('items.1.unit_price', 125000)
            ->assertJsonPath('items.1.quantity', 2);
    }

    public function test_repeated_delete_and_clear_leave_cart_empty_without_affecting_other_items(): void
    {
        $first = $this->product('first');
        $second = $this->product('second');
        foreach ([$first, $second] as $product) {
            $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->deleteJson('/cart/items/'.$first->product_id)->assertOk()
                ->assertJsonCount(1, 'items')->assertJsonPath('items.0.product_id', $second->product_id);
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->deleteJson('/cart')->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('subtotal', 0);
        }
        $this->getJson('/cart')->assertJsonCount(0, 'items');
    }

    public function test_invalid_quantity_or_unavailable_stock_does_not_mutate_existing_cart(): void
    {
        $first = $this->product('first');
        $second = $this->product('second', 0);
        $this->postJson('/cart/items', ['product_id' => $first->product_id, 'quantity' => 1])->assertOk();
        foreach ([0, -1, 100, '1.5', null] as $quantity) {
            $this->postJson('/cart/items', ['product_id' => $first->product_id, 'quantity' => $quantity])
                ->assertUnprocessable();
        }
        $this->postJson('/cart/items', ['product_id' => $second->product_id, 'quantity' => 1])->assertUnprocessable();
        $this->getJson('/cart')->assertJsonCount(1, 'items')->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('items.0.product_id', $first->product_id);
    }

    public function test_cart_methods_share_one_sixty_request_per_ip_budget(): void
    {
        $this->freezeTime();
        $product = $this->product('limited');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.41']);

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $this->getJson('/cart')->assertOk();
            $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
            $this->deleteJson('/cart/items/'.$product->product_id)->assertOk();
            $this->deleteJson('/cart')->assertOk();
        }

        $this->getJson('/cart')->assertStatus(429);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertStatus(429);
        $this->deleteJson('/cart/items/'.$product->product_id)->assertStatus(429);
        $this->deleteJson('/cart')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.42'])->getJson('/cart')->assertOk();
        $this->travel(61)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.41'])->getJson('/cart')->assertOk();
    }

    public function test_customer_logout_clears_cart_without_logging_out_admin(): void
    {
        $user = User::factory()->create();
        $admin = Admin::create([
            'name' => 'Cart Admin',
            'email' => 'cart-admin@example.test',
            'password' => 'Test-password-123!',
        ]);
        $this->withSession([]);
        auth()->guard('web')->login($user);
        auth()->guard('admin')->login($admin);
        $product = $this->product('logout');
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $this->getJson('/cart')->assertJsonCount(1, 'items');
        $sessionId = session()->getId();

        $this->post(route('logout'))->assertRedirect('/')->assertSessionMissing('cart');

        $this->assertNotSame($sessionId, session()->getId());
        auth()->forgetGuards();
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->getJson('/cart')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_every_cart_route_shares_checkout_session_lock_duration(): void
    {
        $routes = app('router')->getRoutes();
        $checkout = $routes->getByName('checkout.store');
        $this->assertSame(10, $checkout->locksFor());
        $this->assertSame(10, $checkout->waitsFor());
        foreach (['cart.show', 'cart.items.store', 'cart.items.destroy', 'cart.clear'] as $name) {
            $route = $routes->getByName($name);
            $this->assertSame($checkout->locksFor(), $route->locksFor(), $name);
            $this->assertSame($checkout->waitsFor(), $route->waitsFor(), $name);
        }
    }
}
