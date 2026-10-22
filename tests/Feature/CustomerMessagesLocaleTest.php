<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerMessagesLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_customer_message_catalogs_have_recursive_parity_and_nonempty_values(): void
    {
        $reference = Arr::dot(require base_path('lang/id/customer_messages.php'));
        $this->assertSame('Pesanan diserahkan ke pengiriman.', $reference['shipment.dispatched']);
        $this->assertSame('Pesanan diterima.', $reference['shipment.delivered']);

        foreach (self::LOCALES as $locale) {
            $values = Arr::dot(require base_path("lang/$locale/customer_messages.php"));
            $this->assertSame(array_keys($reference), array_keys($values), "Mismatch for $locale");
            foreach ($values as $key => $value) {
                $this->assertIsString($value, "$locale.$key must be string");
                $this->assertNotSame('', trim($value), "$locale.$key must not be empty");
                preg_match_all('/(?<!:):([a-z_]+)/i', $reference[$key], $expected);
                preg_match_all('/(?<!:):([a-z_]+)/i', $value, $actual);
                $this->assertSame($expected[1], $actual[1], "$locale.$key placeholders differ");
            }
        }
    }

    public function test_http_json_message_follows_request_locale_and_keeps_indonesian_default(): void
    {
        $this->postJson('/orders/track', ['token' => str_repeat('x', 64)])
            ->assertNotFound()->assertJsonPath('message', 'Pesanan tidak ditemukan.');

        foreach (['en', 'fr', 'it', 'zh'] as $locale) {
            $this->postJson('/orders/track?locale='.$locale, ['token' => str_repeat('x', 64)])
                ->assertNotFound()
                ->assertJsonPath('message', trans('customer_messages.guest.not_found', [], $locale));
        }

        // Cart stock failure reflects requested locale
        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Robe Soie',
            'product_slug' => 'robe-soie-'.uniqid(),
            'product_price' => '1000000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 0, 'is_active' => true]);

        // Requests in this test share the application instance; explicitly select Indonesian.
        $this->postJson('/cart/items?locale=id', ['product_id' => $product->product_id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok produk tidak mencukupi atau produk tidak aktif.');

        $this->postJson('/cart/items?locale=fr', ['product_id' => $product->product_id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', trans('customer_messages.cart.stock', [], 'fr'));

        $this->postJson('/cart/items?locale=zh', ['product_id' => $product->product_id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', trans('customer_messages.cart.stock', [], 'zh'));
    }

    public function test_reachable_customer_backend_has_no_literal_response_messages(): void
    {
        // Routes and bootstrap/app.php are the production entry points; exclude admin and Profile locale logic.
        $controllers = [
            'Address', 'Auth', 'Cart', 'Catalog', 'CustomerTotp', 'EmailVerification',
            'Favorite', 'GuestCheckout', 'Health', 'MidtransWebhook', 'Order', 'OrderReturn',
            'PasswordReset', 'Refund', 'Review', 'Shop', 'SocialAuth', 'Storefront',
        ];
        $files = array_merge(
            array_map(fn ($name) => app_path("Http/Controllers/{$name}Controller.php"), $controllers),
            [app_path('Services/CheckoutService.php'), app_path('Services/RefundService.php'), base_path('routes/web.php')],
        );

        // Non-response prose: the concierge note stored for atelier staff.
        $allowed = [
            '[Pesanan Checkout]\n',
        ];
        $found = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    continue;
                }
                $literal = $token[0] === T_CONSTANT_ENCAPSED_STRING ? substr($token[1], 1, -1) : $token[1];
                if ($this->isSitemapProtocolMarkup($file, $literal)) {
                    continue;
                }
                // Two adjacent words = prose; identifiers, keys, and rules never contain a space between words.
                if (! preg_match('/\p{L}+\s+\p{L}+/u', $literal) || in_array($literal, $allowed, true)) {
                    continue;
                }
                // Logs, internal exceptions, and SQL are diagnostics, not customer copy.
                if (preg_match('/Log::|report\(|new \\\\?(?:LogicException|UnexpectedValueException|RuntimeException)\b|throw_unless|selectRaw/', $lines[$token[2] - 1])) {
                    continue;
                }
                $found[] = basename($file).":$token[2]: $literal";
            }
        }

        $this->assertSame([], $found, 'Move customer-facing prose to lang/*/customer_messages.php');
    }

    private function isSitemapProtocolMarkup(string $file, string $literal): bool
    {
        // Exact protocol tokens only; do not exempt arbitrary XML/HTML or embedded customer copy.
        return $file === app_path('Http/Controllers/StorefrontController.php')
            && in_array($literal, [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            ], true);
    }

    public function test_protocol_markup_exclusion_does_not_hide_customer_prose(): void
    {
        $storefront = app_path('Http/Controllers/StorefrontController.php');
        foreach ([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ] as $markup) {
            $this->assertTrue($this->isSitemapProtocolMarkup($storefront, $markup));
            $this->assertFalse($this->isSitemapProtocolMarkup($storefront, $markup.' Pesanan tidak ditemukan.'));
            $this->assertFalse($this->isSitemapProtocolMarkup(app_path('Http/Controllers/OrderController.php'), $markup));
        }
        foreach (['Pesanan tidak ditemukan.', '<message>Pesanan tidak ditemukan.</message>', '<urlset title="Pesanan tidak ditemukan.">'] as $prose) {
            $this->assertMatchesRegularExpression('/\p{L}+\s+\p{L}+/u', $prose);
            $this->assertFalse($this->isSitemapProtocolMarkup($storefront, $prose));
        }
    }

    public function test_shipment_tracking_descriptions_use_order_owner_locale(): void
    {
        $category = Category::create(['cat_title' => 'Parfum']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Attar', 'product_slug' => 'attar-'.uniqid(), 'product_price' => '300000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create(['locale' => 'fr']);
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Atelier', 'recipient_name' => 'Siti',
            'phone' => '08123456789', 'line1' => 'Jl. Atelier 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'locale-shipment-1'])
            ->assertCreated()->json('order.id');

        // Admin process runs in Indonesian; the stored note must still follow the customer.
        app()->setLocale('id');
        $checkout = app(CheckoutService::class);
        $order = Order::with('items')->findOrFail($id);
        DB::transaction(fn () => $checkout->markPaid($order));
        $checkout->transition($order->fresh(), 'processing');
        $checkout->transition($order->fresh(), 'shipped');
        $checkout->transition($order->fresh(), 'completed');

        $this->assertSame(
            ['Commande remise au transporteur.', 'Commande reçue.'],
            array_column($order->fresh()->shipmentSummary()['events'], 'description'),
        );
    }

    public function test_catalog_messages_translate_with_runtime_placeholders(): void
    {
        $this->assertSame(
            'Kode pelacakan tidak dapat dipulihkan. Hubungi concierge dengan nomor pesanan #42.',
            trans('customer_messages.guest.tracking_unavailable', ['id' => 42], 'id')
        );
        $this->assertNotSame(
            trans('customer_messages.guest.not_found', [], 'id'),
            trans('customer_messages.guest.not_found', [], 'en')
        );
        $this->assertStringContainsString(
            '7',
            trans('customer_messages.customer_totp.rate_limited', ['seconds' => 7], 'fr')
        );
    }

    public function test_password_changed_notification_follows_recipient_locale_and_keeps_indonesian_default(): void
    {
        $indonesian = [
            'subject' => 'Kata Sandi Akun ZAFIRA Telah Diperbarui',
            'lines' => [
                'Kata sandi untuk akun ZAFIRA Anda baru saja diperbarui.',
                'Jika bukan Anda yang melakukan perubahan ini, hubungi Concierge Maison ZAFIRA segera.',
            ],
            'action' => 'Masuk ke Akun',
        ];

        $mail = (new PasswordChanged)->toMail(new User(['locale' => null]));
        $this->assertSame($indonesian['subject'], $mail->subject);
        $this->assertSame($indonesian['lines'], $mail->introLines);
        $this->assertSame($indonesian['action'], $mail->actionText);
        $this->assertSame(route('login'), $mail->actionUrl);

        $unsupported = (new PasswordChanged)->toMail(new User(['locale' => 'xx']));
        $this->assertSame($indonesian['subject'], $unsupported->subject);

        foreach (['en', 'fr', 'zh'] as $locale) {
            $mail = (new PasswordChanged)->toMail(new User(['locale' => $locale]));
            $this->assertSame(trans('mail_ui.password_changed.subject', [], $locale), $mail->subject);
            $this->assertSame([
                trans('mail_ui.password_changed.changed', [], $locale),
                trans('mail_ui.password_changed.warning', [], $locale),
            ], $mail->introLines);
            $this->assertSame(trans('mail_ui.password_changed.action', [], $locale), $mail->actionText);
            $this->assertNotSame($indonesian['subject'], $mail->subject);
            $this->assertNotSame($indonesian['action'], $mail->actionText);
            $html = $mail->render()->toHtml();
            foreach ($mail->introLines as $line) {
                $this->assertStringContainsString(e($line), $html);
            }
            $this->assertStringContainsString(e($mail->actionText), $html);
        }

        $this->assertSame('Your ZAFIRA Account Password Has Been Updated', (new PasswordChanged)->toMail(new User(['locale' => 'en']))->subject);
        $this->assertStringNotContainsString('<', implode(' ', (new PasswordChanged)->toMail(new User(['locale' => 'en']))->introLines));
    }

    public function test_password_changed_catalog_has_parity_in_all_locales(): void
    {
        $reference = Arr::dot(require base_path('lang/id/mail_ui.php'));
        foreach (['subject', 'changed', 'warning', 'action'] as $key) {
            $this->assertArrayHasKey("password_changed.$key", $reference);
        }
        foreach (self::LOCALES as $locale) {
            $values = Arr::dot(require base_path("lang/$locale/mail_ui.php"));
            $this->assertSame(array_keys($reference), array_keys($values), "Mismatch for $locale");
            foreach (['subject', 'changed', 'warning', 'action'] as $key) {
                $this->assertNotSame('', trim($values["password_changed.$key"]), "$locale.$key empty");
            }
        }
    }
}
