<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock, bool $active = true): Product
    {
        $this->seed(CategorySeeder::class);

        return Product::create([
            'product_title' => 'Availability fixture', 'product_slug' => 'availability-fixture',
            'product_cat' => Category::firstOrFail()->cat_id, 'product_brand' => 'ZAFIRA',
            'product_price' => 100000, 'product_image' => '/favicon.svg',
            'stock' => $stock, 'is_active' => $active,
        ]);
    }

    private function assertAvailability(string $expected): void
    {
        $response = $this->get(route('products.show', 'availability-fixture'))->assertOk();
        $schema = json_decode($response->viewData('jsonLd'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org/'.$expected, $schema['offers']['availability']);
    }

    public function test_negative_stock_is_reported_out_of_stock(): void
    {
        $this->product(-3);
        $this->assertAvailability('OutOfStock');
    }

    public function test_active_zero_stock_is_out_of_stock(): void
    {
        $this->product(0);
        $this->assertAvailability('OutOfStock');
    }

    public function test_active_positive_stock_is_in_stock(): void
    {
        $this->product(1);
        $this->assertAvailability('InStock');
    }

    public function test_inactive_positive_stock_has_no_public_offer(): void
    {
        $this->product(1, false);
        $this->get(route('products.show', 'availability-fixture'))->assertNotFound()
            ->assertDontSee('https://schema.org/InStock', false);
    }

    public function test_stock_snapshot_is_fresh_after_cache_was_warmed(): void
    {
        $product = $this->product(1);
        $this->assertAvailability('InStock');
        $product->update(['stock' => 0]);
        $this->assertAvailability('OutOfStock');
        $product->update(['stock' => 1]);
        $this->assertAvailability('InStock');
    }

    public function test_checkout_then_cancel_refresh_warmed_pdp_availability(): void
    {
        $product = $this->product(1);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);

        $this->assertAvailability('InStock'); // warm cache
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'availability-1'])
            ->assertCreated()->json('order.id');
        $this->assertAvailability('OutOfStock');

        $this->postJson('/orders/'.$id.'/cancel')->assertOk();
        $this->assertAvailability('InStock');
    }

    public function test_admin_activation_and_deactivation_refresh_pdp_and_home(): void
    {
        $product = $this->product(1, false);
        $admin = Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
        $this->get(route('home'))->assertOk()->assertDontSee('Availability fixture');

        $this->actingAs($admin, 'admin')->patch(route('admin.products.update', $product->product_id), ['is_active' => true]);
        $this->assertAvailability('InStock');
        $this->get(route('home'))->assertSee('Availability fixture');

        $this->patch(route('admin.products.update', $product->product_id), ['is_active' => false]);
        $this->get(route('products.show', 'availability-fixture'))->assertNotFound();
        $this->get(route('home'))->assertDontSee('Availability fixture');
    }
}
