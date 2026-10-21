<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthEvent;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class AdminResidualLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_catalogues_have_recursive_key_parity(): void
    {
        $expected = array_keys(Arr::dot(require lang_path('id/admin_ui.php')));
        sort($expected);
        foreach (['en', 'fr', 'it', 'zh'] as $locale) {
            $actual = array_keys(Arr::dot(require lang_path($locale.'/admin_ui.php')));
            sort($actual);
            $this->assertSame($expected, $actual, $locale);
        }
    }

    public function test_admin_catalogues_have_placeholder_parity(): void
    {
        $placeholders = fn (string $locale) => array_map(function ($text) {
            preg_match_all('/:([a-z_]+)/', (string) $text, $m);
            sort($m[1]);

            return $m[1];
        }, Arr::dot(require lang_path($locale.'/admin_ui.php')));

        $expected = $placeholders('id');
        foreach (['en', 'fr', 'it', 'zh'] as $locale) {
            $this->assertSame($expected, array_intersect_key($placeholders($locale), $expected), $locale);
        }
    }

    public function test_report_range_validation_is_localized(): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator', 'email' => 'report-locale@example.test', 'password' => 'Password123!',
        ]), 'admin');

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $this->getJson(route('admin.orders.report', [
                'locale' => $locale, 'period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-01-01',
            ]))->assertUnprocessable()->assertJsonValidationErrors([
                'to' => trans('admin_ui.flash.report_range', [], $locale),
            ]);
        }
    }

    public function test_admin_views_render_localized_residual_copy(): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator', 'email' => 'admin-residual@example.test', 'password' => 'Password123!',
        ]), 'admin');

        foreach (['en', 'fr', 'zh'] as $locale) {
            $dashboard = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk();
            $dashboard->assertSee('placeholder="'.trans('admin_ui.dashboard.user_hint_input', [], $locale).'"', false);
            $dashboard->assertSee('placeholder="'.trans('admin_ui.dashboard.contact_hint_input', [], $locale).'"', false);
            $dashboard->assertSee('placeholder="'.trans('admin_ui.dashboard.email_hint_input', [], $locale).'"', false);
            $dashboard->assertDontSee('cth: nama@email.id…');
            $dashboard->assertDontSee('>Catatan / Pesanan:</strong>', false);
            $dashboard->assertDontSee('Catatan / Pesanan:');

            $events = $this->get(route('admin.auth-events.index', ['locale' => $locale]))->assertOk();
            $events->assertSee(trans('admin_ui.events.success', [], $locale));
            $events->assertSee(trans('admin_ui.events.request_id', [], $locale));
            $header = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk();
            $header->assertSee(trans('admin_ui.common.administration', [], $locale).' · ZAFIRA Maison');
        }
    }

    public function test_auth_events_populated_outcome_cells_are_localized(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'admin-events-locale@example.test', 'password' => 'Password123!',
        ]);
        $this->actingAs($admin, 'admin');

        AuthEvent::create([
            'occurred_at' => now(),
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'event' => 'login.success',
            'outcome' => 'success',
            'ip' => '127.0.0.1',
            'request_id' => 'req-succ-001',
            'context' => [],
        ]);

        AuthEvent::create([
            'occurred_at' => now(),
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'event' => 'login.failure',
            'outcome' => 'failure',
            'ip' => '127.0.0.1',
            'request_id' => 'req-fail-001',
            'context' => [],
        ]);

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $response = $this->get(route('admin.auth-events.index', ['locale' => $locale]))->assertOk();
            $successLabel = trans('admin_ui.events.success', [], $locale);
            $failureLabel = trans('admin_ui.events.failure', [], $locale);

            $this->assertMatchesRegularExpression('/<span class="badge badge-success">\s*'.preg_quote(e($successLabel), '/').'\s*<\/span>/u', $response->getContent());
            $this->assertMatchesRegularExpression('/<span class="badge badge-failure">\s*'.preg_quote(e($failureLabel), '/').'\s*<\/span>/u', $response->getContent());
        }
    }

    public function test_invoice_is_localized_with_html_lang_and_translated_labels(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'admin-invoice-locale@example.test', 'password' => 'Password123!',
        ]);
        $this->actingAs($admin, 'admin');

        $order = Order::create([
            'user_id' => null,
            'customer_name' => 'Madame Dupont',
            'customer_email' => 'dupont@example.test',
            'status' => 'processing',
            'subtotal' => 200000,
            'discount' => 20000,
            'shipping_fee' => 0,
            'total' => 180000,
            'idempotency_key' => 'inv-loc-'.uniqid(),
            'payload_hash' => str_repeat('b', 64),
            'shipping_address' => [
                'line1' => 'Rue de Rivoli',
                'city' => 'Paris',
                'postal_code' => '75001',
            ],
        ]);

        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'Maison',
            'product_title' => 'Gaun', 'product_slug' => 'invoice-gown', 'product_price' => 200000,
        ]);
        $order->items()->create([
            'product_id' => $product->getKey(),
            'product_title' => '<b>Gaun</b> & Sutra',
            'unit_price' => 200000,
            'quantity' => 1,
            'subtotal' => 200000,
        ]);

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $order->update(['locale' => $locale]);
            $response = $this->get(route('admin.orders.invoice', ['id' => $order->id, 'locale' => $locale === 'id' ? 'fr' : 'id']))->assertOk();
            $content = $response->getContent();
            $this->assertStringContainsString(e(trans('admin_ui.invoice.date', [], $locale)).': '.$order->created_at->format('Y-m-d H:i'), $content);

            $this->assertStringContainsString('<html lang="'.$locale.'">', $content);
            foreach (['title', 'heading'] as $key) {
                $this->assertStringContainsString(e(trans('admin_ui.invoice.'.$key, ['id' => $order->id], $locale)), $content);
            }
            foreach (['date', 'client', 'status', 'destination', 'product', 'quantity', 'price', 'subtotal', 'discount', 'total'] as $key) {
                $this->assertStringContainsString(e(trans('admin_ui.invoice.'.$key, [], $locale)), $content);
            }
            $this->assertStringContainsString(e(trans('admin_ui.dashboard.processing', [], $locale)), $content);
            $this->assertStringContainsString('&lt;b&gt;Gaun&lt;/b&gt; &amp; Sutra', $content);
            $this->assertStringNotContainsString('<b>Gaun</b>', $content);
        }
    }

    public function test_report_translated_title_lines_and_status_and_customer_labels(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'admin-report-locale@example.test', 'password' => 'Password123!',
        ]);
        $this->actingAs($admin, 'admin');
        Carbon::setTestNow('2026-06-15 10:00:00');

        $order = Order::create([
            'user_id' => null,
            'customer_name' => 'Claire Delacroix',
            'customer_email' => 'claire@example.test',
            'status' => 'pending',
            'subtotal' => 500000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 500000,
            'idempotency_key' => 'rep-loc-'.uniqid(),
            'payload_hash' => str_repeat('c', 64),
            'shipping_address' => ['line1' => '12 Rue Royale', 'city' => 'Paris', 'postal_code' => '75008'],
        ]);

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $mockPdf = Mockery::mock(PdfDocumentService::class);
            $mockPdf->shouldReceive('render')
                ->once()
                ->withArgs(function (string $title, array $lines, string $pagination) use ($locale, $order) {
                    if ($pagination !== trans('admin_ui.report.pagination', [], $locale)) {
                        return false;
                    }
                    $expectedTitle = trans('admin_ui.report.title', [], $locale);
                    if ($title !== $expectedTitle) {
                        return false;
                    }
                    $status = trans('admin_ui.dashboard.pending', [], $locale);
                    $expected = [
                        trans('admin_ui.report.period', ['from' => '15-06-2026 00:00', 'to' => '15-06-2026 23:59'], $locale),
                        trans('admin_ui.report.orders_created', ['count' => 1, 'cancelled' => 0], $locale),
                        trans('admin_ui.report.non_cancelled_value', ['value' => '500.000'], $locale),
                        trans('admin_ui.report.note', [], $locale),
                        trans('admin_ui.report.status_line', ['status' => $status, 'count' => 1], $locale),
                        trans('admin_ui.report.row', [
                            'id' => $order->id, 'date' => '15-06-2026 10:00', 'status' => $status,
                            'customer' => 'Claire Delacroix', 'total' => '500.000',
                        ], $locale),
                    ];

                    return array_diff($expected, $lines) === [];
                })
                ->andReturn('%PDF-1.4 mock pdf');

            $this->app->instance(PdfDocumentService::class, $mockPdf);

            $response = $this->get(route('admin.orders.report', ['locale' => $locale, 'period' => 'today']))->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
        }
        Carbon::setTestNow();
    }

    public function test_report_exceeded_orders_error_is_localized(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'admin-report-max@example.test', 'password' => 'Password123!',
        ]);
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 501; $i++) {
            Order::create([
                'user_id' => null,
                'customer_name' => 'Customer '.$i,
                'customer_email' => 'cust'.$i.'@example.test',
                'status' => 'pending',
                'subtotal' => 1000,
                'discount' => 0,
                'shipping_fee' => 0,
                'total' => 1000,
                'idempotency_key' => 'rep-max-'.$i.'-'.uniqid(),
                'payload_hash' => str_repeat('d', 64),
                'shipping_address' => ['line1' => 'Street', 'city' => 'City', 'postal_code' => '12345'],
            ]);
        }

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $response = $this->get(route('admin.orders.report', [
                'locale' => $locale,
                'period' => 'custom',
                'from' => now()->subDay()->format('Y-m-d'),
                'to' => now()->addDay()->format('Y-m-d'),
            ]))->assertStatus(422);

            $this->assertSame(
                trans('admin_ui.report.exceeded_limit', ['max' => 500], $locale),
                $response->getContent()
            );
        }
    }

    public function test_order_update_status_flash_uses_localized_status_label(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'admin-status-locale@example.test', 'password' => 'Password123!',
        ]);
        $this->actingAs($admin, 'admin');

        $order = Order::create([
            'user_id' => null,
            'customer_name' => 'Customer',
            'status' => 'pending',
            'subtotal' => 100000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 100000,
            'idempotency_key' => 'stat-loc-'.uniqid(),
            'payload_hash' => str_repeat('e', 64),
        ]);

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            // Cancel order
            $order->update(['status' => 'pending']);
            $response = $this->patch(route('admin.orders.update', ['id' => $order->id, 'locale' => $locale]), [
                'status' => 'cancelled',
            ]);

            $localizedStatus = trans('admin_ui.dashboard.cancelled', [], $locale);
            $expectedFlash = trans('admin_ui.flash.order_status', [
                'id' => $order->id,
                'status' => $localizedStatus,
            ], $locale);

            $response->assertRedirect();
            $response->assertSessionHas('success', $expectedFlash);
        }
    }
}
