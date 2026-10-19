<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Butuh route: /belanja (shop.index) merender kontainer belanja nyata.
class AmadisShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_values_are_captured_before_controls_are_disabled(): void
    {
        $source = file_get_contents(resource_path('views/shop/index.blade.php'));

        $this->assertStringContainsString('const fields = new FormData(event.target); action(async () =>', $source);
        $this->assertStringContainsString('const fields = new FormData(form); action(async () =>', $source);
    }

    public function test_catalog_displays_localized_stock_count(): void
    {
        $this->product(37);

        foreach (['id' => 'Stok', 'en' => 'In stock', 'zh' => '库存', 'fr' => 'En stock', 'it' => 'Disponibilità'] as $locale => $label) {
            $this->get('/koleksi?locale='.$locale)->assertOk()->assertSee($label.': 37');
        }
    }

    private function product(int $stock = 3): Product
    {
        $category = Category::create(['cat_title' => 'Shop']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Shop Piece',
            'product_slug' => 'shop-'.uniqid(),
            'product_price' => '125000.00',
        ]);
        // stock tidak fillable; ikuti pola AmadisOrderTest.
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock]);

        return $product->fresh();
    }

    public function test_logged_in_shop_page_shows_checkout_form_and_catalog_shows_favorite(): void
    {
        $this->product();
        $this->actingAs(User::factory()->create());
        $this->get('/belanja')->assertOk()->assertSee('id="checkout-summary"', false)->assertSee('id="checkout-form"', false)->assertSee('Buat pesanan pending');
        $this->get('/koleksi')->assertOk()->assertSee('data-favorite=', false);
    }

    public function test_shop_page_renders_for_guest_and_discloses_pending_checkout(): void
    {
        $this->get('/belanja')->assertOk()
            ->assertSee('id="checkout-summary"', false)
            ->assertDontSee('id="guest-summary"', false)
            ->assertSee('Langkah 1/2', false)
            ->assertSee('pending / belum dibayar', false)
            ->assertSee('Tidak ada pembayaran online', false)
            ->assertSee('csrf-token', false)
            ->assertDontSee('id="checkout-form"', false)
            ->assertSee('id="guest-checkout-form"', false)
            ->assertSee('id="guest-track-form"', false);
    }

    public function test_shop_page_discloses_old_browser_cart_is_separate(): void
    {
        $this->get('/belanja')->assertOk()->assertSee('tidak tersambung', false);
    }

    public function test_catalog_links_to_shop_and_discloses_cart_limits(): void
    {
        $this->product();
        $this->get('/koleksi')->assertOk()
            ->assertSee('Belanja &amp; tas', false)
            ->assertSee('menggantikan jumlah lama', false);
    }

    public function test_cart_add_from_catalog_is_server_scoped(): void
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $this->getJson('/cart')->assertJsonPath('items.0.product_id', $product->product_id)
            ->assertJsonPath('items.0.quantity', 1);
    }
}
