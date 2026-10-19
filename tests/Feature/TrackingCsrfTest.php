<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrackingCsrfTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_TOKEN = 'session-token';

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the track limiter from masking CSRF results.
        Cache::flush();
    }

    /** Laravel skips CSRF under unit tests; re-enable only that bypass. */
    private function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /** Creates a guest order while CSRF is still bypassed, then enforces CSRF. */
    private function trackedOrder(): string
    {
        $category = Category::create(['cat_title' => 'Track']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Linen', 'product_slug' => 'linen-'.uniqid(), 'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 2]);
        $token = $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', [
            'idempotency_key' => 'tracking-csrf', 'customer_name' => 'Client', 'customer_email' => 'client@example.test',
            'customer_phone' => '081234567890', 'recipient_name' => 'Client', 'phone' => '081234567890',
            'label' => 'Rumah', 'line1' => 'Jalan Rahasia 123', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ])->assertCreated()->json('tracking_token');

        $this->withSession(['_token' => self::SESSION_TOKEN]);
        $this->enforceCsrf();

        return $token;
    }

    public static function rejections(): array
    {
        return [
            'no token, no Sec-Fetch-Site' => [[], []],
            'wrong header token' => [['X-CSRF-TOKEN' => 'wrong-token'], []],
            'wrong body token' => [[], ['_token' => 'wrong-token']],
            'cross-site, no token' => [['Sec-Fetch-Site' => 'cross-site'], []],
            'same-site, no token' => [['Sec-Fetch-Site' => 'same-site'], []],
        ];
    }

    #[DataProvider('rejections')]
    public function test_track_rejects_forged_requests_without_leaking_order_data(array $headers, array $body): void
    {
        $token = $this->trackedOrder();
        $before = $this->writeSnapshot();
        $this->assertSame(1, $before['orders']);
        $this->assertCount(1, $before['tracked']);

        $response = $this->withHeaders($headers)->postJson('/orders/track', ['token' => $token] + $body)->assertStatus(419);

        $this->assertSame($before, $this->writeSnapshot());
        $content = $response->getContent();
        foreach ([$token, 'Linen', '"order"', '"items"', 'pending', 'client@example.test', 'Jalan Rahasia 123'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $content);
        }
    }

    /** Raw DB state a forged request must never change. */
    private function writeSnapshot(): array
    {
        return [
            'orders' => Order::count(),
            'order_items' => DB::table('order_items')->count(),
            'stock' => (int) Product::sum('stock'),
            'tracked' => DB::table('orders')->orderBy('id')->get(['status', 'total', 'updated_at'])
                ->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function test_track_accepts_valid_csrf_header(): void
    {
        $token = $this->trackedOrder();

        $this->withHeaders(['X-CSRF-TOKEN' => self::SESSION_TOKEN])->postJson('/orders/track', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.items.0.product_title', 'Linen');
    }

    public function test_track_accepts_form_urlencoded_body_token(): void
    {
        $token = $this->trackedOrder();

        $this->post('/orders/track', ['token' => $token, '_token' => self::SESSION_TOKEN])
            ->assertOk()
            ->assertJsonPath('order.items.0.product_title', 'Linen');
    }

    public function test_valid_csrf_with_invalid_tracking_token_is_not_found(): void
    {
        $this->trackedOrder();

        $this->withHeaders(['X-CSRF-TOKEN' => self::SESSION_TOKEN])
            ->postJson('/orders/track', ['token' => str_repeat('a', 64)])
            ->assertNotFound()
            ->assertJsonPath('message', 'Pesanan tidak ditemukan.');
    }
}
