<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminOrderController;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutLocaleTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return array_map(fn ($locale) => [$locale], ['id', 'en', 'fr', 'it', 'zh']);
    }

    #[DataProvider('locales')]
    public function test_authenticated_checkout_snapshots_request_locale_and_preserves_it_after_profile_change_and_replay(string $locale): void
    {
        $user = User::factory()->create(['locale' => $locale === 'fr' ? 'en' : 'fr']);
        $category = Category::create(['cat_title' => 'Parfum']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Attar', 'product_slug' => 'locale-attar',
            'product_price' => '500000.00', 'stock' => 5, 'is_active' => true,
        ]);
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Atelier', 'recipient_name' => 'Siti',
            'phone' => '08123456789', 'line1' => 'Jl. Atelier 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $payload = ['address_id' => $address->id, 'idempotency_key' => 'checkout-locale'];
        $id = $this->postJson('/checkout?locale='.$locale, $payload)->assertCreated()->json('order.id');
        $this->assertSame($locale, Order::findOrFail($id)->locale);

        $changedLocale = $locale === 'it' ? 'en' : 'it';
        $this->postJson('/locale', ['locale' => $changedLocale])->assertSuccessful();
        $this->assertSame($changedLocale, $user->fresh()->locale);
        $this->postJson('/checkout?locale='.$changedLocale, $payload)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertSame($locale, Order::findOrFail($id)->locale);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->fresh()->stock);

        app()->setLocale($changedLocale);
        $html = app(AdminOrderController::class)->invoice($id)->getContent();
        $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
        $this->assertStringContainsString(e(trans('admin_ui.invoice.date', [], $locale)), $html);
        $this->assertSame($changedLocale, app()->getLocale());
    }

    public function test_historical_null_order_locale_keeps_user_then_indonesian_invoice_fallback(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $order = Order::create([
            'user_id' => $user->id, 'locale' => null, 'status' => 'pending',
            'idempotency_key' => 'historical-locale', 'payload_hash' => str_repeat('a', 64),
        ]);
        app()->setLocale('it');
        $this->assertStringContainsString('<html lang="fr">', app(AdminOrderController::class)->invoice($order->id)->getContent());
        $user->update(['locale' => null]);
        $this->assertStringContainsString('<html lang="id">', app(AdminOrderController::class)->invoice($order->id)->getContent());
        $this->assertNull($order->fresh()->locale);
        $this->assertSame('it', app()->getLocale());
    }
}
