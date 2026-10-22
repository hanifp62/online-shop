<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerTotpController;
use App\Http\Middleware\AuthenticateGuardSession;
use App\Models\Admin;
use App\Models\User;
use App\Support\AuthSecurityPageProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** FR-FE-001/002, NFR-FE-001/002. Run through the guarded isolated test harness. */
class InertiaAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['Admin/Login', 'Admin/TotpChallenge', 'Auth/TotpChallenge', 'Auth/ResetPassword'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true,
            'frontend.pages' => self::PAGES,
            'inertia.testing.page_paths' => [resource_path('js/pages')],
        ]);
    }

    public static function pages(): array
    {
        return [
            'admin login' => ['Admin/Login', 'admin.login', 'admin.login.process', 'admin_ui.login.heading'],
            'admin challenge' => ['Admin/TotpChallenge', 'admin.totp.challenge', 'admin.totp.verify', 'admin_ui.totp.heading'],
            'customer challenge' => ['Auth/TotpChallenge', 'login.totp', 'login.totp.verify', 'auth_ui.totp.heading'],
            'password reset' => ['Auth/ResetPassword', 'password.reset', 'password.update', 'auth_ui.reset.heading'],
        ];
    }

    private function preparePage(string $component): void
    {
        if ($component === 'Admin/TotpChallenge') {
            $admin = Admin::create(['name' => 'Security reader', 'email' => 'security-admin@example.test', 'password' => 'Synthetic-only!123']);
            $admin->forceFill(['totp_required_at' => now(), 'totp_secret' => 'synthetic-secret-not-used-for-verification'])->save();
            $this->actingAs($admin, 'admin');
        }
        if ($component === 'Auth/TotpChallenge') {
            $user = User::factory()->create();
            $this->withSession([CustomerTotpController::PENDING_LOGIN => [
                'user_id' => $user->id, 'remember' => true,
                'password' => AuthenticateGuardSession::fingerprint($user),
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ]]);
        }
    }

    private function url(string $route): string
    {
        return route($route, $route === 'password.reset' ? ['token' => 'synthetic-reset-token', 'email' => 'client@example.test'] : []);
    }

    #[DataProvider('pages')]
    public function test_native_get_has_safe_props_and_live_html_fallback(string $component, string $route, string $submit, string $heading): void
    {
        $this->preparePage($component);
        $response = $this->withSession(['_old_input' => [
            'email' => 'old@example.test', 'password' => 'synthetic-password-marker',
            'password_confirmation' => 'synthetic-confirmation-marker', 'code' => 'synthetic-code-marker',
            'recovery_code' => 'synthetic-recovery-marker', 'token' => 'synthetic-old-token-marker',
            '_token' => 'synthetic-old-csrf-marker',
        ], 'private_payload' => 'synthetic-session-marker'])->get($this->url($route))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component($component)
            ->where('copy.page.heading', trans($heading))->where('urls.submit', route($submit))
            ->missing('resetToken')->missing('token')->missing('password')->missing('code')
            ->missing('user')->missing('admin')->missing('session')->missing('csrfToken'));
        $page = $response->viewData('page');
        $keys = array_keys($page['props']);
        sort($keys);
        $this->assertSame(['assets', 'copy', 'errors', 'locale', 'old', 'status', 'urls'], $keys);
        $this->assertSame(in_array($component, ['Admin/Login', 'Auth/ResetPassword'], true) ? ['email' => 'old@example.test'] : [], $page['props']['old']);
        $serialized = json_encode($page['props'], JSON_THROW_ON_ERROR);
        foreach (['synthetic-password-marker', 'synthetic-confirmation-marker', 'synthetic-code-marker', 'synthetic-recovery-marker', 'synthetic-old-token-marker', 'synthetic-old-csrf-marker', 'synthetic-session-marker', 'synthetic-reset-token'] as $marker) {
            $this->assertStringNotContainsString($marker, $serialized);
        }
        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-react-fallback', false)->assertSee('data-page="app"', false)
            ->assertSee('action="'.route($submit).'"', false)->assertSee('name="_token"', false)
            ->assertSee('<meta name="csrf-token"', false)->assertSee('zafira:private-leave', false);
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $this->assertStringContainsString('<h1>'.e(trans($heading)).'</h1>', $response->getContent());
        $this->assertTrue($page['encryptHistory']);
        if ($component === 'Auth/ResetPassword') {
            $this->assertSame('synthetic-reset-token', $page['flash']['resetToken']);
            $response->assertSee('name="token" value="synthetic-reset-token"', false);
        }
    }

    #[DataProvider('pages')]
    public function test_global_allowlist_and_request_fallbacks_keep_native_forms(string $component, string $route, string $submit, string $heading): void
    {
        $this->preparePage($component);
        foreach (['allowlist', 'disabled', 'request'] as $mode) {
            config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'allowlist' ? [] : self::PAGES]);
            $url = $this->url($route);
            if ($mode === 'request') {
                $url .= (str_contains($url, '?') ? '&' : '?').'frontend=blade';
            }
            $this->get($url)->assertOk()->assertDontSee('data-page="app"', false)
                ->assertDontSee('data-react-fallback', false)->assertSee('action="'.route($submit).'"', false)
                ->assertSee('<h1>'.e(trans($heading)).'</h1>', false)->assertSee('zafira:private-leave', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_reset_flash_is_top_level_for_json_and_not_replayed_on_another_page(): void
    {
        $version = $this->get($this->url('password.reset'))->viewData('page')['version'];
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'];
        $response = $this->get($this->url('password.reset'), $headers)->assertOk();
        $response->assertJsonPath('component', 'Auth/ResetPassword')->assertJsonPath('flash.resetToken', 'synthetic-reset-token');
        $this->assertArrayNotHasKey('resetToken', $response->json('props'));
        $this->assertStringNotContainsString('synthetic-reset-token', json_encode($response->json('props'), JSON_THROW_ON_ERROR));
        $this->get(route('admin.login'), $headers)
            ->assertOk()->assertJsonMissingPath('flash.resetToken');
    }

    public function test_get_guards_do_not_render_challenges_without_valid_identity(): void
    {
        $this->get(route('admin.totp.challenge'))->assertRedirect(route('admin.login'));
        $this->get(route('login.totp'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        foreach (['expired', 'wrong-fingerprint'] as $case) {
            $this->withSession([CustomerTotpController::PENDING_LOGIN => [
                'user_id' => $user->id, 'remember' => false,
                'password' => $case === 'expired' ? AuthenticateGuardSession::fingerprint($user) : 'wrong',
                'expires_at' => now()->addSeconds($case === 'expired' ? -1 : 60)->getTimestamp(),
            ]])->get(route('login.totp'))->assertRedirect(route('login'));
        }
        $admin = Admin::create(['name' => 'No TOTP', 'email' => 'no-totp@example.test', 'password' => 'Synthetic-only!123']);
        $this->actingAs($admin, 'admin')->get(route('admin.totp.challenge'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_dto_rejects_nested_email_and_never_copies_arbitrary_input_or_session(): void
    {
        $request = Request::create('/reset-kata-sandi/synthetic', 'GET', ['email' => ['private' => 'nested-marker'], 'token' => 'input-marker']);
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('_old_input', ['email' => ['private' => 'nested-marker'], 'password' => 'input-marker']);
        $request->session()->put('success', ['private' => 'input-marker']);
        foreach (['adminLogin', 'adminTotp', 'customerTotp', 'reset'] as $method) {
            $props = AuthSecurityPageProps::$method($request);
            $this->assertSame(in_array($method, ['adminLogin', 'reset'], true) ? ['email' => ''] : [], $props['old']);
            $this->assertNull($props['status']);
            $this->assertStringNotContainsString('marker', json_encode($props, JSON_THROW_ON_ERROR));
        }
    }
}
