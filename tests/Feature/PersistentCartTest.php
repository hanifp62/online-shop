<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersistentCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Persistent']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra', 'product_slug' => 'persistent-'.uniqid(), 'product_price' => '125000.00']);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 100]);

        return $product->fresh();
    }

    public function test_account_cart_is_shared_across_sessions_and_not_erased_on_logout(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->product_id, 'quantity' => 2]);
        $this->post('/logout')->assertRedirect('/');
        $this->getJson('/cart')->assertJsonCount(0, 'items');
        $this->actingAs($user)->getJson('/cart')->assertJsonPath('items.0.quantity', 2);
    }

    public function test_legacy_session_merges_by_max_once_and_is_removed(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 3])->assertOk();
        $this->withSession(['cart' => [$product->product_id => 2]])->getJson('/cart')->assertJsonPath('items.0.quantity', 3);
        $this->assertSame([], session('cart', []));
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cart_schema_enforces_unique_owner_and_product(): void
    {
        $user = User::factory()->create();
        $cart = DB::table('carts')->insertGetId(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('carts')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_login_merges_guest_cart_by_max_and_consumes_guest_cart(): void
    {
        $shared = $this->product();
        $guestOnly = $this->product();
        $user = User::factory()->create(['password' => 'Test-password-123!']);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $shared->product_id, 'quantity' => 4])->assertOk();
        $this->post('/logout');
        $this->postJson('/cart/items', ['product_id' => $shared->product_id, 'quantity' => 2])->assertOk();
        $this->postJson('/cart/items', ['product_id' => $guestOnly->product_id, 'quantity' => 1])->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-123!'])->assertRedirect('/');
        $this->getJson('/cart')->assertJsonCount(2, 'items')->assertJsonPath('items.0.quantity', 4)->assertJsonPath('items.1.quantity', 1);
        $this->assertNotNull(DB::table('carts')->whereNull('user_id')->value('consumed_at'));
        $this->assertSame(0, DB::table('cart_items')->join('carts', 'carts.id', '=', 'cart_items.cart_id')->whereNull('carts.user_id')->count());
    }

    public function test_checkout_replay_does_not_clear_items_added_after_order(): void
    {
        $product = $this->product();
        $later = $this->product();
        $user = User::factory()->create();
        $address = Address::create(['user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'A', 'phone' => '0812345678', 'line1' => 'Jalan 1', 'city' => 'Jakarta', 'province' => 'DKI', 'postal_code' => '12345']);
        $payload = ['address_id' => $address->id, 'idempotency_key' => 'persist-key'];
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $this->postJson('/checkout', $payload)->assertCreated();
        $this->getJson('/cart')->assertJsonCount(0, 'items');
        $this->postJson('/cart/items', ['product_id' => $later->product_id, 'quantity' => 1])->assertOk();
        $this->postJson('/checkout', $payload)->assertStatus(409);
        $this->getJson('/cart')->assertJsonCount(1, 'items')->assertJsonPath('items.0.product_id', $later->product_id);
    }

    public function test_prune_command_deletes_only_expired_or_consumed_guest_carts(): void
    {
        $user = User::factory()->create();
        DB::table('carts')->insert([
            ['user_id' => $user->id, 'token_hash' => null, 'expires_at' => null, 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->subDay(), 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay(), 'consumed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => null, 'token_hash' => str_repeat('c', 64), 'expires_at' => now()->addDay(), 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->artisan('carts:prune')->assertSuccessful();
        $this->assertSame([str_repeat('c', 64)], DB::table('carts')->whereNull('user_id')->pluck('token_hash')->all());
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    public function test_guest_token_is_hashed_and_expired_cart_is_not_loaded(): void
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $this->assertDatabaseCount('carts', 1);
        $this->assertNotNull(DB::table('carts')->value('token_hash'));
        DB::table('carts')->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/cart')->assertJsonCount(0, 'items');
    }
}
