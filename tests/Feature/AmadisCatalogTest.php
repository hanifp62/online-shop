<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Requires parent to register: Route::get('/koleksi', [CatalogController::class, 'index'])->name('catalog.index');
class AmadisCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedProducts(int $count = 10): Category
    {
        $category = Category::create(['cat_title' => 'Tas']);
        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'product_cat' => $category->cat_id,
                'product_brand' => 'ZAFIRA',
                'product_title' => sprintf('Kreasi %02d', $i),
                'product_slug' => 'kreasi-'.$i,
                'product_price' => 100000 * $i,
                'product_color' => $i % 2 ? 'Saphir' : 'Noir',
                'product_material' => $i % 2 ? 'Sutra' : 'Kulit',
            ]);
        }

        return $category;
    }

    public function test_lists_eight_per_page_in_id_order_with_product_links(): void
    {
        $this->seedProducts();

        $response = $this->get('/koleksi')->assertOk();
        $response->assertSeeInOrder(['Kreasi 01', 'Kreasi 08']);
        $response->assertDontSee('Kreasi 09');
        $response->assertSee('/produk/kreasi-1', false);
        $response->assertSee('Rp100.000', false);
        $this->get('/koleksi?page=2')->assertOk()->assertSee('Kreasi 09')->assertDontSee('Kreasi 01');
    }

    public function test_search_and_attribute_filters_persist_in_pagination(): void
    {
        $category = $this->seedProducts(20);

        $this->get('/koleksi?q=sutra')->assertOk()->assertSee('Kreasi 01')->assertDontSee('Kreasi 02');
        $this->get('/koleksi?color=Noir')->assertOk()->assertSee('Kreasi 02')->assertDontSee('Kreasi 01');
        $this->get('/koleksi?category='.$category->cat_id.'&material=Sutra')
            ->assertOk()
            ->assertSee('category='.$category->cat_id, false)
            ->assertSee('material=Sutra', false);
    }

    public function test_like_wildcards_are_literal(): void
    {
        $this->seedProducts(3);
        $this->get('/koleksi?q=%25')->assertOk()->assertDontSee('Kreasi 01');
    }

    public function test_out_of_range_page_redirects_to_last_page_keeping_query(): void
    {
        $this->seedProducts();

        $this->get('/koleksi?page=99&locale=en')
            ->assertRedirect(url('/koleksi').'?locale=en&page=2');
    }

    public function test_locale_is_validated_and_labels_localized(): void
    {
        $this->seedProducts(1);

        $this->get('/koleksi?locale=zh')->assertOk()->assertSee('快速查看');
        $this->get('/koleksi?locale=en')->assertOk()->assertSee('Quick view');
        $this->get('/koleksi?locale=xx')->assertOk()->assertSee('<html lang="id">', false)->assertSee('Lihat cepat');
    }

    public function test_output_is_escaped_and_cart_form_uses_convention(): void
    {
        $category = Category::create(['cat_title' => 'Tas']);
        Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => '<script>alert(1)</script>', 'product_slug' => 'xss', 'product_price' => 1000,
        ]);

        $this->get('/koleksi')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('action="'.url('/cart/items').'"', false)
            ->assertSee('name="product_id"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('<dialog', false);
    }
}
