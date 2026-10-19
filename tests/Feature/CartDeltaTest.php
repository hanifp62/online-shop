<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// FR-201: +/- tas bersifat relatif terhadap state server (anti lost-update lintas tab); SET eksplisit tetap ada.
// Uji ini memakai SQLite in-memory: tidak membuktikan konkurensi MySQL.
class CartDeltaTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 10, bool $active = true): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Delta']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra',
            'product_slug' => 'delta-'.uniqid(),
            'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock, 'is_active' => $active]);

        return $product->fresh();
    }

    private function delta(Product|int $product, mixed $delta, array $extra = [])
    {
        $id = $product instanceof Product ? $product->product_id : $product;

        return $this->postJson("/cart/items/{$id}/delta", ['delta' => $delta] + $extra);
    }

    public function test_delta_is_relative_to_server_state_not_client_view(): void
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();
        // Tab A still believes quantity is 2; tab B already moved it to 5 via SET.
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 5])->assertOk();

        $this->delta($product, 1)->assertOk()->assertJsonPath('items.0.quantity', 6);
        $this->delta($product, 1)->assertOk()->assertJsonPath('items.0.quantity', 7);
        $this->delta($product, -1)->assertOk()->assertJsonPath('items.0.quantity', 6);
    }

    public function test_positive_delta_creates_line_and_ignores_client_price(): void
    {
        $product = $this->product();
        $this->delta($product, 1, ['price' => 1])->assertOk()
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('items.0.unit_price', 125000);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->product_id, 'quantity' => 1]);
    }

    public function test_decrement_to_zero_removes_line_and_missing_line_is_a_noop(): void
    {
        $product = $this->product();
        $this->delta($product, -1)->assertOk()->assertJsonCount(0, 'items');
        $this->assertDatabaseCount('carts', 0);

        $this->delta($product, 1)->assertOk();
        $this->delta($product, -1)->assertOk()->assertJsonCount(0, 'items');
        $this->assertDatabaseCount('cart_items', 0);
        $this->delta($product, -3)->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_delta_above_limit_or_stock_is_rejected_and_cart_unchanged(): void
    {
        $limited = $this->product(2);
        $this->delta($limited, 2)->assertOk()->assertJsonPath('items.0.quantity', 2);
        $this->delta($limited, 1)->assertUnprocessable();
        $this->getJson('/cart')->assertJsonPath('items.0.quantity', 2);

        $plenty = $this->product(500);
        $this->postJson('/cart/items', ['product_id' => $plenty->product_id, 'quantity' => 99])->assertOk();
        $this->delta($plenty, 1)->assertUnprocessable();
        $this->getJson('/cart')->assertJsonPath('items.1.quantity', 99);
    }

    public function test_inactive_product_cannot_be_incremented_but_can_be_decremented(): void
    {
        $product = $this->product(10);
        $this->delta($product, 2)->assertOk();
        DB::table('products')->where('product_id', $product->product_id)->update(['is_active' => false]);

        $this->delta($product, 1)->assertUnprocessable();
        $this->delta($product, -1)->assertOk()->assertJsonPath('items.0.quantity', 1);
    }

    public function test_delta_validation_and_personalization_boundary(): void
    {
        $product = $this->product();
        foreach ([0, 100, -100, 'x', null, 1.5] as $bad) {
            $this->delta($product, $bad)->assertUnprocessable()->assertJsonValidationErrors('delta');
        }
        $this->delta($product, 1, ['size' => 'FR 38'])->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->delta($product, 1, ['monogram' => 'ZA'])->assertUnprocessable()->assertJsonValidationErrors('monogram');
        $this->getJson('/cart')->assertJsonCount(0, 'items');
    }

    public function test_unknown_or_non_numeric_product_is_not_found(): void
    {
        $this->delta(999999, 1)->assertNotFound();
        $this->postJson('/cart/items/abc/delta', ['delta' => 1])->assertNotFound();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_delta_applies_to_authenticated_account_cart(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user)->delta($product, 3)->assertOk()->assertJsonPath('items.0.quantity', 3);
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->delta($product, -1)->assertOk()->assertJsonPath('items.0.quantity', 2);
    }

    public function test_destroy_reports_quantity_removed_under_lock_for_undo(): void
    {
        $product = $this->product();
        $this->delta($product, 2)->assertOk();
        // Another tab adds one after this tab's last GET; undo must restore 3, not the stale 2.
        $this->delta($product, 1)->assertOk();
        $this->deleteJson("/cart/items/{$product->product_id}")->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('removed_quantity', 3);
        $this->deleteJson("/cart/items/{$product->product_id}")->assertOk()->assertJsonPath('removed_quantity', 0);
    }

    public function test_cart_success_responses_are_not_cached(): void
    {
        $product = $this->product();
        $id = $product->product_id;

        $this->getJson('/cart')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/cart/items', ['product_id' => $id, 'quantity' => 1])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->delta($product, 1)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->deleteJson("/cart/items/{$id}")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->deleteJson('/cart')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_cart_validation_and_missing_product_responses_are_not_cached(): void
    {
        $this->postJson('/cart/items', ['product_id' => 999999, 'quantity' => 0])
            ->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
        $this->delta(999999, 1)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_cart_rate_limit_response_is_not_cached(): void
    {
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/cart');
        }

        $response->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_delta_requires_csrf_like_other_cart_mutations(): void
    {
        $product = $this->product();
        $this->app->instance('env', 'local');
        $this->postJson("https://localhost/cart/items/{$product->product_id}/delta", ['delta' => 1], ['Sec-Fetch-Site' => 'cross-site'])
            ->assertStatus(419);
    }
}
