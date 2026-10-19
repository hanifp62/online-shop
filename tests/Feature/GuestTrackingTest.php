<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeGuestOrder(): array
    {
        $category = Category::create(['cat_title' => 'Track']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Linen', 'product_slug' => 'linen-'.uniqid(), 'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 2]);
        $response = $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', [
            'idempotency_key' => 'tracking-key', 'customer_name' => 'Client', 'customer_email' => 'client@example.test',
            'customer_phone' => '081234567890', 'recipient_name' => 'Client', 'phone' => '081234567890',
            'label' => 'Rumah', 'line1' => 'Jalan Rahasia 123', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ])->assertCreated();

        return [$response->json('tracking_token'), Order::firstOrFail()];
    }

    public function test_valid_token_returns_minimal_private_no_store_projection(): void
    {
        [$token, $order] = $this->makeGuestOrder();
        $this->assertNotSame($token, $order->tracking_token_hash);
        $this->assertSame(hash('sha256', $token), $order->tracking_token_hash);
        $response = $this->postJson('/orders/track', ['token' => $token])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.items.0.product_title', 'Linen');
        $body = $response->getContent();
        foreach (['client@example.test', '081234567890', 'Jalan Rahasia 123', $token, 'customer_email', 'shipping_address'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $body);
        }
    }

    public function test_invalid_expired_and_id_only_requests_are_uniform_no_store(): void
    {
        [$token, $order] = $this->makeGuestOrder();
        foreach ([['token' => str_repeat('x', 64)], ['order_id' => $order->id], ['token' => $token]] as $index => $input) {
            if ($index === 2) {
                $order->update(['tracking_expires_at' => now()->subSecond()]);
            }
            $response = $this->postJson('/orders/track', $input)->assertStatus(404)->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame('Pesanan tidak ditemukan.', $response->json('message'));
        }
    }

    public function test_token_is_body_only_rate_limited_and_get_query_never_looks_up(): void
    {
        [$token] = $this->makeGuestOrder();
        $this->get('/orders/track?token='.urlencode($token))->assertRedirect('/belanja');
        $this->get('/orders/track')->assertRedirect('/belanja');
        $this->assertDatabaseCount('orders', 1);
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/orders/track', ['token' => str_repeat('z', 64)]);
        }
        $response->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_token_replay_is_only_available_in_issuing_session(): void
    {
        [$token] = $this->makeGuestOrder();
        session()->flush();
        $this->postJson('/orders/track', ['token' => $token])->assertOk();
        $this->withSession(['cart' => []])->postJson('/checkout', [
            'idempotency_key' => 'tracking-key', 'customer_name' => 'Client', 'customer_email' => 'client@example.test',
            'customer_phone' => '081234567890', 'recipient_name' => 'Client', 'phone' => '081234567890',
            'label' => 'Rumah', 'line1' => 'Jalan Rahasia 123', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ])->assertUnprocessable();
    }
}
