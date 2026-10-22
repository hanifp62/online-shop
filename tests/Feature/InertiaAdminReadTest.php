<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthEvent;
use App\Services\OperationalMetrics;
use App\Support\AdminReadPageProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** FR-FE-001/002, NFR-FE-001/002: run only in integrator's isolated PHP harness. */
class InertiaAdminReadTest extends TestCase
{
    use RefreshDatabase;

    private const EVENTS = '/admin/auth-events';

    private const OPS = '/admin/reports/operations-summary';

    private const PAGES = ['Admin/AuthEvents', 'Admin/OperationsSummary'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true, 'frontend.pages' => self::PAGES,
            'inertia.testing.page_paths' => [resource_path('js/pages')],
            'features.admin_ops_summary' => ['enabled' => true, 'rollout' => 100],
        ]);
    }

    private function admin(string $role = Admin::ROLE_ADMIN): void
    {
        $admin = Admin::create(['name' => 'Reader', 'email' => 'reader@example.test', 'password' => 'Password123!', 'role' => $role]);
        $this->actingAs($admin, 'admin');
    }

    private function report(bool $available = true): array
    {
        return [
            'contract_version' => 'ops-metrics.v1', 'as_of' => '2026-10-22T12:00:00Z', 'consistency' => 'best_effort',
            'window' => ['from' => '2026-10-16T00:00:00Z', 'to_exclusive' => '2026-10-23T00:00:00Z', 'tz' => 'UTC', 'period' => '7d'],
            'summary' => ['status' => 'unknown', 'coverage' => 'partial', 'unknown_ids' => ['MET-10'], 'main_source_available' => $available],
            'metrics' => array_map(fn ($id) => ['id' => sprintf('MET-%02d', $id), 'value' => null, 'status' => 'unknown', 'reason' => 'not_in_minimal_slice', 'oldest_age_seconds' => null, 'basis' => 'not_in_minimal_slice', 'time_basis' => 'snapshot'], range(1, 16)),
        ];
    }

    public function test_auth_events_uses_safe_dto_native_page_and_real_fallback(): void
    {
        $this->admin();
        AuthEvent::create(['occurred_at' => now(), 'event' => 'LOGIN_SUCCESS', 'outcome' => 'success', 'actor_type' => 'admin', 'actor_id' => 1,
            'context' => ['guard' => 'admin', 'scope' => 'login', 'password' => 'PRIVATE_SENTINEL', 'reason' => 'PRIVATE_SENTINEL']]);
        $response = $this->get(self::EVENTS.'?event=LOGIN_SUCCESS');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/AuthEvents')
            ->where('filters.event', 'LOGIN_SUCCESS')->where('pagination.total', 1)
            ->where('rows.0.context', ['guard' => 'admin', 'scope' => 'login'])
            ->missing('events')->missing('session')->missing('user')->missing('admin'));
        $response->assertSee('data-react-fallback', false)->assertSee('auth-events-table-wrap', false);
        $response->assertDontSee('PRIVATE_SENTINEL', false);
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($response->viewData('page')['props']));
        $this->get(self::EVENTS.'?frontend=blade')->assertOk()->assertDontSee('PRIVATE_SENTINEL', false)
            ->assertSee('login')->assertSee('admin');
        $this->assertTrue($response->viewData('page')['encryptHistory']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
    }

    public function test_operations_native_json_statuses_validation_and_rollback(): void
    {
        $this->admin();
        $this->mock(OperationalMetrics::class)->shouldReceive('summary')->andReturn($this->report());
        $response = $this->get(self::OPS)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsSummary')->where('report.metrics.0.value', null)->where('report.summary.coverage', 'partial')
            ->missing('session')->missing('user'));
        $response->assertSee('data-react-fallback', false)->assertSee('src="'.asset('js/admin-operations-summary.js').'"', false);
        $this->getJson(self::OPS)->assertOk()->assertJsonPath('contract_version', 'ops-metrics.v1');
        $this->get(self::OPS.'?period=custom&from=2026-10-22&to=2026-10-01')->assertStatus(422)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/OperationsSummary')->where('report', null)->has('validationErrors', 1));
        $this->get(self::OPS.'?frontend=blade')->assertOk()->assertDontSee('data-react-fallback', false)
            ->assertSee('js/admin-operations-summary.js', false);
    }

    public function test_unavailable_operations_preserves_503_in_both_representations(): void
    {
        $this->admin();
        $this->mock(OperationalMetrics::class)->shouldReceive('summary')->andReturn($this->report(false));
        $this->get(self::OPS)->assertStatus(503)->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsSummary')->where('report.summary.main_source_available', false));
        $this->getJson(self::OPS)->assertStatus(503)->assertJsonPath('summary.main_source_available', false);
    }

    public function test_rollback_and_role_gates_remain_server_owned(): void
    {
        $this->admin(Admin::ROLE_STAFF);
        $this->get(self::EVENTS)->assertForbidden();
        $this->get(self::OPS)->assertForbidden();
    }

    public function test_flags_off_and_blade_override_do_not_mount_react(): void
    {
        $this->admin();
        foreach (['global', 'allowlist', 'query'] as $mode) {
            config(['frontend.enabled' => $mode !== 'global', 'frontend.pages' => $mode === 'allowlist' ? [] : self::PAGES]);
            $this->get(self::EVENTS.($mode === 'query' ? '?frontend=blade' : ''))->assertOk()
                ->assertDontSee('data-react-fallback', false)->assertSee('auth-events-table-wrap', false);
        }
        $this->assertFalse(config('inertia.ssr.enabled'));
        $this->assertTrue(config('inertia.history.encrypt'));
    }

    public function test_report_dto_excludes_unexpected_top_level_and_metric_fields(): void
    {
        $report = $this->report();
        $report['session'] = 'PRIVATE_SENTINEL';
        $report['metrics'][0]['customer_email'] = 'PRIVATE_SENTINEL';
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode(AdminReadPageProps::report($report)));
    }
}
