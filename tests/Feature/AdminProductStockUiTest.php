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
}
