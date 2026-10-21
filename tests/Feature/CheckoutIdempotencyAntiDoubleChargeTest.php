<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Verifikasi anti double-charge: submit checkout dengan kunci idempotensi identik menghasilkan pesanan tunggal dan stok aman. */
class CheckoutIdempotencyAntiDoubleChargeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Address $address;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['cat_title' => 'Couture']);
        $this->product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Robe Soirée', 'product_slug' => 'robe-soiree-'.uniqid(), 'product_price' => '1500000.00',
        ]);
        DB::table('products')->where('product_id', $this->product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->address = $this->address('Rumah');
    }

    private function address(string $label): Address
    {
        return Address::create([
            'user_id' => $this->user->id, 'label' => $label, 'recipient_name' => 'Vivienne',
            'phone' => '08123456789', 'line1' => 'Jl. Sudirman', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
    }

    public function test_same_idempotency_key_replays_order_without_double_stock_deduction(): void
    {
        $key = Str::uuid()->toString();
        $cart = [$this->product->product_id => 2];

        $response1 = $this->actingAs($this->user)
            ->withSession(['cart' => $cart])
            ->postJson('/checkout', [
                'address_id' => $this->address->id,
                'idempotency_key' => $key,
            ])
            ->assertStatus(201);

        $orderId = $response1->json('order.id');
        $this->assertNotNull($orderId);
        $this->assertSame(3, (int) $this->product->fresh()->stock);
        $this->assertSame(1, Order::where('user_id', $this->user->id)->count());

        // Replay: kirim ulang request identik (misal klik ganda / retry jaringan).
        // Sesi keranjang mungkin sudah kosong karena order pertama berhasil.
        $response2 = $this->actingAs($this->user)
            ->withSession(['cart' => []])
            ->postJson('/checkout', [
                'address_id' => $this->address->id,
                'idempotency_key' => $key,
            ])
            ->assertStatus(200);

        $this->assertSame($orderId, $response2->json('order.id'));
        $this->assertSame(3, (int) $this->product->fresh()->stock, 'Stok tidak boleh terpotong dua kali');
        $this->assertSame(1, Order::where('user_id', $this->user->id)->count(), 'Order tidak boleh terduplikasi');
    }

    public function test_reusing_key_with_different_payload_is_rejected_with_conflict_409(): void
    {
        $key = Str::uuid()->toString();

        $this->actingAs($this->user)
            ->withSession(['cart' => [$this->product->product_id => 1]])
            ->postJson('/checkout', [
                'address_id' => $this->address->id,
                'idempotency_key' => $key,
            ])
            ->assertStatus(201);

        // Kunci sama, tetapi alamat berbeda
        $altAddress = $this->address('Kantor');

        $this->actingAs($this->user)
            ->withSession(['cart' => [$this->product->product_id => 1]])
            ->postJson('/checkout', [
                'address_id' => $altAddress->id,
                'idempotency_key' => $key,
            ])
            ->assertStatus(409);
    }

    public function test_empty_cart_without_idempotency_replay_is_validation_error(): void
    {
        $newKey = Str::uuid()->toString();

        $this->actingAs($this->user)
            ->withSession(['cart' => []])
            ->postJson('/checkout', [
                'address_id' => $this->address->id,
                'idempotency_key' => $newKey,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }
}
