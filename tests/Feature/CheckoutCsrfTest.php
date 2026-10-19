<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutCsrfTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_TOKEN = 'session-token';

    protected function setUp(): void
    {
        parent::setUp();
        // Keep 422/429 from the checkout limiter from masking CSRF results.
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

    private function product(): Product
    {
        $category = Category::create(['cat_title' => 'Csrf']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'sutra-'.uniqid(), 'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5]);

        return $product->fresh();
    }

    /** Returns [payload, product] with a 2-unit cart prepared for the actor. */
    private function arrange(string $actor): array
    {
        $product = $this->product();
        if ($actor === 'guest') {
            $this->withSession(['cart' => [$product->product_id => 2]]);

            return [[
                'idempotency_key' => 'csrf-guest-key', 'customer_name' => 'Élodie',
                'customer_email' => 'elodie@example.test', 'customer_phone' => '081234567890',
                'recipient_name' => 'Élodie', 'phone' => '081234567890', 'label' => 'Rumah',
                'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12345',
            ], $product];
        }

        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
        // Seed the cart while CSRF is still bypassed.
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();

        return [['address_id' => $address->id, 'idempotency_key' => 'csrf-auth-key'], $product];
    }

    private function cartQuantity(string $actor, Product $product): int
    {
        return $actor === 'guest'
            ? (int) (session('cart')[$product->product_id] ?? 0)
            : (int) DB::table('cart_items')->sum('quantity');
    }

    public static function rejections(): array
    {
        $rows = [
            'no token, no Sec-Fetch-Site' => [[], []],
            'wrong header token' => [['X-CSRF-TOKEN' => 'wrong-token'], []],
            'wrong body token' => [[], ['_token' => 'wrong-token']],
            'cross-site, no token' => [['Sec-Fetch-Site' => 'cross-site'], []],
            'same-site, no token' => [['Sec-Fetch-Site' => 'same-site'], []],
        ];
        $cases = [];
        foreach (['guest', 'authenticated'] as $actor) {
            foreach ($rows as $name => [$headers, $body]) {
                $cases["$actor: $name"] = [$actor, $headers, $body];
            }
        }

        return $cases;
    }

    #[DataProvider('rejections')]
    public function test_checkout_rejects_forged_requests_without_side_effects(string $actor, array $headers, array $body): void
    {
        [$payload, $product] = $this->arrange($actor);
        $this->withSession(['_token' => self::SESSION_TOKEN]);
        $this->enforceCsrf();

        $this->withHeaders($headers)->postJson('/checkout', $payload + $body)->assertStatus(419);

        $this->assertSame(0, Order::count());
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(2, $this->cartQuantity($actor, $product));
        $this->assertDatabaseCount('order_items', 0);
    }

    public static function actors(): array
    {
        return ['guest' => ['guest'], 'authenticated' => ['authenticated']];
    }

    #[DataProvider('actors')]
    public function test_checkout_accepts_valid_csrf_header(string $actor): void
    {
        [$payload, $product] = $this->arrange($actor);
        $this->withSession(['_token' => self::SESSION_TOKEN]);
        $this->enforceCsrf();

        $this->withHeaders(['X-CSRF-TOKEN' => self::SESSION_TOKEN])->postJson('/checkout', $payload)->assertCreated();

        $this->assertSame(1, Order::count());
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    #[DataProvider('actors')]
    public function test_checkout_accepts_form_urlencoded_body_token(string $actor): void
    {
        [$payload, $product] = $this->arrange($actor);
        $this->withSession(['_token' => self::SESSION_TOKEN]);
        $this->enforceCsrf();

        $this->post('/checkout', $payload + ['_token' => self::SESSION_TOKEN])->assertCreated();

        $this->assertSame(1, Order::count());
        $this->assertSame(3, (int) $product->fresh()->stock);
    }
}
