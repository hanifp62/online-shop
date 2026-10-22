<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminTotpController;
use App\Http\Controllers\AdminTotpEnrolController;
use App\Http\Controllers\CustomerTotpController;
use App\Http\Middleware\EnsureAdminTotp;
use App\Models\Admin;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\UserInfoBackup;
use App\Services\TotpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * REM-20261021 / FR-REM-001, FR-REM-004, NFR-REM-003.
 * Acceptance tests, not the defect-preserving assertions in AuditSnapshotProbeTest.
 * Run serially through the integrator's isolated test launcher only.
 */
class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Synthetic-only!Password123';

    private Google2FA $clock;

    private string $defaultGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultGuard = config('auth.defaults.guard');
        Notification::fake();
        Http::preventStrayRequests();
        config(['cache.default' => 'array']);
        $this->travelTo(now()->startOfMinute()->addSeconds(5));
        // Bridge Google's wall clock only; verification and persistence stay real.
        $this->clock = new class extends Google2FA
        {
            public function getTimestamp()
            {
                return intdiv(now()->timestamp, 30);
            }
        };
        $this->app->instance(Google2FA::class, $this->clock);
        $this->app->instance(TotpService::class, new TotpService($this->clock));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function admin(bool $totp = false): Admin
    {
        $admin = Admin::create([
            'name' => 'Synthetic remediation',
            'email' => 'rem-'.bin2hex(random_bytes(6)).'@example.test',
            'password' => self::PASSWORD,
        ]);
        if ($totp) {
            $admin->forceFill([
                'totp_secret' => app(TotpService::class)->generateSecret(),
                'totp_required_at' => now(),
            ])->save();
        }

        return $admin;
    }

    private function enrolAdmin(Admin $admin): void
    {
        $this->actingAs($admin, 'admin')->get('/admin/totp/enrol')->assertOk();
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $this->post('/admin/totp/enrol', ['code' => $this->clock->getCurrentOtp($secret)])
            ->assertRedirect(route('admin.totp.enrol.recovery'));

    }

    public function test_sec01_reset_notification_uses_canonical_origin_under_untrusted_host(): void
    {
        config(['app.url' => 'https://canonical.example.test']);
        $user = User::factory()->create();
        $response = $this->post('https://untrusted.example.test/lupa-kata-sandi', ['email' => $user->email]);
        // A Host allowlist may reject the request before any notification is issued.
        if (Notification::sent($user, ResetPassword::class)->isEmpty()) {
            $this->assertContains($response->getStatusCode(), [400, 403, 421]);

            return;
        }
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            route('home'); // Prewarm the original RouteUrlGenerator before cloning for mail.
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
            $this->assertSame('canonical.example.test', parse_url($url, PHP_URL_HOST));
            $this->assertStringContainsString('/reset-kata-sandi/', $url);

            return true;
        });
    }

    public function test_sec07_array_email_is_validation_error_on_both_reset_endpoints(): void
    {
        foreach (['/lupa-kata-sandi', '/reset-kata-sandi'] as $uri) {
            $this->postJson($uri, ['email' => ['synthetic@example.test'], 'token' => 'synthetic',
                'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        Notification::assertNothingSent();
    }

    public function test_sec03_dual_guard_logout_prevents_previous_admin_recovery_disclosure(): void
    {
        $user = User::factory()->create();
        $a = $this->admin();
        $b = $this->admin();
        $this->actingAs($user, 'web');
        $this->enrolAdmin($a);
        $this->post('/admin/logout')->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
        $this->post('/admin/login', ['email' => $b->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($b, 'admin');
        $this->get('/admin/totp/enrol/recovery')->assertRedirect(route('admin.dashboard'));
    }

    public function test_sec03_recovery_is_owner_bound_even_without_logout_cleanup(): void
    {
        $a = $this->admin();
        $this->enrolAdmin($a);
        $this->actingAs($this->admin(), 'admin')->get('/admin/totp/enrol/recovery')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_sec03_admin_recovery_expires_and_owner_can_read_fresh_codes_only_once(): void
    {
        $a = $this->admin();
        $this->enrolAdmin($a);
        $this->get('/admin/totp/enrol/recovery')->assertOk()->assertViewHas('codes', fn ($codes) => count($codes) === 8);
        $this->get('/admin/totp/enrol/recovery')->assertRedirect(route('admin.dashboard'));
        $this->post('/admin/logout');
        $b = $this->admin();
        $this->enrolAdmin($b);
        // Deliberately beyond a short-lived enrolment window; no legal retention policy implied.
        $this->travel(1)->days();
        $this->get('/admin/totp/enrol/recovery')->assertRedirect(route('admin.dashboard'));
    }

    public function test_sec03_customer_recovery_is_owner_bound(): void
    {
        $a = User::factory()->create();
        $this->actingAs($a, 'web')->get(route('account.totp.enrol'))->assertOk();
        $secret = Crypt::decryptString(session('customer_totp_secret'));
        $this->post(route('account.totp.store'), ['code' => $this->clock->getCurrentOtp($secret)])
            ->assertRedirect(route('account.totp.recovery'));

        $this->actingAs(User::factory()->create(), 'web')->get(route('account.totp.recovery'))
            ->assertRedirect(route('account.show'));
    }

    public function test_sec03_customer_recovery_expires(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->get(route('account.totp.enrol'))->assertOk();
        $secret = Crypt::decryptString(session('customer_totp_secret'));
        $this->post(route('account.totp.store'), ['code' => $this->clock->getCurrentOtp($secret)])
            ->assertRedirect(route('account.totp.recovery'));
        $this->travel(1)->days();
        $this->get(route('account.totp.recovery'))->assertRedirect(route('account.show'));
    }

    /** Independent cookie jars using the repository's AdminTotpReplayTest pattern. */
    private function browser(string $method, string $uri, array $data = [], ?string $sid = null): array
    {
        $this->app['session']->driver()->flush();
        Auth::forgetGuards();
        Auth::shouldUse($this->defaultGuard);
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());

        return [$response, $this->app['session']->driver()->getId()];
    }

    private function loginBrowser(Admin $admin): string
    {
        [, $sid] = $this->browser('GET', '/admin/login');
        [$response, $sid] = $this->browser('POST', '/admin/login', [
            'email' => $admin->email, 'password' => self::PASSWORD,
        ], $sid);
        $response->assertRedirect($admin->requiresTotp() ? '/admin/totp' : '/admin');

        return $sid;
    }

    public function test_sec02_password_change_revokes_other_legacy_admin_session(): void
    {
        $admin = $this->admin();
        $old = $this->loginBrowser($admin);
        $current = $this->loginBrowser($admin);
        [$response] = $this->browser('PATCH', '/admin/password', [
            'current_password' => self::PASSWORD, 'password' => self::PASSWORD.'New',
            'password_confirmation' => self::PASSWORD.'New',
        ], $current);
        $response->assertSessionHasNoErrors();
        [$response] = $this->browser('GET', '/admin', [], $old);
        $response->assertRedirect('/admin/login');
    }

    public function test_sec02_password_change_revokes_pending_challenge_without_resetting_replay_watermark(): void
    {
        $admin = $this->admin(true);
        $pending = $this->loginBrowser($admin);
        $current = $this->loginBrowser($admin);
        $code = $this->clock->getCurrentOtp($admin->totp_secret);
        [$response, $current] = $this->browser('POST', '/admin/totp', ['code' => $code], $current);
        $response->assertRedirect('/admin');
        $watermark = $admin->fresh()->totp_last_used_at;
        [$response] = $this->browser('PATCH', '/admin/password', [
            'current_password' => self::PASSWORD, 'password' => self::PASSWORD.'New',
            'password_confirmation' => self::PASSWORD.'New',
        ], $current);
        $response->assertSessionHasNoErrors();
        $this->assertSame($watermark, $admin->fresh()->totp_last_used_at);
        [$response] = $this->browser('POST', '/admin/totp', ['code' => $code], $pending);
        $response->assertRedirect('/admin/login');
        $this->assertFalse(app(TotpService::class)->verifyAndAdvance($admin->id, $code)['ok']);
    }

    public function test_sec04_failed_totp_budget_follows_account_across_ips(): void
    {
        $admin = $this->admin(true);
        $service = Mockery::mock(TotpService::class)->makePartial();
        // The sixth attempt must not reach the verifier, even from a different IP.
        $service->shouldReceive('verifyAndAdvance')->times(5)->andReturn(['ok' => false, 'reason' => 'invalid_code']);
        $this->app->instance(TotpService::class, $service);
        $this->actingAs($admin, 'admin');
        for ($i = 1; $i <= 6; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])
                ->post('/admin/totp', ['code' => '123456'])->assertSessionHasErrors('code');
        }
    }

    public function test_sec05_stale_customer_enrolment_cannot_overwrite_winner_or_receive_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.totp.enrol'))->assertOk();
        $pendingSecret = Crypt::decryptString(session('customer_totp_secret'));
        $winnerSecret = app(TotpService::class)->generateSecret();
        $service = Mockery::mock(TotpService::class, [$this->clock])->makePartial();
        // Commit another enrolment after the initial hasTotp check, before persistence.
        $service->shouldReceive('verifyPendingSecret')->once()->andReturnUsing(function () use ($user, $winnerSecret) {
            $winner = User::findOrFail($user->id);
            $winner->forceFill(['totp_secret' => $winnerSecret, 'totp_enabled_at' => now(),
                'totp_last_used_at' => $this->clock->getTimestamp()])->save();
            app(TotpService::class)->replaceUserRecoveryCodes($winner, ['ABCDE-23456']);

            return $this->clock->getTimestamp();
        });
        $this->app->instance(TotpService::class, $service);
        $this->post(route('account.totp.store'), ['code' => $this->clock->getCurrentOtp($pendingSecret)])
            ->assertRedirect(route('account.show'));
        $this->assertSame($winnerSecret, $user->fresh()->totp_secret);
        $this->assertSame(1, $user->totpRecoveryCodes()->count());
        $this->get(route('account.totp.recovery'))->assertRedirect(route('account.show'));
    }

    public function test_sec06_unverified_email_cannot_export_someone_elses_subscription(): void
    {
        $user = User::factory()->unverified()->create();
        NewsletterSubscriber::create(['email' => $user->email]);
        $this->actingAs($user)->get(route('account.export'))->assertOk()->assertJsonPath('newsletter_subscribed', false);
    }

    public function test_sec06_unverified_email_cannot_delete_someone_elses_subscription(): void
    {
        $user = User::factory()->unverified()->create(['has_password' => false]);
        $subscriber = NewsletterSubscriber::create(['email' => $user->email]);
        $this->actingAs($user)->delete(route('account.destroy'), ['confirmation' => 'HAPUS AKUN SAYA'])->assertRedirect(route('home'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id, 'email' => $user->email]);
    }

    public function test_sec01_local_http_canonical_url_is_supported_without_forcing_navigation_origin(): void
    {
        config(['app.url' => 'http://localhost:8123']);
        $user = User::factory()->create();
        $this->post('http://dev.example.test/lupa-kata-sandi', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            route('home');
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('localhost', parse_url($url, PHP_URL_HOST));
            $this->assertSame(8123, parse_url($url, PHP_URL_PORT));
            $this->assertSame('http', parse_url($url, PHP_URL_SCHEME));
            $this->assertSame('dev.example.test', parse_url(route('home'), PHP_URL_HOST));

            return true;
        });
    }

    public function test_sec01_verification_mail_uses_canonical_signed_url(): void
    {
        config(['app.url' => 'https://canonical.example.test']);
        $this->get('https://untrusted.example.test/');
        $user = User::factory()->unverified()->create();
        route('home');
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $this->assertSame('canonical.example.test', parse_url($url, PHP_URL_HOST));
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_sec01_prewarmed_verification_mail_supports_canonical_http_port(): void
    {
        config(['app.url' => 'http://localhost:8123']);
        $this->get('https://untrusted.example.test/');
        $navigation = route('home');
        $user = User::factory()->unverified()->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $this->assertSame('http', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('localhost', parse_url($url, PHP_URL_HOST));
        $this->assertSame(8123, parse_url($url, PHP_URL_PORT));
        $this->assertSame($navigation, route('home'));
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /** @return array{Admin, string} */
    private function verifiedAdminBrowser(?User $customer = null): array
    {
        $admin = $this->admin(true);
        [, $sid] = $this->browser('GET', '/login');
        if ($customer) {
            [$response, $sid] = $this->browser('POST', '/login', [
                'email' => $customer->email, 'password' => self::PASSWORD,
            ], $sid);
            $response->assertRedirect();
        }
        [$response, $sid] = $this->browser('POST', '/admin/login', [
            'email' => $admin->email, 'password' => self::PASSWORD,
        ], $sid);
        $response->assertRedirect('/admin/totp');
        [$response, $sid] = $this->browser('POST', '/admin/totp', [
            'code' => $this->clock->getCurrentOtp($admin->totp_secret),
        ], $sid);
        $response->assertRedirect('/admin');
        [$response] = $this->browser('GET', '/admin', [], $sid);
        $response->assertOk();

        return [$admin, $sid];
    }

    private function assertVerifiedAdminSurvives(Admin $admin, string $oldId, string $newId): void
    {
        $this->assertNotSame($oldId, $newId);
        $this->assertSame($newId, session(EnsureAdminTotp::SESSION_KEY)['session_id']);
        $this->assertSame($admin->fresh()->totp_last_used_at, session(EnsureAdminTotp::SESSION_KEY)['last_used_at']);
        [$response] = $this->browser('GET', '/admin', [], $newId);
        $response->assertOk();
        [$oldResponse] = $this->browser('GET', '/admin', [], $oldId);
        $oldResponse->assertRedirect('/admin/login');
    }

    public function test_sec02_verified_admin_survives_customer_password_reset_rotation(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [$admin, $sid] = $this->verifiedAdminBrowser();
        $token = Password::broker('users')->createToken($user);
        [$response, $rotated] = $this->browser('POST', '/reset-kata-sandi', [
            'token' => $token, 'email' => $user->email,
            'password' => self::PASSWORD.'New', 'password_confirmation' => self::PASSWORD.'New',
        ], $sid);
        $response->assertRedirect('/login');
        $this->assertVerifiedAdminSurvives($admin, $sid, $rotated);
    }

    public function test_sec02_verified_admin_survives_customer_deletion_rotation(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [$admin, $sid] = $this->verifiedAdminBrowser($user);
        [$response, $rotated] = $this->browser('DELETE', '/akun', ['password' => self::PASSWORD], $sid);
        $response->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertVerifiedAdminSurvives($admin, $sid, $rotated);
    }

    public function test_sec02_verified_admin_survives_customer_revocation_rotation(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [$admin, $sid] = $this->verifiedAdminBrowser($user);
        $user->forceFill(['password' => self::PASSWORD.'New'])->save();
        [$response, $rotated] = $this->browser('GET', '/akun', [], $sid);
        $response->assertRedirect('/login');
        $this->assertVerifiedAdminSurvives($admin, $sid, $rotated);
    }

    public static function invalidOtherGuardMarkers(): array
    {
        return [['session_id'], ['admin_id'], ['last_used_at']];
    }

    #[DataProvider('invalidOtherGuardMarkers')]
    public function test_sec02_rotation_never_repairs_invalid_other_guard_totp_marker(string $field): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [, $sid] = $this->verifiedAdminBrowser($user);
        $marker = session(EnsureAdminTotp::SESSION_KEY);
        $marker[$field] = $field === 'session_id' ? 'not-the-current-session' : $marker[$field] + 1;
        $this->app['session']->driver()->put(EnsureAdminTotp::SESSION_KEY, $marker);
        $this->app['session']->driver()->save();
        $user->forceFill(['password' => self::PASSWORD.'New'])->save();
        [$response, $rotated] = $this->browser('GET', '/akun', [], $sid);
        $response->assertRedirect('/login');
        $this->assertNull(session(EnsureAdminTotp::SESSION_KEY));
        [$response] = $this->browser('GET', '/admin', [], $rotated);
        $response->assertRedirect('/admin/totp');
    }

    public function test_sec04_success_does_not_clear_other_inflight_totp_reservations(): void
    {
        $admin = $this->admin(true);
        $sid = $this->loginBrowser($admin);
        $key = 'admin-totp:account:'.$admin->id;
        RateLimiter::increment($key, 60, 2);
        [$response] = $this->browser('POST', '/admin/totp', [
            'code' => $this->clock->getCurrentOtp($admin->totp_secret),
        ], $sid);
        $response->assertRedirect('/admin');
        $this->assertSame(3, RateLimiter::attempts($key));
        $this->assertSame(1, RateLimiter::attempts('admin-totp:ip:127.0.0.1'));
    }

    public static function reservationBuckets(): array
    {
        return [['account', 5], ['ip', 25]];
    }

    #[DataProvider('reservationBuckets')]
    public function test_sec04_challenge_reserves_last_slot_before_verifier_interleaving(string $bucket, int $limit): void
    {
        $admin = $this->admin(true);
        $key = $bucket === 'account' ? 'admin-totp:account:'.$admin->id : 'admin-totp:ip:127.0.0.1';
        RateLimiter::increment($key, 60, $limit - 1);
        $calls = 0;
        $service = Mockery::mock(TotpService::class)->makePartial();
        $service->shouldReceive('verifyAndAdvance')->andReturnUsing(function () use ($admin, $key, $bucket, $limit, &$calls) {
            if (++$calls === 1) {
                $nested = Request::create('/admin/totp', 'POST', ['code' => '123456'], server: [
                    'REMOTE_ADDR' => $bucket === 'account' ? '192.0.2.99' : '127.0.0.1',
                ]);
                $nested->setLaravelSession($this->app['session']->driver());
                $nested->setUserResolver(fn () => $admin);
                app(AdminTotpController::class)->verify($nested);
                $this->assertSame($limit, RateLimiter::attempts($key));
                $this->assertStringContainsString('detik', session('errors')->first('code'));
            }

            return ['ok' => false, 'reason' => 'invalid_code'];
        });
        $this->app->instance(TotpService::class, $service);
        $this->actingAs($admin, 'admin')->post('/admin/totp', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertSame(1, $calls, 'The overlapping sixth attempt must never reach the verifier.');
    }

    #[DataProvider('reservationBuckets')]
    public function test_sec04_enrolment_reserves_last_slot_before_verifier_interleaving(string $bucket, int $limit): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get('/admin/totp/enrol')->assertOk();
        $key = $bucket === 'account' ? 'admin-totp-enrol:account:'.$admin->id : 'admin-totp-enrol:ip:127.0.0.1';
        RateLimiter::increment($key, 60, $limit - 1);
        $calls = 0;
        $google = Mockery::mock(Google2FA::class)->makePartial();
        $google->shouldReceive('verifyKeyNewer')->andReturnUsing(function () use ($admin, $key, $bucket, $limit, &$calls) {
            if (++$calls === 1) {
                $nested = Request::create('/admin/totp/enrol', 'POST', ['code' => '123456'], server: [
                    'REMOTE_ADDR' => $bucket === 'account' ? '192.0.2.99' : '127.0.0.1',
                ]);
                $nested->setLaravelSession($this->app['session']->driver());
                $nested->setUserResolver(fn () => $admin);
                app(AdminTotpEnrolController::class)->store($nested);
                $this->assertSame($limit, RateLimiter::attempts($key));
                $this->assertStringContainsString('detik', session('errors')->first('code'));
            }

            return false;
        });
        $this->app->instance(Google2FA::class, $google);
        $this->post('/admin/totp/enrol', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertSame(1, $calls);
        $this->assertNull($admin->fresh()->totp_secret);
    }

    public function test_sec03_pending_customer_secret_cannot_be_confirmed_by_another_owner(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->get(route('account.totp.enrol'))->assertOk();
        $secret = Crypt::decryptString(session('customer_totp_secret'));
        $this->actingAs($b)->post(route('account.totp.store'), ['code' => $this->clock->getCurrentOtp($secret)])
            ->assertRedirect(route('account.totp.enrol'));
        $this->assertFalse($b->fresh()->hasTotp());
    }

    public function test_sec03_pending_admin_secret_expires_before_confirmation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get('/admin/totp/enrol')->assertOk();
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $this->travel(1)->days();
        $this->post('/admin/totp/enrol', ['code' => $this->clock->getCurrentOtp($secret)])
            ->assertRedirect(route('admin.totp.enrol'));
        $this->assertNull($admin->fresh()->getRawOriginal('totp_secret'));
    }

    public function test_sec02_customer_pending_challenge_is_revoked_after_password_change(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $user->forceFill(['totp_secret' => app(TotpService::class)->generateSecret(), 'totp_enabled_at' => now()])->save();
        $this->post(route('login.process'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('login.totp'));
        $user->forceFill(['password' => self::PASSWORD.'New'])->save();
        $this->post(route('login.totp.verify'), ['code' => $this->clock->getCurrentOtp($user->totp_secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertNull(session(CustomerTotpController::PENDING_LOGIN));
    }

    public function test_sec02_revoked_admin_session_preserves_authenticated_customer_guard(): void
    {
        $user = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($user, 'web');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect();
        $admin->forceFill(['password' => self::PASSWORD.'New'])->save();
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_sec02_customer_password_reset_revokes_old_web_sessions_without_revoking_admin(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $admin = $this->admin();
        [, $sid] = $this->browser('GET', '/login');
        [$response, $sid] = $this->browser('POST', '/login', [
            'email' => $user->email, 'password' => self::PASSWORD,
        ], $sid);
        $response->assertRedirect();
        [$response, $shared] = $this->browser('POST', '/admin/login', [
            'email' => $admin->email, 'password' => self::PASSWORD,
        ], $sid);
        $response->assertRedirect('/admin');
        $token = Password::broker('users')->createToken($user);
        [$response] = $this->browser('POST', '/reset-kata-sandi', [
            'token' => $token, 'email' => $user->email,
            'password' => self::PASSWORD.'New', 'password_confirmation' => self::PASSWORD.'New',
        ]);
        $response->assertRedirect('/login');
        [$response, $revoked] = $this->browser('GET', '/akun', [], $shared);
        $response->assertRedirect('/login');
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
        [$response] = $this->browser('GET', '/admin', [], $revoked);
        $response->assertOk();
    }

    public function test_sec02_persisted_admin_session_without_revocation_marker_fails_closed(): void
    {
        $admin = $this->admin();
        $sid = $this->loginBrowser($admin);
        // Simulate a pre-rollout session: authenticated guard ID exists but no fingerprint.
        $this->app['session']->driver()->forget('auth_fingerprint_admin');
        $this->app['session']->driver()->save();
        [$response] = $this->browser('GET', '/admin', [], $sid);
        $response->assertRedirect('/admin/login');
    }

    public function test_data07_export_includes_only_own_profile_snapshots_without_internal_identifiers(): void
    {
        $user = User::factory()->create(['first_name' => 'Own snapshot']);
        $other = User::factory()->create(['first_name' => 'Other snapshot']);
        $user->forceFill(['first_name' => 'Own updated'])->save();
        $response = $this->actingAs($user)->get(route('account.export'))->assertOk();
        $snapshots = $response->json('profile_snapshots');
        $this->assertCount(2, $snapshots);
        $this->assertSame(['Own snapshot', 'Own updated'], array_column($snapshots, 'first_name'));
        foreach ($snapshots as $snapshot) {
            $this->assertArrayNotHasKey('user_id', $snapshot);
            $this->assertArrayNotHasKey('id', $snapshot);
            $this->assertNotSame($other->email, $snapshot['email']);
        }
        $this->assertSame(2, UserInfoBackup::where('user_id', $user->id)->count());
    }

    public function test_sec06_verified_owner_subscription_remains_exportable_and_deletable(): void
    {
        $user = User::factory()->create(['has_password' => false]);
        NewsletterSubscriber::create(['email' => $user->email]);
        $this->actingAs($user)->get(route('account.export'))->assertOk()->assertJsonPath('newsletter_subscribed', true);
        $this->delete(route('account.destroy'), ['confirmation' => 'HAPUS AKUN SAYA'])->assertRedirect(route('home'));
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => $user->email]);
    }

    public function test_data01_deletion_checks_active_transactions_inside_identity_lock_transaction(): void
    {
        $user = User::factory()->create(['has_password' => false]);
        $baseLevel = DB::connection()->transactionLevel();
        $checks = [];
        $identityReads = [];
        DB::listen(function (QueryExecuted $query) use (&$checks, &$identityReads) {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'select') && preg_match('/["`]?users["`]?\s+where/', $sql)) {
                $identityReads[] = ['sql' => $sql, 'level' => $query->connection->transactionLevel()];
            }
            if (str_starts_with($sql, 'select') && str_contains($sql, 'exists')
                && preg_match('/["`]?(orders|returns|refunds)["`]?\s+where/', $sql)) {
                $checks[] = $query->connection->transactionLevel();
            }
        });
        $this->actingAs($user)->delete(route('account.destroy'), ['confirmation' => 'HAPUS AKUN SAYA'])
            ->assertRedirect(route('home'));
        $this->assertNotEmpty($checks);
        foreach ($checks as $level) {
            $this->assertGreaterThan($baseLevel, $level, 'Active-transaction decisions must run in the deletion transaction.');
        }
        $lockedReads = array_filter($identityReads, fn ($read) => $read['level'] > $baseLevel
            && (DB::getDriverName() === 'sqlite' || str_contains($read['sql'], 'for update')));
        $this->assertNotEmpty($lockedReads, 'Re-read and lock the same user identity used by checkout before deletion.');
        // SQLite cannot establish row-lock exclusion. Two-connection InnoDB proof remains an integration gate.
    }
}
