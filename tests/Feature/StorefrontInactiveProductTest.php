<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontInactiveProductTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
        $this->category = Category::firstOrFail();
    }

    private function product(string $slug, bool $active = true): Product
    {
        $product = Product::create([
            'product_title' => 'Kreasi '.$slug, 'product_slug' => $slug,
            'product_cat' => $this->category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_price' => 100000, 'product_image' => '/favicon.svg',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['is_active' => $active]);

        return $product->fresh();
    }

    public function test_inactive_product_is_hidden_from_home_detail_related_and_concierge(): void
    {
        $this->product('aktif-satu');
        $this->product('aktif-dua');
        $this->product('nonaktif-rahasia', false);

        $this->get('/')->assertOk()->assertSee('data-id="aktif-satu"', false)->assertDontSee('nonaktif-rahasia');
        $this->get(route('products.show', 'nonaktif-rahasia'))->assertNotFound();
        $this->get(route('products.show', 'aktif-satu'))->assertOk()
            ->assertSee('Kreasi aktif-dua')->assertDontSee('Kreasi nonaktif-rahasia');
        $this->get(route('sitemap'))->assertOk()->assertDontSee('nonaktif-rahasia');

        $this->postJson(route('concierge.store'), [
            'name' => 'Klien', 'email' => 'klien@example.test', 'phone' => '0812345678',
            'service' => 'private-styling',
            'order_items' => json_encode([['id' => 'nonaktif-rahasia', 'quantity' => 1]]),
        ])->assertUnprocessable()->assertJsonValidationErrors('order_items.0.id');
    }

    public function test_admin_deactivation_invalidates_cached_storefront_responses(): void
    {
        $this->product('tas-satu');
        $this->product('tas-dua');
        $this->get('/')->assertSee('data-id="tas-dua"', false);
        $this->get(route('products.show', 'tas-dua'))->assertOk();
        $this->get(route('products.show', 'tas-satu'))->assertSee('Kreasi tas-dua');

        $target = Product::where('product_slug', 'tas-dua')->firstOrFail();
        $this->actingAs(Admin::firstOrFail(), 'admin')
            ->patch(route('admin.products.update', $target), ['is_active' => '0'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($target->fresh()->is_active);
        $this->get('/')->assertDontSee('data-id="tas-dua"', false);
        $this->get(route('products.show', 'tas-dua'))->assertNotFound();
        $this->get(route('products.show', 'tas-satu'))->assertDontSee('Kreasi tas-dua');
    }

    public function test_admin_can_set_bounded_stock_and_active_flag(): void
    {
        $admin = Admin::firstOrFail();
        $product = $this->product('stok-uji');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.products.update', $product), ['stock' => 12, 'is_active' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertTrue($product->fresh()->is_active);

        foreach ([['stock' => -1], ['stock' => 1.5], ['stock' => 'banyak'], ['stock' => 100001], ['is_active' => 'mungkin']] as $bad) {
            $this->patch(route('admin.products.update', $product), $bad)
                ->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertSame(12, $product->fresh()->stock);

        $this->post(route('admin.products.store'), [
            'product_title' => 'Sac Stok', 'product_cat' => $this->category->cat_id,
            'product_price' => 5000, 'product_image' => '/favicon.svg', 'stock' => 7, 'is_active' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $created = Product::where('product_slug', 'sac-stok')->firstOrFail();
        $this->assertSame(7, $created->stock);
        $this->assertFalse($created->is_active);

        // Defaults preserved when admin omits the new fields.
        $this->post(route('admin.products.store'), [
            'product_title' => 'Sac Bawaan', 'product_cat' => $this->category->cat_id,
            'product_price' => 5000, 'product_image' => '/favicon.svg',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $default = Product::where('product_slug', 'sac-bawaan')->firstOrFail();
        $this->assertSame(0, $default->stock);
        $this->assertTrue($default->is_active);
    }
}
