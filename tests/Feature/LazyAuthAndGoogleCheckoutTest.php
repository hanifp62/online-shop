<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Mockery;
use Tests\TestCase;

class LazyAuthAndGoogleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function seedLuxuryProduct(int $stock = 8): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Prêt-à-Porter']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Robe Aurore Saphir',
            'product_slug' => 'robe-aurore-saphir-'.uniqid(),
            'product_price' => '4500000.00',
            'product_desc' => 'Sutra mulberry Italia dengan potongan fluid.',
            'product_image' => 'robe_aurore.webp',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock, 'is_active' => true]);

        return $product->fresh();
    }

    public function test_lazy_authenticator_allows_guest_variant_cart_blocks_guest_checkout_merges_on_login_and_persists_order_variants(): void
    {
        config([
            'services.shop.require_auth_checkout' => true,
            'services.google.client_id' => 'google-client-test-id',
            'services.google.client_secret' => 'google-client-test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $product = $this->seedLuxuryProduct(6);
        $user = User::factory()->create(['email' => 'client@zafira.test']);
        $address = Address::create([
            'user_id' => $user->id,
            'label' => 'Butik Pribadi',
            'recipient_name' => 'Madame Claire',
            'phone' => '081234567890',
            'line1' => 'Jl. Senopati No. 88',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12190',
            'is_default' => true,
        ]);

        // 1. Guest freely explores & adds two distinct variants of the same product into Guest Cart
        $this->postJson('/cart/items', [
            'product_id' => $product->product_id,
            'quantity' => 2,
            'size' => 'M',
            'monogram' => 'zf',
        ])->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.size', 'M')
            ->assertJsonPath('items.0.monogram', 'ZF')
            ->assertJsonPath('items.0.quantity', 2);

        $this->postJson('/cart/items', [
            'product_id' => $product->product_id,
            'quantity' => 1,
            'size' => 'L',
            'mode' => 'add',
        ])->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('subtotal', 13500000);

        // Aggregate stock check across variants of the same product_id (stock = 6, current = 3, adding 4 -> 422)
        $this->postJson('/cart/items', [
            'product_id' => $product->product_id,
            'quantity' => 4,
            'size' => 'XL',
            'mode' => 'add',
        ])->assertUnprocessable();

        // 2. Guest visits /belanja -> Lazy Auth Wall & 1-Click Google Login props/DOM are rendered
        $this->get('/belanja')
            ->assertOk()
            ->assertSee('checkout-auth-wall', false)
            ->assertSee('google-checkout-login', false);

        // 3. Guest attempts POST /checkout -> blocked smoothly with 401 Lazy Auth Wall + intended checkout URLs, zero stock/order mutation
        $this->postJson('/checkout', [
            'idempotency_key' => 'guest-blocked-001',
            'address_id' => $address->id,
        ])->assertUnauthorized()
            ->assertJsonPath('auth_required', true)
            ->assertJson(fn ($json) => $json
                ->where('auth_required', true)
                ->whereType('message', 'string')
                ->whereType('login_url', 'string')
                ->whereType('register_url', 'string')
                ->whereType('google_url', 'string')
                ->etc()
            );

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(6, (int) DB::table('products')->where('product_id', $product->product_id)->value('stock'));

        // 4. Guest authenticates with ?intended=/belanja%23checkout -> Guest Cart merges automatically & redirects straight to /belanja#checkout
        $this->post('/login?intended=%2Fbelanja%23checkout', [
            'email' => 'client@zafira.test',
            'password' => 'password',
        ])->assertRedirect('/belanja#checkout');

        $this->assertAuthenticatedAs($user);

        $cartResponse = $this->getJson('/cart')->assertOk()->assertJsonPath('subtotal', 13500000);
        $this->assertCount(2, $cartResponse->json('items'));

        // 5. Authenticated user completes POST /checkout -> Order & OrderItems persist exact size & monogram variants and decrement stock atomically
        $checkout = $this->postJson('/checkout', [
            'idempotency_key' => 'auth-checkout-001',
            'address_id' => $address->id,
        ])->assertCreated();

        $orderId = $checkout->json('order.id');
        $this->assertNotNull($orderId);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->product_id,
            'quantity' => 2,
            'size' => 'M',
            'monogram' => 'ZF',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->product_id,
            'quantity' => 1,
            'size' => 'L',
            'monogram' => null,
        ]);
        $this->assertSame(3, (int) DB::table('products')->where('product_id', $product->product_id)->value('stock'));
    }

    public function test_google_oauth_one_click_checkout_merges_guest_variant_cart_and_sanitizes_open_redirects(): void
    {
        config([
            'services.shop.require_auth_checkout' => true,
            'services.google.client_id' => 'google-client-test-id',
            'services.google.client_secret' => 'google-client-test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $product = $this->seedLuxuryProduct(5);

        // Guest adds bespoke FR 38 + monogram AV to Guest Cart
        $this->postJson('/cart/items', [
            'product_id' => $product->product_id,
            'quantity' => 1,
            'size' => 'FR 38',
            'monogram' => 'AV',
        ])->assertOk();

        $googleIdentity = (new SocialiteTwoUser)->setRaw([
            'sub' => 'google-oauth-vip-777',
            'email_verified' => true,
        ])->map([
            'id' => 'google-oauth-vip-777',
            'name' => 'Vivienne Laurent',
            'email' => 'vivienne.google@zafira.test',
        ]);

        $providerMock = Mockery::mock(Provider::class);
        $providerMock->shouldReceive('redirect')
            ->twice()
            ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));
        $providerMock->shouldReceive('user')
            ->once()
            ->andReturn($googleIdentity);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($providerMock);

        // Open-redirect attempt must NOT be stored as external URL
        $this->get('/auth/google/redirect?intended=https://evil.example/phish')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->assertNull(session('url.intended'));

        // Legitimate checkout redirect is captured cleanly
        $this->get('/auth/google/redirect?intended=%2Fbelanja%23checkout')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->assertSame('/belanja#checkout', session('url.intended'));

        // Google OAuth callback creates verified account, merges Guest Cart with variants, and redirects to /belanja#checkout
        $this->get('/auth/google/callback')
            ->assertRedirect('/belanja#checkout');

        $user = User::where('email', 'vivienne.google@zafira.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.product_id', $product->product_id)
            ->assertJsonPath('items.0.size', 'FR 38')
            ->assertJsonPath('items.0.monogram', 'AV');
    }
}
