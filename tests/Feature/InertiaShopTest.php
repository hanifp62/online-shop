<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true,
            'frontend.pages' => ['Shop/Index'],
            'inertia.testing.page_paths' => [resource_path('js/pages')],
        ]);
    }

    public function test_guest_gets_native_props_and_real_fallback_without_private_checkout_data(): void
    {
        $response = $this->get('/belanja')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Shop/Index')
            ->where('loggedIn', false)
            ->where('urls.checkout', route('checkout.store'))
            ->where('urls.track', route('orders.track'))
            ->where('copy.heading', __('shop_ui.shop.heading'))
            ->missing('html')->missing('cart')->missing('pending')->missing('tracking_token')
            ->missing('user')->missing('csrfToken')->missing('session'));
        $response->assertSee('data-react-fallback', false)
            ->assertSee('data-page="app"', false)
            ->assertSee('id="guest-checkout-form"', false)
            ->assertSee('id="guest-track-form"', false)
            ->assertSee('zafira:react-claim', false)
            ->assertSee('zafira:private-leave', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_account_props_do_not_serialize_identity_addresses_or_orders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/belanja')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Shop/Index')
            ->where('loggedIn', true)->where('urls.addresses', route('addresses.index'))
            ->where('urls.orders', route('orders.index'))
            ->missing('user')->missing('addresses')->missing('orders')->missing('favorites'));
        $response->assertSee('id="checkout-form"', false)
            ->assertSee('id="address-form"', false)
            ->assertDontSee('id="guest-checkout-form"', false);
        $this->assertStringNotContainsString($user->email, $response->getContent());
    }

    public function test_rollback_and_disabled_allowlist_keep_original_blade_forms(): void
    {
        foreach (['request', 'disabled', 'allowlist'] as $mode) {
            config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'allowlist' ? [] : ['Shop/Index']]);
            $response = $this->get('/belanja'.($mode === 'request' ? '?frontend=blade' : ''))->assertOk();
            $response->assertDontSee('data-page="app"', false)
                ->assertDontSee('data-react-fallback', false)
                ->assertSee('id="guest-checkout-form"', false)
                ->assertSee('window.shopRequest', false)
                ->assertSee('function shopFallback()', false);
        }
    }

    public function test_all_supported_locales_supply_server_translated_copy(): void
    {
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $this->get('/belanja?locale='.$locale)->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Shop/Index')
                    ->where('locale', $locale)
                    ->where('copy.guest_button', trans('shop_ui.shop.guest_button', [], $locale))
                    ->where('requestCopy.timeout', trans('shop_ui.request.timeout', [], $locale))
                    ->where('statuses.pending', trans('shop_ui.status.pending', [], $locale)));
        }
    }
}
