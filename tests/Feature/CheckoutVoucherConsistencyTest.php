<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pre-calculation (hash/tax) and transactional commit must agree on voucher validity:
 * an invalid voucher is rejected before any stock write or voucher claim.
 */
class CheckoutVoucherConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['cat_title' => 'Voucher']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'voucher-'.uniqid(), 'product_price' => '100000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 3]);

        return $product->fresh();
    }

    private function address(User $user): Address
    {
        return Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
    }

    public static function invalidVouchers(): array
    {
        return [
            'unknown code' => ['TIDAKADA', 'unknown', 'Voucher tidak valid.'],
            'inactive code' => ['MATI', 'inactive', 'Voucher tidak valid.'],
            'exhausted global quota' => ['HABIS', 'exhausted', 'Kuota voucher sudah habis.'],
            'per-user limit reached' => ['BATAS', 'per-user', 'Batas pemakaian voucher Anda sudah tercapai.'],
        ];
    }

    #[DataProvider('invalidVouchers')]
    public function test_invalid_voucher_rejected_in_pre_calculation_before_commit(string $code, string $voucherState, string $message): void
    {
        $voucher = null;
        if ($voucherState !== 'unknown') {
            $voucher = Voucher::create([
                'code' => $code, 'discount_amount' => 10000,
                'quota' => $voucherState === 'exhausted' ? 1 : 5,
                'used' => $voucherState === 'exhausted' ? 1 : 0,
                'per_user_limit' => $voucherState === 'per-user' ? 1 : 0,
                'is_active' => $voucherState !== 'inactive',
            ]);
        }
        $user = User::factory()->create();
        $address = $this->address($user);
        if ($voucherState === 'per-user') {
            // Prior non-cancelled order already consumed this user's single allowed use.
            Order::create([
                'user_id' => $user->id, 'status' => 'pending', 'subtotal' => 100000,
                'discount' => 10000, 'tax' => 0, 'shipping_fee' => 0, 'total' => 90000,
                'idempotency_key' => 'prior-voucher-use', 'payload_hash' => str_repeat('a', 64),
                'shipping_address' => $address->snapshot(), 'voucher_id' => $voucher->id,
            ]);
        }
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();

        $stockWrites = 0;
        DB::listen(function (QueryExecuted $query) use (&$stockWrites) {
            if (str_starts_with(strtolower($query->sql), 'update "products"')) {
                $stockWrites++;
            }
        });

        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'bad-voucher', 'voucher_code' => strtolower($code)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voucher_code' => $message]);

        // Same rejection as commit, but surfaced by pre-calculation: no stock write before the voucher is checked.
        $this->assertSame(0, $stockWrites, 'pre-calculation must reject the voucher before stock is touched');
        $this->assertDatabaseMissing('orders', ['idempotency_key' => 'bad-voucher']);
        $this->assertSame(3, (int) $product->fresh()->stock);
        if ($voucher) {
            $this->assertSame($voucher->used, $voucher->fresh()->used, 'voucher quota must not be claimed');
        }
    }

    public function test_valid_voucher_pre_calculation_matches_committed_totals(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1000]);
        Voucher::create(['code' => 'SUTRA', 'discount_amount' => 30000, 'quota' => 5]);
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $payload = ['address_id' => $address->id, 'idempotency_key' => 'good-voucher', 'voucher_code' => ' sutra '];

        $order = $this->postJson('/checkout', $payload)->assertCreated()->json('order');
        $this->assertSame(30000, $order['discount']);
        $this->assertSame(7000, $order['tax']);
        $this->assertSame(100000 - 30000 + 7000 + 25000, $order['total']);

        // Replay hash was built from the pre-calculated tax; it must match the committed snapshot.
        $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $order['id']);
    }

    public function test_quote_previews_voucher_totals_identical_to_order_without_claiming(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1000]);
        $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 30000, 'quota' => 5]);
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();

        $quote = $this->postJson('/checkout/quote', ['voucher_code' => ' sutra '])->assertOk()
            ->assertJsonPath('voucher_code', 'SUTRA')->json();
        $this->assertSame(0, $voucher->fresh()->used, 'quote must not claim quota');
        $this->assertSame(3, (int) $product->fresh()->stock);

        $order = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'quoted-key-1', 'voucher_code' => 'SUTRA'])
            ->assertCreated()->json('order');
        foreach (['subtotal', 'discount', 'tax', 'shipping_fee', 'total'] as $field) {
            $this->assertSame($order[$field], $quote[$field], "quote $field must equal order");
        }
    }

    public function test_quote_rejects_invalid_voucher_and_requires_login(): void
    {
        $this->postJson('/checkout/quote', ['voucher_code' => 'X'])->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/checkout/quote', ['voucher_code' => 'TIDAKADA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voucher_code' => 'Voucher tidak valid.']);
    }
}
