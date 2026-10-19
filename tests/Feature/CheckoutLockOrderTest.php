<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutLockOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $title): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Lock']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => $title, 'product_slug' => 'lock-'.uniqid(), 'product_price' => '100000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 10]);

        return $product->fresh();
    }

    public function test_stock_rows_are_decremented_in_ascending_product_id_order_regardless_of_cart_order(): void
    {
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
        $ids = [$this->product('A')->product_id, $this->product('B')->product_id, $this->product('C')->product_id];
        $cart = [];
        foreach (array_reverse($ids) as $id) {
            $cart[$id] = 1;
        }

        $decremented = [];
        DB::listen(function (QueryExecuted $query) use (&$decremented) {
            if (! preg_match('/^update\s+["`]?products["`]?\s+set\s+["`]?stock["`]?\s*=\s*["`]?stock["`]?\s*-/i', $query->sql)) {
                return;
            }
            // Ambil binding product_id berdasarkan posisi placeholder di SQL.
            $index = substr_count(substr($query->sql, 0, strpos($query->sql, 'product_id')), '?');
            $decremented[] = (int) $query->bindings[$index];
        });

        [$order] = app(CheckoutService::class)->checkout($user, $address, $cart, 'lock-order', null);

        $this->assertSame($ids, $decremented);
        // Urutan pembuatan order_items tetap mengikuti keranjang.
        $this->assertSame(array_keys($cart), $order->items->pluck('product_id')->map(fn ($id) => (int) $id)->all());
        foreach ($ids as $id) {
            $this->assertSame(9, (int) Product::find($id)->stock);
        }
    }
}
