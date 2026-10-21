<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// FR-201: pembelian standar memakai tas server; permintaan ukuran/monogram tetap melalui concierge.
class UnifiedPurchaseCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['cat_title' => 'Tas Tangan']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Seraphine',
            'product_slug' => 'sac-seraphine',
            'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5]);

        return $product->fresh();
    }

    public function test_home_and_product_expose_numeric_purchase_id_and_separate_personalization(): void
    {
        $product = $this->product();
        $this->get('/')->assertOk()
            ->assertSee('data-product-id="'.$product->product_id.'"', false)
            ->assertSee('Pembelian katalog', false)
            ->assertSee('data-cart-items', false);
        $this->get('/produk/sac-seraphine')->assertOk()
            ->assertSee('data-product-id="'.$product->product_id.'"', false)
            ->assertSee('Ukuran dan monogram hanya melalui concierge', false)
            ->assertSee('data-open-concierge', false);
    }

    public function test_server_cart_sets_quantity_and_survives_login_with_authoritative_price(): void
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2, 'price' => 1])
            ->assertOk()->assertJsonPath('items.0.unit_price', 125000)
            ->assertJsonPath('items.0.quantity', 2);
        // Real login: merge is only authorized by AuthController, not by actingAs.
        $user = User::factory()->create(['password' => 'Test-password-123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-123!'])->assertRedirect('/');
        $this->getJson('/cart')->assertOk()->assertJsonPath('subtotal', 250000);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])
            ->assertOk()->assertJsonPath('items.0.quantity', 1);
        $this->get('/belanja')->assertOk()->assertSee('Tas Belanja', false);
    }

    public function test_personalization_is_rejected_at_server_purchase_boundary(): void
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1, 'size' => 'FR 38'])
            ->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1, 'monogram' => 'ZA'])
            ->assertUnprocessable()->assertJsonValidationErrors('monogram');
        $this->getJson('/cart')->assertJsonCount(0, 'items');
    }
}
