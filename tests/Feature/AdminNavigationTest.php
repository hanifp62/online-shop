<?php

namespace Tests\Feature;

use App\Enums\AuthEventName;
use App\Models\Admin;
use App\Models\AuthEvent;
use App\Services\AuthEventRecorder;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    // Legacy admin (no TOTP secret) passes EnsureAdminTotp; see AdminTotpTest::test_legacy_admin_can_enter_console.
    private function createLegacyAdmin(string $email = 'operator@example.test'): Admin
    {
        return Admin::create([
            'name' => 'Operator',
            'email' => $email,
            'password' => 'Password123!',
        ]);
    }

    public function test_auth_events_table_is_keyboard_scrollable_and_semantic(): void
    {
        $html = $this->actingAs($this->createLegacyAdmin(), 'admin')
            ->get(route('admin.auth-events.index'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $wrap = $xpath->query('//div[contains(@class,"auth-events-table-wrap")]')->item(0);
        $this->assertSame('0', $wrap->getAttribute('tabindex'));
        $this->assertSame('region', $wrap->getAttribute('role'));
        $this->assertNotSame('', $wrap->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('//table/caption')->length);
        $this->assertSame($xpath->query('//th')->length, $xpath->query('//th[@scope="col"]')->length);
        $this->assertStringContainsString('min-height: 44px', $html);
    }

    public function test_admin_console_has_visible_focus_and_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/admin.css'));
        $this->assertStringContainsString('body.admin-console :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('summary', $css);
    }

    public function test_admin_console_uses_its_own_visual_system_separate_from_storefront(): void
    {
        $html = $this->actingAs($this->createLegacyAdmin(), 'admin')
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<body class="admin-console">', $html);
        $this->assertStringNotContainsString('class="storefront"', $html);
        $this->assertMatchesRegularExpression('#/build/assets/admin-[^"]+\.css#', $html);
        // Console styles live in admin.css, not inline in the layout or dashboard.
        $this->assertStringNotContainsString('.admin-work-header {', $html);
        $this->assertStringNotContainsString('.stat-grid {', $html);

        $storefront = $this->get(route('home'))->getContent();
        $this->assertDoesNotMatchRegularExpression('#/build/assets/admin-[^"]+\.css#', $storefront);
    }

    public function test_dashboard_uses_shared_navigation_shell_with_active_marker_and_skip_link(): void
    {
        $admin = $this->createLegacyAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="#main-content"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('aria-label="Navigasi konsol admin"', false);
        $response->assertSee('href="'.route('admin.dashboard').'"', false);
        $response->assertSee('href="'.route('admin.auth-events.index').'"', false);
        $response->assertSee('href="'.route('home').'"', false);
        $response->assertSee('data-admin-active="dashboard"', false);
        $response->assertSee('<a href="'.route('admin.dashboard').'" aria-current="page">Dashboard</a>', false);
        $response->assertSee('<a href="'.route('admin.auth-events.index').'">Log autentikasi</a>', false);
        $response->assertSee('<span aria-current="page">Dashboard Maison</span>', false);
        $response->assertSee('Dashboard Maison');
        $response->assertSee('Keluar');
        $response->assertSee('#panel-kategori', false);
        $response->assertSee('#panel-produk', false);
        $response->assertSee('#panel-pesanan', false);
        $response->assertSee('#panel-user', false);
        $response->assertSee('#panel-audit', false);
        $response->assertSee('#panel-concierge', false);
        $response->assertSee('#panel-subscriber', false);
        $response->assertSee('#panel-keamanan', false);
        $response->assertSee(route('admin.categories.store'), false);
        $response->assertSee(route('admin.products.store'), false);
        $response->assertSee(route('admin.orders.report'), false);
        $response->assertSee(route('admin.password.update'), false);
    }

    public function test_auth_events_uses_shared_navigation_shell_with_active_marker_and_breadcrumb(): void
    {
        $admin = $this->createLegacyAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index'));

        $response->assertOk();
        $response->assertSee('href="#main-content"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('aria-label="Navigasi konsol admin"', false);
        $response->assertSee('aria-label="Jejak navigasi"', false);
        $response->assertSee('data-admin-active="auth-events"', false);
        $response->assertSee('<a href="'.route('admin.dashboard').'">Dashboard</a>', false);
        $response->assertSee('<a href="'.route('admin.auth-events.index').'" aria-current="page">Log autentikasi</a>', false);
        $response->assertSee('for="auth-event-filter"', false);
        $response->assertSee('id="auth-event-filter"', false);
        $response->assertSee('for="auth-outcome-filter"', false);
        $response->assertSee('id="auth-outcome-filter"', false);
        $response->assertSee('class="auth-events-table-wrap"', false);
        $response->assertSee('Log Peristiwa Autentikasi');
        $response->assertSee(route('admin.dashboard'), false);
    }

    public function test_auth_events_filters_and_pagination_preserve_query_and_active_context(): void
    {
        $admin = $this->createLegacyAdmin();
        $recorder = app(AuthEventRecorder::class);

        for ($i = 1; $i <= 30; $i++) {
            $recorder->record(
                AuthEventName::LOGIN_FAILURE,
                'failure',
                'admin',
                $admin->id,
                '127.0.0.1',
                ['attempt' => $i]
            );
        }

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index', [
                'event' => AuthEventName::LOGIN_FAILURE,
                'outcome' => 'failure',
                'page' => 1,
            ]));

        $response->assertOk();
        $response->assertSee('value="LOGIN_FAILURE" selected', false);
        $response->assertSee('value="failure" selected', false);
        $response->assertSee('data-active-filter="event: LOGIN_FAILURE"', false);
        $response->assertSee('data-active-filter="outcome: failure"', false);
        $response->assertSee('href="'.route('admin.auth-events.index').'"', false);
        $response->assertSeeText('event: LOGIN_FAILURE');
        $response->assertSeeText('outcome: failure');

        $pageTwoUrl = route('admin.auth-events.index', [
            'event' => AuthEventName::LOGIN_FAILURE,
            'outcome' => 'failure',
            'page' => 2,
        ]);
        $response->assertSee('href="'.e($pageTwoUrl).'"', false);

        $pageTwo = $this->get($pageTwoUrl)->assertOk();
        $pageTwo->assertViewHas('events', function ($events) {
            return $events->currentPage() === 2
                && $events->total() === 30
                && $events->count() === 5
                && $events->every(fn ($event) => $event->event === AuthEventName::LOGIN_FAILURE
                    && $event->outcome === 'failure');
        });
        $pageTwo->assertSeeText('event: LOGIN_FAILURE');
        $pageTwo->assertSeeText('outcome: failure');

        $document = new \DOMDocument;
        @$document->loadHTML($pageTwo->getContent());
        $resetLinks = (new \DOMXPath($document))->query('//a[normalize-space(.)="Reset"]');
        $this->assertCount(1, $resetLinks);
        $resetUrl = $resetLinks->item(0)->getAttribute('href');
        $this->assertSame(route('admin.auth-events.index'), $resetUrl);
        $this->assertNull(parse_url($resetUrl, PHP_URL_QUERY));

        $this->get($resetUrl)->assertOk()
            ->assertDontSee('data-active-filter=', false)
            ->assertDontSeeText('Filter aktif:')
            ->assertViewHas('events', fn ($events) => $events->currentPage() === 1);
    }

    public function test_verified_totp_admin_can_access_both_navigation_shell_pages(): void
    {
        $admin = $this->createLegacyAdmin('verified@example.test');
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill([
            'totp_secret' => $secret,
            'totp_required_at' => now(),
        ])->save();

        $g = new Google2FA;
        $res = $this->actingAs($admin->fresh(), 'admin')
            ->post('/admin/totp', ['code' => $g->oathTotp($secret, $g->getTimestamp())]);

        $res->assertRedirect(route('admin.dashboard'));
        // EnsureAdminTotp binds the marker to the session id, so replay the rotated session cookie.
        $sid = session()->getId();

        foreach (['admin.dashboard' => 'dashboard', 'admin.auth-events.index' => 'auth-events'] as $route => $active) {
            $this->withCookie(config('session.cookie'), $sid)
                ->get(route($route))->assertOk()
                ->assertSee('data-admin-active="'.$active.'"', false)
                ->assertSee('aria-label="Navigasi konsol admin"', false);
        }
    }

    public function test_empty_filtered_events_keep_active_context_and_reset_link(): void
    {
        $this->actingAs($this->createLegacyAdmin(), 'admin')
            ->get(route('admin.auth-events.index', ['event' => AuthEventName::LOGIN_FAILURE, 'outcome' => 'failure']))
            ->assertOk()
            ->assertSee('Belum ada rekaman peristiwa autentikasi.')
            ->assertSee('data-active-filter="event: LOGIN_FAILURE"', false)
            ->assertSee('data-active-filter="outcome: failure"', false)
            ->assertSeeText('event: LOGIN_FAILURE')
            ->assertSeeText('outcome: failure')
            ->assertSee('<a href="'.route('admin.auth-events.index').'" style="align-self: center; margin-left: 8px; font-size: 13px; color: var(--zafira-muted);">Reset</a>', false);
    }

    public function test_auth_events_without_filters_omit_active_context_and_reset(): void
    {
        $admin = $this->createLegacyAdmin();

        // When visiting with no filter query params, active context and reset anchor are both omitted.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index'))
            ->assertOk()
            ->assertDontSee('data-active-filter=', false)
            ->assertDontSeeText('Filter aktif:')
            ->assertDontSee('>Reset</a>', false);

        // When query parameters are present but empty strings, active filter badges remain omitted.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index', ['event' => '', 'outcome' => '']))
            ->assertOk()
            ->assertDontSee('data-active-filter=', false)
            ->assertDontSeeText('Filter aktif:');
    }

    public function test_auth_events_can_be_filtered_by_request_id(): void
    {
        $admin = $this->createLegacyAdmin();
        AuthEvent::create(['occurred_at' => now(), 'event' => 'LOGIN_FAILURE', 'outcome' => 'failure', 'request_id' => 'req-target-0001']);
        AuthEvent::create(['occurred_at' => now(), 'event' => 'LOGIN_FAILURE', 'outcome' => 'failure', 'request_id' => 'req-other-0002']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index', ['request_id' => 'req-target-0001']))
            ->assertOk()
            ->assertSee('req-target-0001')
            ->assertDontSee('req-other-0002')
            ->assertSee('data-active-filter="request_id: req-target-0001"', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index', ['request_id' => '<script>']))
            ->assertSessionHasErrors('request_id');
    }

    public function test_auth_events_active_filter_values_are_escaped_in_view(): void
    {
        $this->actingAs($this->createLegacyAdmin(), 'admin');
        // Controller validation rejects such values, so render the view directly.
        request()->merge(['event' => '<script>alert(1)</script>', 'outcome' => '"><b>x</b>']);

        $html = view('admin.auth-events', ['events' => AuthEvent::query()->paginate(25)])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('event: &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('outcome: &quot;&gt;&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function test_unverified_totp_admin_cannot_access_navigation_shell_pages(): void
    {
        $admin = $this->createLegacyAdmin('protected@example.test');
        $admin->forceFill([
            'totp_secret' => app(TotpService::class)->generateSecret(),
            'totp_required_at' => now(),
        ])->save();
        $admin = $admin->fresh();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.totp.challenge'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.auth-events.index'))
            ->assertRedirect(route('admin.totp.challenge'));
    }
}
