<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Services\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminOrderReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Route::has('admin.orders.report'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
    }

    private function order(string $createdAt, int $total, string $status = 'pending', ?User $user = null): Order
    {
        $user ??= User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'status' => $status, 'subtotal' => $total, 'discount' => 0,
            'shipping_fee' => 0, 'total' => $total,
            'idempotency_key' => 'report-'.uniqid(), 'payload_hash' => str_repeat('a', 64),
            'shipping_address' => ['line1' => 'Jalan Sutra 1', 'city' => 'Jakarta', 'postal_code' => '12345'],
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order->fresh();
    }

    /**
     * Genuine text extraction through macOS PDFKit (tests/Support/pdf-text.swift); pages split by form feed.
     * shortcut: macOS-only (swiftc + PDFKit); skips elsewhere, swap for poppler pdftotext on Linux CI.
     *
     * @return list<string>
     */
    private function pdfText(string $pdf): array
    {
        static $binary = null;
        if (PHP_OS_FAMILY !== 'Darwin' || ! is_executable('/usr/bin/swiftc')) {
            $this->markTestSkipped('PDF text extraction needs macOS PDFKit (swiftc).');
        }
        $dir = sys_get_temp_dir();
        if ($binary === null) {
            $binary = $dir.'/zafira-pdf-text-'.sha1_file(base_path('tests/Support/pdf-text.swift'));
            if (! is_file($binary)) {
                exec('/usr/bin/swiftc -O -module-cache-path '.escapeshellarg($dir.'/swift-module-cache').' '
                    .escapeshellarg(base_path('tests/Support/pdf-text.swift')).' -o '.escapeshellarg($binary).' 2>&1', $out, $rc);
                $this->assertSame(0, $rc, implode("\n", $out));
            }
        }
        $file = tempnam($dir, 'pdf');
        file_put_contents($file, $pdf);
        exec(escapeshellarg($binary).' '.escapeshellarg($file), $lines, $rc);
        unlink($file);
        $this->assertSame(0, $rc, 'PDFKit could not open the PDF.');

        return $lines;
    }

    public function test_pdf_service_embeds_unicode_font_and_sanitizes_html_and_unicode(): void
    {
        $pdf = app(PdfDocumentService::class)->render('日本語 <script>alert(1)</script>', [
            '注文 café (安全) \\', "NFD: Lumie\u{0300}re", "control x\xFFy\x01z",
        ]);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/ToUnicode', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
        $this->assertStringNotContainsString('<script>', $pdf);
        $this->assertStringNotContainsString('alert(1)', $pdf);
        $text = implode("\n", $this->pdfText($pdf));
        $this->assertStringContainsString('日本語 <script>alert(1)</script>', $text);
        $this->assertStringContainsString('注文 café (安全)', $text);
        $this->assertStringContainsString('Lumière', $text);
        $this->assertStringContainsString('control x?y?z', $text);
        $this->assertStringContainsString('NotoSansSC-Regular.ttf', resource_path('fonts/NotoSansSC-Regular.ttf'));
    }

    public function test_report_pdf_pagination_and_title_are_localized_in_real_pdf(): void
    {
        $this->order(now()->toDateTimeString(), 125000);
        $this->actingAs($this->admin(), 'admin');

        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $response = $this
                ->get('/admin/reports/orders.pdf?period=today&locale='.$locale)->assertOk();
            $text = implode("\n", $this->pdfText($response->getContent()));

            $this->assertStringContainsString(trans('admin_ui.report.title', [], $locale), $text);
            $expectedPage = str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], '1', trans('admin_ui.report.pagination', [], $locale));
            $this->assertStringContainsString($expectedPage, $text, $locale);
            if ($locale !== 'id') {
                $this->assertStringNotContainsString('Halaman 1 dari 1', $text, $locale);
            }
        }
    }

    public function test_pdf_service_expands_custom_pagination_across_pages(): void
    {
        $lines = array_map(fn ($i) => 'Row '.$i, range(1, 120));
        $text = implode("\n", $this->pdfText(app(PdfDocumentService::class)->render('Report', $lines, 'Page {PAGE_NUM} of {PAGE_COUNT}')));

        $this->assertMatchesRegularExpression('/Page 2 of ([2-9]|\d{2,})/', $text);
        $this->assertStringNotContainsString('Halaman', $text);
    }

    public function test_pdf_service_embeds_latin_fallback_alongside_cjk(): void
    {
        $pdf = app(PdfDocumentService::class)->render('Ĳ Ł 王小明', ['ĳ ł 日本語']);

        // Extraction alone can pass even when the rendered glyph is missing.
        $this->assertStringContainsString('DejaVuSans', $pdf);
        $this->assertStringContainsString('NotoSansSC', $pdf);
        $this->assertGreaterThanOrEqual(2, substr_count($pdf, '/FontFile2'));

        $text = implode("\n", $this->pdfText($pdf));
        $this->assertStringContainsString('Ĳ Ł 王小明', $text);
        $this->assertStringContainsString('ĳ ł 日本語', $text);
    }

    public function test_guest_invoice_escapes_snapshot_and_report_includes_guest_name(): void
    {
        $order = $this->order(now()->toDateTimeString(), 125000);
        $order->update(['user_id' => null, 'customer_name' => '<script>alert(1)</script>', 'customer_email' => 'guest@example.test']);
        $this->actingAs($this->admin(), 'admin')->get('/admin/orders/'.$order->id.'/invoice')
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false)->assertSee('guest@example.test');
        $order->update(['customer_name' => 'Guest Snapshot']);
        $response = $this->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $this->assertStringContainsString('Guest Snapshot', implode("\n", $this->pdfText($response->getContent())));
    }

    public function test_report_requires_admin_authentication(): void
    {
        $this->get('/admin/reports/orders.pdf?period=today')->assertRedirect(route('admin.login'));
    }

    public function test_report_is_a_parseable_pdf_with_period_totals(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->order('2026-06-15 02:00:00', 300000);
        $this->order('2026-06-14 23:59:59', 999000);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="zafira-laporan-20260615-20260615.pdf"', $response->headers->get('content-disposition'));

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('/Type /Catalog', $pdf);
        $this->assertMatchesRegularExpression('/startxref\n\d+\n%%EOF/', $pdf);

        $text = implode("\n", $this->pdfText($pdf));
        $this->assertStringContainsString('Pesanan dibuat: 1', $text);
        $this->assertStringContainsString('Rp 300.000', $text);
        $this->assertStringNotContainsString('999.000', $text);
        $this->assertStringNotContainsString('pendapatan terbayar: ', $text);
        $this->assertStringContainsString('bukan pendapatan terbayar', $text);
        Carbon::setTestNow();
    }

    public function test_report_boundary_rows_and_cancelled_orders_excluded_from_value(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->order('2026-06-09 00:00:00', 100000);
        $this->order('2026-06-08 23:59:59', 500000);
        $this->order('2026-06-15 09:00:00', 70000, 'cancelled');

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=7d')->assertOk();
        $text = implode("\n", $this->pdfText($response->getContent()));

        $this->assertStringContainsString('Pesanan dibuat: 2 (dibatalkan: 1)', $text);
        $this->assertStringContainsString('Nilai pesanan non-batal: Rp 100.000', $text);
        $this->assertStringContainsString('Status Dibatalkan: 1', $text);
        Carbon::setTestNow();
    }

    public function test_custom_period_validates_dates_and_range(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom')
            ->assertSessionHasErrors(['from', 'to']);

        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom&from=2026-06-10&to=2026-06-01')
            ->assertSessionHasErrors('to');

        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom&from=2024-01-01&to=2026-01-01')
            ->assertSessionHasErrors('to');

        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=sepanjang-masa')
            ->assertSessionHasErrors('period');

        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom&from=2026-06-01&to=2026-06-10')
            ->assertOk();
    }

    public function test_empty_period_still_renders_valid_pdf_without_revenue_claim(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=30d')->assertOk();
        $pdf = $response->getContent();
        $text = implode("\n", $this->pdfText($pdf));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Pesanan dibuat: 0', $text);
        $this->assertStringContainsString('Nilai pesanan non-batal: Rp 0', $text);
    }

    public function test_pdf_service_paginates_and_escapes_special_characters(): void
    {
        $pdf = app(PdfDocumentService::class)->render('Judul (uji) \\ tes', array_map(
            static fn (int $i) => "Baris {$i} (aman)",
            range(1, 100)
        ));

        $this->assertStringContainsString('/Count 3', $pdf);
        $this->assertStringContainsString('Halaman 3 dari 3', implode("\n", $this->pdfText($pdf)));
        $this->assertStringContainsString('Judul (uji) \\ tes', implode("\n", $this->pdfText($pdf)));
        $this->assertSame(3, preg_match_all('/\/Type \/Page[^s]/', $pdf));
    }

    public function test_today_bounds_follow_utc_even_when_now_is_expressed_in_another_zone(): void
    {
        // 23:30 New York (UTC-4) = 03:30 UTC hari berikutnya.
        Carbon::setTestNow(Carbon::parse('2026-06-15 23:30:00', 'America/New_York'));
        $this->order('2026-06-16 00:00:00', 111000);
        $this->order('2026-06-16 23:59:59', 222000);
        $this->order('2026-06-15 23:59:59', 999000);
        $this->order('2026-06-17 00:00:00', 888000);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $text = implode("\n", $this->pdfText($response->getContent()));

        $this->assertStringContainsString('zafira-laporan-20260616-20260616.pdf', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Periode (UTC): 16-06-2026 00:00 hingga 16-06-2026 23:59 UTC', $text);
        $this->assertStringContainsString('Pesanan dibuat: 2', $text);
        $this->assertStringContainsString('Rp 333.000', $text);
    }

    public function test_report_rejects_more_than_500_orders_with_clear_422(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 501; $i++) {
            $this->order('2026-06-10 08:00:00', 1000, 'pending', $user);
        }
        $admin = $this->admin();

        $too = $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom&from=2026-06-01&to=2026-06-30');
        $too->assertStatus(422);
        $too->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('melebihi 500 pesanan', $too->getContent());
        $this->assertStringNotContainsString('%PDF', $too->getContent());

        // Tepat 500 masih lolos.
        Order::where('user_id', $user->id)->orderByDesc('id')->limit(1)->delete();
        $this->actingAs($admin, 'admin')->get('/admin/reports/orders.pdf?period=custom&from=2026-06-01&to=2026-06-30')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_service_wraps_long_lines_before_pagination_and_marks_non_ascii(): void
    {
        $long = str_repeat('x', 200);
        $pdf = app(PdfDocumentService::class)->render('Judul', array_fill(0, 44, $long));

        // 44 baris x 3 potongan (90+90+20) = 132 baris = 3 halaman, bukan 1.
        $this->assertStringContainsString('/Count 3', $pdf);
        $pages = explode("\x0C", implode("\n", $this->pdfText($pdf)));
        $this->assertCount(3, $pages);
        foreach (explode("\n", implode("\n", $pages)) as $text) {
            $this->assertLessThanOrEqual(120, mb_strlen($text));
        }

        $spaced = app(PdfDocumentService::class)->render('Judul', [str_repeat('kata ', 40)]);
        $rows = array_values(array_filter(
            explode("\n", implode("\n", $this->pdfText($spaced))),
            static fn (string $t) => str_starts_with(trim($t), 'kata')
        ));
        $this->assertGreaterThan(1, count($rows));
        $this->assertSame(trim(str_repeat('kata ', 40)), implode(' ', array_map('trim', $rows)));

        $unicode = implode("\n", $this->pdfText(app(PdfDocumentService::class)->render('Lumière — €', [
            'Sutra café naïve × ™ (uji) \\', '日本語 注文 😀',
        ])));
        $this->assertStringContainsString('Lumière — €', $unicode);
        $this->assertStringContainsString('Sutra café naïve × ™ (uji) \\', $unicode);
        $this->assertStringContainsString('日本語 注文', $unicode);

        // NFD input is composed; invalid UTF-8 and control bytes cannot leak into the document.
        $edge = implode("\n", $this->pdfText(app(PdfDocumentService::class)->render('Judul', ["Lumie\u{0300}re", "x\xFFy\x01z"])));
        $this->assertStringContainsString('Lumière', $edge);
        $this->assertStringContainsString('x?y?z', $edge);
    }

    public function test_report_uses_each_orders_related_customer_name_and_preserves_unicode(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $a = $this->order('2026-06-15 08:00:00', 125000, 'pending', User::factory()->create(['name' => '王小明']));
        $b = $this->order('2026-06-15 09:00:00', 225000, 'paid', User::factory()->create(['name' => 'Madame Élodie de Longueville']));

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $text = implode("\n", $this->pdfText($response->getContent()));
        // Nama terikat ke baris pesanannya sendiri, bukan sekadar muncul di dokumen.
        $this->assertMatchesRegularExpression('/#'.$a->id.' \| [^\n]*Pelanggan: 王小明 \| Rp 125\.000/u', $text);
        $this->assertMatchesRegularExpression('/#'.$b->id.' \| [^\n]*Pelanggan: Madame Élodie de Longueville \| Rp 225\.000/u', $text);

        $this->assertStringContainsString('王小明', $text);
        $this->assertStringContainsString('Madame Élodie de Longueville', $text);
        $this->assertStringContainsString('Rp 125.000', $text);
        $this->assertStringContainsString('Rp 225.000', $text);
    }

    public function test_report_falls_back_when_customer_name_is_blank_or_missing(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $blank = $this->order('2026-06-15 08:00:00', 125000, 'pending', User::factory()->create(['name' => '   ']));
        $named = $this->order('2026-06-15 09:00:00', 225000, 'paid', User::factory()->create(['name' => 'Klien Terdaftar']));

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $text = implode("\n", $this->pdfText($response->getContent()));

        $this->assertMatchesRegularExpression('/#'.$blank->id.' \| [^\n]*Pelanggan: - \| Rp 125\.000/u', $text);
        $this->assertMatchesRegularExpression('/#'.$named->id.' \| [^\n]*Pelanggan: Klien Terdaftar \| Rp 225\.000/u', $text);
        $this->assertSame(1, substr_count($text, 'Pelanggan: -'));
        // shortcut: relasi yatim tak bisa dibuat (user_id NOT NULL + FK cascade); null-safe `?->` cukup, tidak diuji terpisah.
    }

    public function test_report_wraps_long_customer_names_without_losing_amounts(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $name = 'Yang Mulia Putri Cantika Maharani Sasikirana Kusumaningrat dari Keraton Surakarta';
        $order = $this->order('2026-06-15 08:00:00', 999000, 'pending', User::factory()->create(['name' => $name]));

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $lines = $this->pdfText($response->getContent());
        $text = implode("\n", $lines);

        $this->assertStringContainsString('Pelanggan:', $text);
        $this->assertStringContainsString('Rp 999.000', $text);
        $this->assertStringContainsString($name, preg_replace('/\s+/u', ' ', $text));
        $offset = strpos($text, '#'.$order->id.' |');
        $this->assertNotFalse($offset);
        $detail = substr($text, $offset);
        $this->assertGreaterThan(1, count(explode("\n", trim(substr($detail, 0, strpos($detail, 'Rp 999.000'))))));
    }

    public function test_report_does_not_query_customer_relation_per_order(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->order('2026-06-15 08:00:00', 125000);
        $this->order('2026-06-15 09:00:00', 225000);
        DB::enableQueryLog();

        $this->actingAs($this->admin(), 'admin')->get('/admin/reports/orders.pdf?period=today')->assertOk();
        $userQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains(strtolower($q['query']), 'from '.strtolower(DB::connection()->getQueryGrammar()->wrapTable('users'))));
        $this->assertCount(1, $userQueries);
    }

    public function test_admin_cannot_mark_pending_order_paid_without_payment_proof(): void
    {
        $order = $this->order('2026-06-15 09:00:00', 250000);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson(route('admin.orders.update', $order->id), ['status' => 'paid'])
            ->assertUnprocessable();

        $this->assertSame('pending', $order->fresh()->status);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('<option value="paid">', false)
            ->assertSee('<option value="cancelled">', false);
    }

    public function test_dashboard_lists_orders_and_report_controls(): void
    {
        $this->order('2026-06-15 09:00:00', 250000);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pesanan &amp; Laporan', false)
            ->assertSee('Status administratif')
            ->assertSee('/admin/reports/orders.pdf', false)
            ->assertSee('name="stock"', false)
            ->assertSee('name="is_active"', false);
    }
}
