<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_update_invalidates_catalog_without_resetting_newsletter_limit(): void
    {
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
        $category = Category::firstOrFail();
        $product = Product::create([
            'product_title' => 'Tas Atelier', 'product_slug' => 'tas-atelier',
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_price' => 100000, 'product_image' => '/favicon.svg',
        ]);
        $this->get('/')->assertOk();
        $this->get(route('products.show', $product->product_slug))->assertOk();
        Cache::put('unrelated:marker', 'keep', 300);
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();

        $this->actingAs(Admin::firstOrFail(), 'admin')
            ->patch(route('admin.categories.update', $category), ['cat_title' => 'Atelier Baru'])
            ->assertRedirect();

        $this->assertNull(Cache::get('storefront:products:v1'));
        $this->assertNull(Cache::get('storefront:categories:v1'));
        $this->assertNull(Cache::get('storefront:product:tas-atelier:v1'));
        $this->assertNull(Cache::get("storefront:related:{$category->cat_id}:{$product->product_id}:v1"));
        $this->assertSame('keep', Cache::get('unrelated:marker'));
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertTooManyRequests();
    }

    public function test_product_rename_and_delete_invalidate_old_detail_keys(): void
    {
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
        $category = Category::firstOrFail();
        $product = Product::create([
            'product_title' => 'Tas Atelier', 'product_slug' => 'tas-atelier',
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_price' => 100000, 'product_image' => '/favicon.svg',
        ]);
        $this->get(route('products.show', 'tas-atelier'))->assertOk();
        $this->actingAs(Admin::firstOrFail(), 'admin')
            ->patch(route('admin.products.update', $product), ['product_slug' => 'tas-baru'])->assertRedirect();
        $this->assertNull(Cache::get('storefront:product:tas-atelier:v1'));
        $this->get(route('products.show', 'tas-baru'))->assertOk();
        $this->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertNull(Cache::get('storefront:product:tas-baru:v1'));
    }
}
