<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandHeritageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AdminSeeder::class, CategorySeeder::class, ProductSeeder::class]);
    }

    public function test_storefront_shows_three_heritages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Tiga tanah, satu Maison')
            ->assertSee('Jiwa Nusantara')
            ->assertSee('La Mano Italiana');
    }

    public function test_storefront_lists_nusantara_and_capsule_categories(): void
    {
        $response = $this->get('/')->assertOk();
        $response->assertSee('Warisan Nusantara');
        $response->assertSee('Koleksi Kapsul');
        $response->assertSee('Kebaya Lumière');
        $response->assertSee('Chemise Rivière');
    }

    public function test_product_detail_shows_provenance(): void
    {
        $this->get('/produk/kebaya-lumiere')
            ->assertOk()
            ->assertSee('Brokat + sutra organza')
            ->assertSee('Bordir tangan: Kebayoran')
            ->assertSee('Keahlian &amp; perawatan', false);
    }

    public function test_product_detail_shows_craft_for_heritage_piece(): void
    {
        $this->get('/produk/chemise-riviere')
            ->assertOk()
            ->assertSee('Batik tulis: Laweyan, Solo')
            ->assertSee('French seam');
    }

    public function test_product_json_ld_includes_material(): void
    {
        $response = $this->get('/produk/sac-seraphine')->assertOk();
        $response->assertSee('"material":"Kulit anak sapi full-grain"', false);
    }

    public function test_home_carries_quick_view_quality_data(): void
    {
        $response = $this->get('/')->assertOk();
        $response->assertSee('data-material="Kulit anak sapi full-grain"', false);
        $response->assertSee('data-origin="Kulit: Santa Croce', false);
    }

    public function test_admin_can_store_product_quality_fields(): void
    {
        $admin = Admin::first();
        $category = Category::first();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), [
                'product_title' => 'Robe Songket',
                'product_cat' => $category->cat_id,
                'product_price' => 21000000,
                'product_image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=800&q=88',
                'product_material' => 'Songket sutra',
                'product_composition' => 'Sutra 70%, benang emas 30%',
                'product_origin' => 'Songket: Palembang',
                'product_care' => 'Dry clean saja.',
                'product_craft' => 'Ditenun 6 minggu.',
            ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'product_title' => 'Robe Songket',
            'product_material' => 'Songket sutra',
            'product_origin' => 'Songket: Palembang',
        ]);
    }

    public function test_legacy_products_without_quality_still_render(): void
    {
        $product = Product::where('product_slug', 'riva-loafer')->firstOrFail();
        $product->update([
            'product_material' => null,
            'product_composition' => null,
            'product_origin' => null,
            'product_care' => null,
            'product_craft' => null,
        ]);

        $this->get('/produk/riva-loafer')
            ->assertOk()
            ->assertSee('Mocassin Riva')
            ->assertDontSee('product-provenance');
    }
}
