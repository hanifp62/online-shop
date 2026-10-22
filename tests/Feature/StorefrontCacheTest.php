<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\StorefrontController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontCacheReclaimer;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $revision = AdminCategoryController::storefrontRevision();
        $relatedKey = "storefront:related:{$category->cat_id}:{$product->product_id}:v4:{$revision}";
        // Guard against vacuous passes: the live keys must be warm before invalidation.
        $this->assertNotNull(Cache::get("storefront:products:v4:{$revision}"));
        $this->assertNotNull(Cache::get("storefront:categories:v3:{$revision}"));
        $this->assertNotNull(Cache::get($relatedKey));

        $this->actingAs(Admin::firstOrFail(), 'admin')
            ->patch(route('admin.categories.update', $category), ['cat_title' => 'Atelier Baru'])
            ->assertRedirect();

        $next = AdminCategoryController::storefrontRevision();
        $this->assertNotSame($revision, $next);
        $this->assertNull(Cache::get("storefront:products:v4:{$next}"));
        $this->assertNull(Cache::get("storefront:categories:v3:{$next}"));
        $this->assertNull(Cache::get("storefront:related:{$category->cat_id}:{$product->product_id}:v4:{$next}"));
        $this->get('/')->assertOk()->assertSee('Atelier Baru');
        $this->get(route('products.show', $product->product_slug))->assertOk()->assertSee('Atelier Baru');
        $this->assertSame('keep', Cache::get('unrelated:marker'));
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'client@example.com'])->assertTooManyRequests();
    }

    public function test_expired_database_generations_are_reclaimed_in_bounded_targeted_batches(): void
    {
        $repository = Cache::store('database');
        $store = $repository->getStore();
        $prefix = $store->getPrefix();
        $table = config('cache.stores.database.table', 'cache');
        $keys = [];
        foreach (['generation-one', 'generation-two', 'generation-three'] as $generation) {
            foreach (["storefront:products:v4:{$generation}", "storefront:categories:v3:{$generation}", "storefront:related:1:2:v4:{$generation}"] as $key) {
                $repository->put($key, ['old'], 300);
                $keys[] = $prefix.$key;
            }
        }
        $repository->put('unrelated:limiter', 7, 300);
        $repository->forever('storefront:revision:v1', 'current');
        $this->travel(6)->minutes();
        $repository->put('storefront:products:v4:current', ['fresh'], 300);
        // Query raw rows, never get expired cache keys (which would hide the lazy-expiry leak).
        $this->assertSame(9, DB::table($table)->whereIn('key', $keys)->count());
        $this->assertSame(2, StorefrontCacheReclaimer::pruneExpired(2, 'database'));
        $this->assertSame(7, DB::table($table)->whereIn('key', $keys)->count());
        $removed = 2;
        for ($batch = 0; $batch < 4; $batch++) {
            $count = StorefrontCacheReclaimer::pruneExpired(2, 'database');
            $this->assertLessThanOrEqual(2, $count);
            $removed += $count;
        }
        $this->assertSame(9, $removed);
        $this->assertSame(0, DB::table($table)->whereIn('key', $keys)->count());
        $this->assertTrue(DB::table($table)->where('key', $prefix.'unrelated:limiter')->exists());
        $this->assertSame(['fresh'], $repository->get('storefront:products:v4:current'));
        $this->assertSame('current', $repository->get('storefront:revision:v1'));
        $this->assertSame(0, StorefrontCacheReclaimer::pruneExpired(2, 'database'));
    }

    public function test_warm_home_and_related_are_fresh_after_product_update_move_deactivate_and_delete(): void
    {
        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::firstOrFail(), 'admin');
        $first = Category::create(['cat_title' => 'First atelier']);
        $second = Category::create(['cat_title' => 'Second atelier']);
        $make = fn (string $slug, Category $category) => Product::create([
            'product_title' => $slug, 'product_slug' => $slug,
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_price' => 100000, 'stock' => 4, 'is_active' => true,
        ]);
        $anchor = $make('anchor-one', $first);
        $otherAnchor = $make('anchor-two', $second);
        $target = $make('mutable-product', $first);
        $controller = app(StorefrontController::class);
        $home = fn () => $controller->index()->getData()['products']->keyBy('id');
        $related = fn (Product $product) => $controller->show($product->product_slug)->getData()['related']->keyBy('product_id');
        $this->assertSame(4, $home()[$target->product_slug]['stock']);
        $this->assertTrue($related($anchor)->has($target->product_id));
        $this->assertFalse($related($otherAnchor)->has($target->product_id));
        $this->patch(route('admin.products.update', $target), ['product_title' => 'Updated product', 'stock' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Updated product', $home()[$target->product_slug]['name']);
        $this->assertSame(0, $home()[$target->product_slug]['stock']);
        $this->assertSame(0, $related($anchor)[$target->product_id]->stock);
        $this->patch(route('admin.products.update', $target), ['product_cat' => $second->cat_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Second atelier', $home()[$target->product_slug]['category']);
        $this->assertFalse($related($anchor)->has($target->product_id));
        $this->assertSame('Second atelier', $related($otherAnchor)[$target->product_id]->category->cat_title);
        $this->patch(route('admin.products.update', $target), ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($home()->has($target->product_slug));
        $this->assertFalse($related($otherAnchor)->has($target->product_id));
        $this->patch(route('admin.products.update', $target), ['is_active' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($home()->has($target->product_slug));
        $this->assertTrue($related($otherAnchor)->has($target->product_id));
        $this->delete(route('admin.products.destroy', $target))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($home()->has($target->product_slug));
        $this->assertFalse($related($otherAnchor)->has($target->product_id));
        $this->assertFalse($related($anchor)->has($target->product_id));
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
        $this->assertNull(Cache::get('storefront:product:tas-atelier:v2'));
        $this->get(route('products.show', 'tas-baru'))->assertOk();
        $this->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertNull(Cache::get('storefront:product:tas-baru:v2'));
    }
}
