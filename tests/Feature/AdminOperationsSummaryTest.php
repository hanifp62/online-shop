<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateGuardSession;
use App\Http\Middleware\EnsureAdminTotp;
use App\Http\Middleware\NoStore;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OperationalMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * REM-20261021 / FR-REM-007 / TST-09: kontrak RED, bukan bukti runtime lulus.
 * Skill: laravel-tdd, api-and-interface-design, laravel-patterns.
 * Detail agregat usulan: by_status, by_outcome, held_count, exhausted_count,
 * leased_count, ready_count, delayed_count, reserved_count pada metrik terkait.
 */
class AdminOperationsSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/reports/operations-summary';

    private CarbonImmutable $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asOf = CarbonImmutable::parse('2026-10-21 12:00:00', 'UTC');
        $this->travelTo($this->asOf);
        config([
            'features.admin_ops_summary' => ['enabled' => true, 'rollout' => 100],
            'queue.default' => 'database',
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.table' => 'jobs',
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => config('database.default'),
            'queue.failed.table' => 'failed_jobs',
        ]);
    }

    private function admin(string $role = Admin::ROLE_ADMIN, bool $totp = false): Admin
    {
        return Admin::create([
            'name' => 'Operations', 'email' => Str::uuid().'@example.test',
            'password' => 'Password123!', 'role' => $role,
            'totp_secret' => $totp ? 'JBSWY3DPEHPK3PXP' : null,
            'totp_required_at' => $totp ? $this->asOf : null,
            'totp_last_used_at' => $totp ? 12345 : null,
        ]);
    }

    private function verifiedAdmin(): Admin
    {
        $admin = $this->admin(totp: true);
        // actingAs sets an in-memory guard; do not fabricate a persisted password-login key.
        $this->actingAs($admin, 'admin');
        $this->withSession([]);
        $this->withSession([
            'auth_fingerprint_admin' => ['id' => $admin->id, 'password' => AuthenticateGuardSession::fingerprint($admin)],
            EnsureAdminTotp::SESSION_KEY => [
                'admin_id' => $admin->id,
                'session_id' => session()->getId(),
                'last_used_at' => 12345,
            ],
        ]);
        // StartSession resets the ID from the request cookie; preserve the ID bound to TOTP.
        // Laravel JSON requests omit cookies unless withCredentials is explicitly enabled.
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $admin;
    }

    private function order(string $createdAt = '2026-10-21 00:00:00', string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'idempotency_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', (string) Str::uuid()),
            'status' => $status, 'subtotal' => 100000, 'discount' => 0, 'total' => 100000,
            'customer_email' => 'private-ops@example.test', 'customer_phone' => '081234009988',
            'shipping_address' => ['line1' => 'PRIVATE_ADDRESS_SENTINEL'],
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $order;
    }

    private function summary(): array
    {
        return app(OperationalMetrics::class)->summary($this->asOf->startOfDay(), $this->asOf->addDay()->startOfDay(), $this->asOf);
    }

    private function metric(array $body, string $id): array
    {
        $matches = array_values(array_filter($body['metrics'], fn (array $metric) => $metric['id'] === $id));
        $this->assertCount(1, $matches, 'Metrik harus unik: '.$id);

        return $matches[0];
    }

    public function test_route_has_every_existing_admin_security_boundary(): void
    {
        $route = Route::getRoutes()->getByName('admin.reports.operations-summary');
        $this->assertNotNull($route);
        $this->assertSame('admin/reports/operations-summary', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach ([NoStore::class, 'auth:admin', EnsureAdminTotp::class, 'feature:admin_ops_summary', 'admin.can:view-reports'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_guest_and_customer_cannot_read_admin_metrics(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(self::URL)->assertUnauthorized();
    }

    public function test_staff_and_unknown_role_are_denied(): void
    {
        foreach ([Admin::ROLE_STAFF, 'unknown'] as $role) {
            $this->actingAs($this->admin($role), 'admin')->getJson(self::URL)->assertForbidden();
        }
    }

    public function test_totp_required_admin_without_valid_session_marker_is_challenged(): void
    {
        $this->actingAs($this->admin(totp: true), 'admin')->get(self::URL)
            ->assertRedirect(route('admin.totp.challenge'));
    }

    public function test_valid_totp_session_can_read_json_without_cache(): void
    {
        $this->verifiedAdmin();
        $response = $this->getJson(self::URL.'?period=today')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertJsonPath('contract_version', 'ops-metrics.v1')
            ->assertJsonPath('window.tz', 'UTC')->assertJsonPath('window.period', 'today');
    }

    public function test_feature_configuration_has_explicit_disabled_default(): void
    {
        // Periksa default deklaratif tanpa memuat .env atau mengubah environment proses.
        $original = config('features.admin_ops_summary');
        $this->assertIsArray($original);
        $source = file_get_contents(config_path('features.php'));
        $this->assertMatchesRegularExpression("/'admin_ops_summary'\\s*=>\\s*\\[\\s*'enabled'\\s*=>\\s*env\\('ZAFIRA_FEATURE_ADMIN_OPS_SUMMARY_ENABLED',\\s*false\\)/s", $source);
    }

    public function test_disabled_and_zero_rollout_feature_return_404(): void
    {
        $this->verifiedAdmin();
        foreach ([[false, 100], [true, 0]] as [$enabled, $rollout]) {
            config(['features.admin_ops_summary' => compact('enabled', 'rollout')]);
            $this->getJson(self::URL)->assertNotFound();
        }
    }

    public function test_invalid_windows_fail_validation_instead_of_becoming_default_window(): void
    {
        $this->verifiedAdmin();
        foreach ([
            ['period' => 'invalid'], ['period' => ['today']],
            ['period' => 'custom'],
            ['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01'],
            ['period' => 'custom', 'from' => ['2026-10-21'], 'to' => '2026-10-21'],
            ['period' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-21'],
            ['period' => 'custom', 'from' => '2025-10-20', 'to' => '2026-10-21'],
            ['period' => 'custom', 'from' => '2026-10-21T00:00:00+07:00', 'to' => '2026-10-21'],
        ] as $query) {
            $this->getJson(self::URL.'?'.http_build_query($query))->assertUnprocessable()->assertJsonStructure(['errors']);
        }
        $this->getJson(self::URL.'?period=custom&from=2025-10-21&to=2026-10-21')->assertOk();
    }

    public function test_presets_and_custom_input_produce_utc_half_open_windows(): void
    {
        $this->verifiedAdmin();
        foreach (['today' => '2026-10-21', '7d' => '2026-10-15', '30d' => '2026-09-22'] as $period => $from) {
            $body = $this->getJson(self::URL.'?period='.$period)->assertOk()->json();
            $this->assertTrue(CarbonImmutable::parse($body['window']['from'])->equalTo(CarbonImmutable::parse($from, 'UTC')));
            $this->assertTrue(CarbonImmutable::parse($body['window']['to_exclusive'])->equalTo($this->asOf->addDay()->startOfDay()));
        }
        $body = $this->getJson(self::URL.'?period=custom&from=2026-10-21&to=2026-10-21')->assertOk()->json();
        $this->assertSame(86400, CarbonImmutable::parse($body['window']['to_exclusive'])->timestamp - CarbonImmutable::parse($body['window']['from'])->timestamp);
    }

    public function test_timestamps_have_stable_utc_second_precision_and_reasons_are_allowlisted(): void
    {
        $body = $this->summary();
        $this->assertSame('2026-10-21T12:00:00Z', $body['as_of']);
        $this->assertSame('2026-10-21T00:00:00Z', $body['window']['from']);
        $this->assertSame('2026-10-22T00:00:00Z', $body['window']['to_exclusive']);
        foreach ($body['metrics'] as $metric) {
            $this->assertSame($body['as_of'], $metric['observed_at']);
            $this->assertContains($metric['reason'], [null, 'source_unavailable', 'unsupported_driver', 'not_in_minimal_slice']);
        }
    }

    public function test_order_events_are_half_open_but_status_counts_are_whole_current_snapshot(): void
    {
        $this->order('2026-10-20 23:59:59', 'completed');
        $this->order('2026-10-21 00:00:00');
        $this->order('2026-10-21 23:59:59');
        $this->order('2026-10-22 00:00:00', 'cancelled');
        $body = $this->summary();
        $this->assertSame(2, $this->metric($body, 'MET-01')['value']);
        $snapshot = $this->metric($body, 'MET-02');
        $this->assertSame(4, $snapshot['value']);
        $this->assertSame(2, $snapshot['by_status']['pending']);
        $this->assertSame(1, $snapshot['by_status']['completed']);
        $this->assertSame('snapshot', $snapshot['time_basis']);
    }

    public function test_payment_anomalies_count_events_not_distinct_orders(): void
    {
        $order = $this->order();
        foreach (['unknown_order', 'amount_mismatch', 'order_not_payable', 'paid', 'amount_mismatch', 'amount_mismatch'] as $i => $outcome) {
            DB::table('payments')->insert([
                'order_id' => $order->id, 'provider' => 'midtrans', 'provider_transaction_id' => 'PRIVATE_TX_'.$i,
                'provider_order_id' => 'PRIVATE_PROVIDER_ORDER', 'event_status' => 'settlement', 'amount' => 100000,
                'outcome' => $outcome, 'payload' => '{"email":"private-ops@example.test"}',
                'created_at' => $i === 4 ? '2026-10-20 23:59:59' : ($i === 5 ? '2026-10-22 00:00:00' : '2026-10-21 00:00:00'),
                'updated_at' => $this->asOf,
            ]);
        }
        $metric = $this->metric($this->summary(), 'MET-04');
        $this->assertSame(3, $metric['value']);
        foreach (['unknown_order', 'amount_mismatch', 'order_not_payable'] as $outcome) {
            $this->assertSame(1, $metric['by_outcome'][$outcome]);
        }
        $this->assertSame('attention', $metric['status']);
    }

    private function backlogFixtures(): void
    {
        $category = Category::create(['cat_title' => 'Tas']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Ops', 'product_slug' => 'ops-fixture', 'product_price' => '100000.00']);
        $free = $this->order('2026-09-01 00:00:00');
        $held = $this->order('2026-09-01 00:00:00');
        foreach ([[$free, 'reserved', '2026-10-21 11:00:00'], [$free, 'reserved', '2026-10-21 11:30:00'], [$held, 'reserved', '2026-10-21 10:00:00'], [$free, 'reserved', '2026-10-21 12:00:00'], [$free, 'released', '2026-10-21 09:00:00']] as [$order, $status, $expiry]) {
            DB::table('inventory_reservations')->insert(['order_id' => $order->id, 'product_id' => $product->product_id, 'quantity' => 3, 'status' => $status, 'expires_at' => $expiry, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => $this->asOf]);
        }
        foreach ([null, null, $this->asOf] as $resolved) {
            DB::table('payment_dead_letters')->insert(['order_id' => $held->id, 'provider' => 'midtrans', 'reason' => 'retry', 'attempts' => 10, 'raw_body' => 'PRIVATE_RAW_BODY', 'transaction_id' => 'PRIVATE_DEAD_TX', 'resolved_at' => $resolved, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => $this->asOf]);
        }
        foreach ([['pending', 'mail', null, null], ['paid', 'mail', null, '2026-10-21 12:05:00'], ['shipped', 'mail', $this->asOf, null], ['completed', 'sms', null, null]] as [$status, $channel, $dispatched, $lease]) {
            DB::table('order_notifications')->insert(['order_id' => $free->id, 'status' => $status, 'channel' => $channel, 'dispatched_at' => $dispatched, 'lease_token' => $lease ? 'PRIVATE_LEASE_TOKEN' : null, 'lease_expires_at' => $lease, 'created_at' => '2026-09-01 00:00:00']);
        }
    }

    public function test_old_backlog_remains_visible_and_held_reservations_are_not_double_counted(): void
    {
        $this->backlogFixtures();
        $body = $this->summary();
        $expired = $this->metric($body, 'MET-05');
        $this->assertSame(2, $expired['value']);
        $this->assertSame(1, $expired['held_count']);
        $this->assertSame(3600, $expired['oldest_age_seconds']);
        $dead = $this->metric($body, 'MET-06');
        $this->assertSame(2, $dead['value']);
        $this->assertSame(2, $dead['exhausted_count']);
        $this->assertSame('critical', $dead['status']);
        $outbox = $this->metric($body, 'MET-07');
        $this->assertSame(2, $outbox['value']);
        $this->assertSame(1, $outbox['leased_count']);
        foreach ([$dead, $outbox] as $metric) {
            $this->assertSame((int) CarbonImmutable::parse('2026-09-01', 'UTC')->diffInSeconds($this->asOf), $metric['oldest_age_seconds']);
        }
    }

    public function test_database_queue_separates_ready_delayed_reserved_and_uses_available_age(): void
    {
        foreach ([[null, -120], [null, 60], [$this->asOf->timestamp - 10, -300]] as [$reserved, $offset]) {
            DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'PRIVATE_JOB_PAYLOAD', 'attempts' => 0, 'reserved_at' => $reserved, 'available_at' => $this->asOf->timestamp + $offset, 'created_at' => $this->asOf->subDays(40)->timestamp]);
        }
        $metric = $this->metric($this->summary(), 'MET-08');
        $this->assertSame('ok', $metric['status']);
        $this->assertTrue($metric['exact']);
        $this->assertNull($metric['reason']);
        $this->assertSame(3, $metric['value']);
        foreach (['ready_count', 'delayed_count', 'reserved_count'] as $key) {
            $this->assertSame(1, $metric[$key]);
        }
        $this->assertSame(120, $metric['oldest_age_seconds']);
    }

    public function test_failed_jobs_use_half_open_event_window(): void
    {
        foreach (['2026-10-20 23:59:59', '2026-10-21 00:00:00', '2026-10-21 23:59:59', '2026-10-22 00:00:00'] as $date) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => 'PRIVATE_JOB_PAYLOAD', 'exception' => 'PRIVATE_EXCEPTION /srv/app/secret.php', 'failed_at' => $date]);
        }
        $this->assertSame(2, $this->metric($this->summary(), 'MET-09')['value']);
    }

    public function test_configured_tables_and_connections_are_not_silently_replaced_by_defaults(): void
    {
        config(['queue.connections.database.connection' => 'ops_missing_connection', 'queue.failed.database' => 'ops_missing_failed_connection']);
        $body = $this->summary();
        foreach (['MET-08', 'MET-09'] as $id) {
            $metric = $this->metric($body, $id);
            $this->assertNull($metric['value']);
            $this->assertSame('source_unavailable', $metric['reason']);
        }
        config(['queue.connections.database.connection' => config('database.default'), 'queue.failed.database' => config('database.default'), 'queue.connections.database.table' => 'ops_missing_jobs', 'queue.failed.table' => 'ops_missing_failed_jobs']);
        $body = $this->summary();
        foreach (['MET-08', 'MET-09'] as $id) {
            $this->assertSame('source_unavailable', $this->metric($body, $id)['reason']);
        }
    }

    public function test_queue_and_failed_job_counts_come_from_configured_connection_and_tables(): void
    {
        // Database SQLite terpisah hanya dibuat saat integrator menjalankan suite terisolasi.
        config(['database.connections.ops_metrics_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        $connection = DB::connection('ops_metrics_test');
        try {
            $connection->getSchemaBuilder()->create('ops_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue');
                $table->text('payload');
                $table->unsignedInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
            $connection->getSchemaBuilder()->create('ops_failed', function (Blueprint $table): void {
                $table->id();
                $table->timestamp('failed_at');
            });
            $connection->table('ops_jobs')->insert([
                'queue' => 'default', 'payload' => 'PRIVATE_JOB_PAYLOAD', 'attempts' => 0,
                'reserved_at' => null, 'available_at' => $this->asOf->timestamp - 60,
                'created_at' => $this->asOf->subDays(40)->timestamp,
            ]);
            $connection->table('ops_failed')->insert(['failed_at' => '2026-10-21 00:00:00']);
            config([
                'queue.connections.database.connection' => 'ops_metrics_test',
                'queue.connections.database.table' => 'ops_jobs',
                'queue.failed.database' => 'ops_metrics_test', 'queue.failed.table' => 'ops_failed',
            ]);
            $body = $this->summary();
            $this->assertSame(1, $this->metric($body, 'MET-08')['value']);
            $this->assertSame(60, $this->metric($body, 'MET-08')['oldest_age_seconds']);
            $this->assertSame(1, $this->metric($body, 'MET-09')['value']);
        } finally {
            DB::purge('ops_metrics_test');
        }
    }

    public function test_measured_zero_is_distinct_from_unsupported_or_disabled_sources(): void
    {
        $body = $this->summary();
        foreach (['MET-01', 'MET-02', 'MET-04', 'MET-05', 'MET-06', 'MET-07', 'MET-08', 'MET-09'] as $id) {
            $metric = $this->metric($body, $id);
            $this->assertSame(0, $metric['value']);
            $this->assertTrue($metric['exact']);
            $this->assertNull($metric['oldest_age_seconds']);
        }
        config(['queue.default' => 'redis', 'queue.failed.driver' => 'null']);
        $body = $this->summary();
        foreach (['MET-08', 'MET-09'] as $id) {
            $metric = $this->metric($body, $id);
            $this->assertNull($metric['value']);
            $this->assertFalse($metric['exact']);
            $this->assertSame('unknown', $metric['status']);
            $this->assertSame('unsupported_driver', $metric['reason']);
            $this->assertContains($id, $body['summary']['unknown_ids']);
        }
        $this->assertSame('partial', $body['summary']['coverage']);
        foreach (['MET-03', 'MET-10', 'MET-11', 'MET-12', 'MET-13', 'MET-14', 'MET-15', 'MET-16'] as $id) {
            $this->assertSame('not_in_minimal_slice', $this->metric($body, $id)['reason']);
        }
    }

    public function test_service_is_read_only_bounded_and_pii_free_without_http_middleware(): void
    {
        $this->backlogFixtures();
        $tables = ['orders', 'products', 'inventory_reservations', 'payment_dead_letters', 'order_notifications', 'payments', 'jobs', 'failed_jobs'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'products' ? 'product_id' : 'id')->get()->toJson()])->all();
        $before = $snapshot();
        $service = app(OperationalMetrics::class);
        $queries = [];
        $collecting = true;
        DB::listen(function (QueryExecuted $query) use (&$queries, &$collecting): void {
            if ($collecting) {
                $queries[] = $query->sql;
            }
        });
        try {
            $body = $service->summary($this->asOf->startOfDay(), $this->asOf->addDay()->startOfDay(), $this->asOf);
        } finally {
            $collecting = false;
        }
        $this->assertNotEmpty($queries);
        $this->assertLessThanOrEqual(20, count($queries));
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma|show|describe)\b/i', $sql);
            $this->assertDoesNotMatchRegularExpression('/\b(for update|lock in share mode)\b/i', $sql);
        }
        $this->assertSame($before, $snapshot());
        $this->assertSame('best_effort', $body['consistency']);
        foreach ($body['metrics'] as $metric) {
            foreach (['id', 'value', 'unit', 'time_basis', 'status', 'exact', 'basis', 'observed_at', 'reason', 'oldest_age_seconds'] as $field) {
                $this->assertArrayHasKey($field, $metric);
            }
        }
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        foreach (['private-ops@example.test', '081234009988', 'PRIVATE_', 'raw_body', 'lease_token', 'customer_email', 'shipping_address', 'exception', 'tracking_token', 'recovery_code'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    public function test_partial_http_response_does_not_leak_source_errors(): void
    {
        $this->verifiedAdmin();
        config(['queue.connections.database.connection' => 'PRIVATE_CONNECTION_SENTINEL']);
        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('summary.coverage', 'partial');
        foreach (['PRIVATE_CONNECTION_SENTINEL', 'SQLSTATE', '/srv/', 'stack trace', 'select *'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_main_source_unavailable_returns_503_without_false_zero_or_raw_error(): void
    {
        $this->verifiedAdmin();
        DB::partialMock()->shouldReceive('table')->andThrow(new \RuntimeException('PRIVATE_SOURCE SQLSTATE /srv/secret.php'));
        $response = $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('summary.main_source_available', false)
            ->assertJsonPath('summary.coverage', 'partial');
        foreach (['MET-01', 'MET-02', 'MET-04', 'MET-05', 'MET-06', 'MET-07'] as $id) {
            $metric = $this->metric($response->json(), $id);
            $this->assertNull($metric['value']);
            $this->assertSame('source_unavailable', $metric['reason']);
        }
        $this->assertStringNotContainsString('PRIVATE_SOURCE', $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_all_unknown_summary_is_unknown_not_ok(): void
    {
        config(['queue.default' => 'redis', 'queue.failed.driver' => 'null']);
        DB::partialMock()->shouldReceive('table')->andThrow(new \RuntimeException('PRIVATE_SOURCE'));
        $body = $this->summary();
        $this->assertSame('unknown', $body['summary']['status']);
        $this->assertFalse($body['summary']['main_source_available']);
        $this->assertCount(16, $body['summary']['unknown_ids']);
        foreach ($body['metrics'] as $metric) {
            $this->assertNull($metric['value']);
            $this->assertFalse($metric['exact']);
            $this->assertSame('unknown', $metric['status']);
        }
    }

    public function test_invalid_html_window_returns_422_with_recovery_form(): void
    {
        $this->verifiedAdmin();
        $this->get(self::URL.'?period=custom&from=2026-10-22&to=2026-10-21')
            ->assertStatus(422)->assertSee('Periksa rentang tanggal UTC')->assertSee('name="period"', false);
    }

    public function test_disabled_flag_hides_dashboard_link(): void
    {
        $this->verifiedAdmin();
        config(['features.admin_ops_summary.enabled' => false]);
        $this->get('/admin')->assertOk()->assertDontSee(self::URL, false);
    }

    public function test_ssr_panel_is_discoverable_and_usable_without_javascript(): void
    {
        $this->verifiedAdmin();
        $this->get('/admin')->assertOk()->assertSee(self::URL, false);
        $response = $this->get(self::URL.'?period=today')->assertOk()
            ->assertSee('Ringkasan operasional')->assertSee('UTC')
            ->assertSee('Backlog saat ini')->assertSee('seluruh umur')
            ->assertSee('Belum diperiksa')->assertSee('name="period"', false)
            ->assertSee('name="from"', false)->assertSee('name="to"', false)
            ->assertSee('<table', false)->assertSee('Terapkan');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->flushSession();
        $this->actingAs($this->admin(Admin::ROLE_STAFF), 'admin')->get('/admin')->assertOk()->assertDontSee(self::URL, false);
    }
}
