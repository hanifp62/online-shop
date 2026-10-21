<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductStockUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
    }

    public function test_admin_can_create_product_with_stock_and_is_active(): void
    {
        $admin = Admin::first();
        $category = Category::first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
            'product_title' => 'Echarpe Cachemire',
            'product_cat' => $category->cat_id,
            'product_price' => 4500000,
            'product_image' => 'https://images.unsplash.com/photo-example',
            'stock' => 12,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'product_title' => 'Echarpe Cachemire',
            'stock' => 12,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_product_stock_and_visibility(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Test',
            'product_slug' => 'sac-test',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.products.update', $product->product_id), [
            'stock' => 25,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $fresh = $product->fresh();
        $this->assertSame(25, $fresh->stock);
        $this->assertFalse($fresh->is_active);
    }

    public function test_product_list_exposes_inline_stock_field_per_product(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Inline',
            'product_slug' => 'sac-inline',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 7,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Stok Sac Inline"', false)
            ->assertSee('value="7"', false)
            ->assertDontSee('Etalase masih kosong');
    }

    public function test_empty_catalog_shows_encouraging_state_with_open_create_form(): void
    {
        $admin = Admin::first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('Belum ada produk.');
        $response->assertSee('Etalase masih kosong. Tambahkan karya pertama Maison ZAFIRA.');
        $response->assertSee('href="#form-produk"', false);
        $this->assertMatchesRegularExpression('/<details id="form-produk"\s+open/', $response->getContent());
    }

    public function test_empty_search_result_offers_reset_instead_of_create(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Ada',
            'product_slug' => 'sac-ada',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', ['pq' => 'tidak-ada-produk-ini']))
            ->assertOk();

        $response->assertSee('Tidak ada produk yang cocok dengan pencarian.');
        $response->assertSee('Hapus pencarian');
        $response->assertDontSee('Etalase masih kosong');
    }
}
