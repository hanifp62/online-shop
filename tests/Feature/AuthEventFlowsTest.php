<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminTotpEnrolController;
use App\Models\Admin;
use App\Models\AuthEvent;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\PasswordResetTokenIssuer;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthEventFlowsTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Audit-only!Password937';

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = tempnam(sys_get_temp_dir(), 'auth-event-log-');
        config(['logging.default' => 'audit_test', 'logging.channels.audit_test' => [
            'driver' => 'single', 'path' => $this->logPath, 'level' => 'debug',
        ]]);
        $this->app->forgetInstance('log');
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance('log');
        if (file_exists($this->logPath)) {
            @unlink($this->logPath);
        }
        parent::tearDown();
    }

    private function admin(bool $totp = true): array
    {
        $admin = Admin::create(['name' => 'Audit Operator', 'email' => 'audit@example.test', 'password' => self::PW]);
        $secret = app(TotpService::class)->generateSecret();
        if ($totp) {
            $admin->forceFill(['totp_secret' => $secret, 'totp_required_at' => now()])->save();
        }
        return [$admin->fresh(), $secret];
    }

    /** Simulate a new request without retaining a previous request's guard or session attributes. */
    private function http(string $method, string $uri, array $data = [], ?string $sid = null, string $ip = '127.0.0.1'): array
    {
        $this->app['session']->driver()->flush();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest(), [], ['REMOTE_ADDR' => $ip]);
        return [$response, $this->app['session']->driver()->getId()];
    }

    private function login(Admin $admin): string
    {
        [$response, $sid] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW]);
        $response->assertRedirect($admin->requiresTotp() ? '/admin/totp' : '/admin');
        return $sid;
    }

    /** Scan raw persisted rows AND the real isolated log, not a mocked logging facade. */
    private function leaked(array $secrets): array
    {
        $payload = json_encode(DB::table('auth_events')->get()).file_get_contents($this->logPath);
        return array_values(array_filter($secrets, fn ($secret) => str_contains($payload, $secret)));
    }

    private function assertClean(array $secrets): void
    {
        $needles = $secrets;
        foreach ($secrets as $secret) {
            if (strlen($secret) >= 10) {
                $needles[] = substr($secret, 0, 8);
                $needles[] = substr($secret, -8);
            }
        }
        $this->assertSame([], $this->leaked($needles), 'Secrets must not reach audit storage or logs.');
    }

    public function test_complete_totp_login_failure_lockout_and_password_rotation(): void
    {
        [$admin, $secret] = $this->admin();
        $sid = $this->login($admin);
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $g = new Google2FA;
        $wrong = $g->oathTotp($secret, $g->getTimestamp() + 10);
        for ($i = 0; $i < 7; $i++) {
            [$response, $sid] = $this->http('POST', '/admin/totp', ['code' => $wrong], $sid);
            $response->assertSessionHasErrors('code');
        }
        $this->assertSame(5, AuthEvent::where('event', 'TOTP_FAILURE')->count());
        $this->assertSame(1, AuthEvent::where('event', 'LOCKOUT_TRIGGERED')->count());
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_FAILURE')->count());
        $this->travel(61)->seconds();
        $code = $g->getCurrentOtp($secret);
        [$response, $verified] = $this->http('POST', '/admin/totp', ['code' => $code], $sid);
        $response->assertRedirect('/admin');
        $this->assertNotSame($sid, $verified);
        $this->assertSame(1, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $row = AuthEvent::where('event', 'LOGIN_SUCCESS')->sole();
        $this->assertSame('success', $row->outcome);
        $this->assertSame('admin', $row->actor_type);
        $this->assertSame($admin->id, $row->actor_id);
        $this->assertSame('127.0.0.1', $row->ip);
        $this->assertNotNull($row->occurred_at);
        $this->http('GET', '/admin/auth-events', [], $verified)[0]->assertOk();
        $newPassword = 'Changed-only!Password481';
        $this->http('PATCH', '/admin/password', ['current_password' => self::PW, 'password' => $newPassword, 'password_confirmation' => $newPassword], $verified)[0]->assertRedirect('/admin/login');
        $this->assertSame(3, AuthEvent::where('event', 'SESSION_ROTATED')->count());
        $this->assertDatabaseHas('auth_events', ['event' => 'SESSION_ROTATED', 'context' => '{"scope":"password_update"}']);
        $this->assertClean([self::PW, $newPassword, $secret, $wrong, $code, $sid, $verified]);
    }

    public function test_enrol_recovery_and_reset_have_distinct_rows_and_no_secrets(): void
    {
        [$admin] = $this->admin(false);
        $sid = $this->login($admin);
        [$response, $sid] = $this->http('GET', '/admin/totp/enrol', [], $sid);
        $response->assertOk();
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $code = (new Google2FA)->getCurrentOtp($secret);
        [$response, $sid] = $this->http('POST', '/admin/totp/enrol', ['code' => $code], $sid);
        $response->assertRedirect('/admin/totp/enrol/recovery');
        $codes = json_decode(Crypt::decryptString(session(AdminTotpEnrolController::FRESH_CODES)), true);
        $this->assertSame(1, AuthEvent::where('event', 'ENROL_CONFIRMED')->count());
        $this->assertSame(1, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->http('POST', '/admin/logout', [], $sid)[0]->assertRedirect('/admin/login');
        $sid = $this->login($admin->fresh());
        $this->http('POST', '/admin/totp/recovery', ['recovery_code' => $codes[0]], $sid)[0]->assertRedirect('/admin');
        $user = User::factory()->create();
        $token = app(PasswordResetTokenIssuer::class)->issue($user);
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
        $this->assertSame(1, AuthEvent::where('event', 'RECOVERY_CODE_USED')->count());
        $this->assertSame(1, AuthEvent::where('event', 'PASSWORD_RESET_TOKEN_ISSUED')->count());
        $this->assertSame(2, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->assertSame(5, AuthEvent::where('event', 'SESSION_ROTATED')->count());
        $this->assertDatabaseHas('auth_events', ['event' => 'PASSWORD_RESET_TOKEN_ISSUED', 'actor_type' => 'web', 'actor_id' => $user->id, 'outcome' => 'success']);
        $this->assertClean([self::PW, $secret, $code, $token, ...$codes]);
    }

    public function test_secret_scanner_detects_raw_database_and_real_log_then_clean(): void
    {
        $secret = 'planted-audit-canary-59731';
        $id = DB::table('auth_events')->insertGetId(['event' => 'TOTP_FAILURE', 'outcome' => 'failure', 'context' => json_encode(['code' => $secret])]);
        $this->assertSame([$secret], $this->leaked([$secret]));
        DB::table('auth_events')->where('id', $id)->delete();
        $this->assertClean([$secret]);
        Log::warning($secret);
        $this->assertSame([$secret], $this->leaked([$secret]));
        file_put_contents($this->logPath, '');
        $this->assertClean([$secret]);
    }

    public function test_login_account_and_ip_lockout_deduplicate_per_real_limiter_key(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->http('POST', '/admin/login', ['email' => 'target@example.test', 'password' => self::PW], null, '192.0.2.'.($i + 1))[0]->assertStatus(302);
        }
        foreach (['192.0.2.50', '192.0.2.51', '192.0.2.52'] as $ip) {
            $this->http('POST', '/admin/login', ['email' => 'target@example.test', 'password' => self::PW], null, $ip)[0]->assertStatus(429);
        }
        $this->assertSame(1, AuthEvent::where('event', 'LOCKOUT_TRIGGERED')->count());
        for ($i = 0; $i < 10; $i++) {
            $this->http('POST', '/admin/login', ['email' => "other{$i}@example.test", 'password' => self::PW], null, '192.0.2.99')[0]->assertStatus(302);
        }
        for ($i = 10; $i < 13; $i++) {
            $this->http('POST', '/admin/login', ['email' => "other{$i}@example.test", 'password' => self::PW], null, '192.0.2.99')[0]->assertStatus(429);
        }
        $this->assertSame(2, AuthEvent::where('event', 'LOCKOUT_TRIGGERED')->count());
        $this->assertSame(['account', 'ip'], AuthEvent::where('event', 'LOCKOUT_TRIGGERED')->get()->map(fn ($row) => $row->context['scope'])->all());
        $this->travel(61)->seconds();
        for ($i = 0; $i < 12; $i++) {
            $this->http('POST', '/admin/login', ['email' => 'target@example.test', 'password' => self::PW]);
        }
        $this->assertSame(3, AuthEvent::where('event', 'LOCKOUT_TRIGGERED')->count());
    }

    public function test_review_denies_customer_and_unverified_admin_and_paginates_operator(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->get('/admin/auth-events')->assertRedirect('/admin/login');
        [$admin, $secret] = $this->admin();
        $sid = $this->login($admin);
        $this->http('GET', '/admin/auth-events', [], $sid)[0]->assertRedirect('/admin/totp');
        [, $sid] = $this->http('POST', '/admin/totp', ['code' => (new Google2FA)->getCurrentOtp($secret)], $sid);
        AuthEvent::query()->delete();
        for ($i = 0; $i < 26; $i++) {
            app(AuthEventRecorder::class)->recordLoginFailure($admin->id, '127.0.0.1');
        }
        $this->http('GET', '/admin/auth-events', [], $sid)[0]->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 26 && $events->count() === 25 && $events->first()->id > $events->last()->id);
        $this->http('GET', '/admin/auth-events?page=2', [], $sid)[0]->assertOk()->assertViewHas('events', fn ($events) => $events->count() === 1);
        $this->http('GET', '/admin/auth-events?outcome=success', [], $sid)[0]->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 0);
    }

    public function test_recorder_database_exception_is_safe_and_login_still_completes(): void
    {
        [$admin] = $this->admin(false);
        $secret = 'binding-private-token-684296';
        AuthEvent::creating(function () use ($secret): void { throw new \RuntimeException($secret); });
        try {
            $sid = $this->login($admin);
            $this->http('GET', '/admin', [], $sid)[0]->assertOk();
            $this->assertSame(0, AuthEvent::count());
            $logs = file_get_contents($this->logPath);
            $this->assertStringContainsString('CRITICAL', $logs);
            $this->assertStringContainsString('Auth event recorder failed', $logs);
            $this->assertStringContainsString('RuntimeException', $logs);
            $this->assertStringNotContainsString('trace', $logs);
            $this->assertClean([$secret, self::PW]);
        } finally {
            AuthEvent::getEventDispatcher()?->forget('eloquent.creating: '.AuthEvent::class);
        }
    }

    public function test_prune_keeps_recent_and_rejects_invalid_days(): void
    {
        $old = app(AuthEventRecorder::class)->recordLoginSuccess(1, null);
        $old->update(['occurred_at' => now()->subDays(31)]);
        $new = app(AuthEventRecorder::class)->recordLoginSuccess(1, null);
        foreach (['0', '-1', '1.5', 'abc', '1e2', '999999999999999999999'] as $days) {
            $this->artisan('auth-events:prune', ['--days' => $days])->assertFailed();
            $this->assertSame(2, AuthEvent::count());
        }
        $this->artisan('auth-events:prune')->assertSuccessful();
        $this->assertDatabaseMissing('auth_events', ['id' => $old->id]);
        $this->assertDatabaseHas('auth_events', ['id' => $new->id]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exceptionClasses')]
    public function test_vendor_exceptions_never_log_message_trace_or_codes(string $exception): void
    {
        [$admin, $secret] = $this->admin();
        $sid = $this->login($admin);
        $code = (new Google2FA)->getCurrentOtp($secret);
        $vendor = \Mockery::mock(Google2FA::class);
        $vendor->shouldReceive('verifyKeyNewer')->once()->andThrow(new $exception("private-vendor-message {$code} {$secret}"));
        $this->app->instance(Google2FA::class, $vendor);
        $this->http('POST', '/admin/totp', ['code' => $code], $sid)[0]->assertSessionHasErrors('code');
        $logs = file_get_contents($this->logPath);
        $this->assertStringContainsString('CRITICAL', $logs);
        $this->assertStringContainsString('"admin_id":'.$admin->id, $logs);
        $this->assertStringNotContainsString('private-vendor-message', $logs);
        $this->assertStringNotContainsString('trace', $logs);
        $this->assertSame(1, AuthEvent::where('event', 'TOTP_FAILURE')->count());
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->assertClean([$secret, $code, self::PW]);
    }

    public function test_failed_enrol_records_totp_failure_without_login_failure(): void
    {
        [$admin] = $this->admin(false);
        $sid = $this->login($admin);
        [, $sid] = $this->http('GET', '/admin/totp/enrol', [], $sid);
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $google = new Google2FA;
        $wrong = $google->oathTotp($secret, $google->getTimestamp() + 10);
        $this->http('POST', '/admin/totp/enrol', ['code' => $wrong], $sid)[0]->assertSessionHasErrors('code');
        $this->assertDatabaseHas('auth_events', ['event' => 'TOTP_FAILURE', 'actor_id' => $admin->id]);
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_FAILURE')->count());
        $this->assertClean([$secret, $wrong, self::PW]);
    }

    public function test_corrupt_enrol_secret_records_failure_without_leaking_ciphertext(): void
    {
        [$admin] = $this->admin(false);
        $sid = $this->login($admin);
        [, $sid] = $this->http('GET', '/admin/totp/enrol', [], $sid);
        $ciphertext = Crypt::encryptString('sensitive-corrupt-pending-secret');
        $this->app['session']->driver()->put(AdminTotpEnrolController::PENDING_SECRET, $ciphertext);
        $this->app['session']->driver()->save();
        $this->http('POST', '/admin/totp/enrol', ['code' => '123456'], $sid)[0]->assertSessionHasErrors('code');

        $this->assertDatabaseHas('auth_events', [
            'event' => 'TOTP_FAILURE',
            'actor_id' => $admin->id,
            'context' => json_encode(['reason' => 'corrupt_secret', 'guard' => 'admin']),
        ]);
        $this->assertClean([$ciphertext, 'sensitive-corrupt-pending-secret', '123456', self::PW]);
    }

    public function test_login_survives_both_audit_database_and_logger_failure(): void
            
    {
        [$admin] = $this->admin(false);
        AuthEvent::creating(function (): void { throw new \RuntimeException('audit unavailable'); });
        Log::shouldReceive('critical')->andThrow(new \RuntimeException('logger unavailable'));
        try {
            $sid = $this->login($admin);
            $this->http('GET', '/admin', [], $sid)[0]->assertOk();
        } finally {
            AuthEvent::getEventDispatcher()?->forget('eloquent.creating: '.AuthEvent::class);
        }
    }

    public function test_lockout_response_survives_cache_and_logger_failure(): void
    {
        $request = \Illuminate\Http\Request::create('/admin/login', 'POST', ['email' => 'lockout@example.test']);
        $limits = \Illuminate\Support\Facades\RateLimiter::limiter('login')($request);
        Cache::shouldReceive('add')->once()->andThrow(new \RuntimeException('cache unavailable'));
        Log::shouldReceive('warning')->once()->andThrow(new \RuntimeException('logger unavailable'));

        $response = ($limits[0]->responseCallback)($request, ['Retry-After' => 60]);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->headers->get('Retry-After'));
    }

    public function test_reset_token_survives_recorder_failure_without_leaking_exception_message(): void
    {
        $user = User::factory()->create();
        $secret = 'private-reset-exception-873951';
        $events = \Mockery::mock(AuthEventRecorder::class);
        $events->shouldReceive('recordPasswordResetTokenIssued')->once()->andThrow(new \RuntimeException($secret));

        $token = (new PasswordResetTokenIssuer($events))->issue($user);

        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
        $this->assertStringContainsString('Password reset audit failed', file_get_contents($this->logPath));
        $this->assertClean([$token, $secret]);
    }

    public function test_unhandled_totp_exception_trace_never_contains_code_or_secret(): void
    {
        [$admin, $secret] = $this->admin();
        $sid = $this->login($admin);
        $code = (new Google2FA)->getCurrentOtp($secret);
        $vendor = \Mockery::mock(Google2FA::class);
        $vendor->shouldReceive('verifyKeyNewer')->once()->andReturnUsing(function (): never {
            throw new \RuntimeException('unexpected vendor failure');
        });
        $this->app->instance(Google2FA::class, $vendor);

        $this->http('POST', '/admin/totp', ['code' => $code], $sid)[0]->assertSessionHasErrors('code');

        $this->assertStringContainsString('Admin TOTP operation failed', file_get_contents($this->logPath));
        $this->assertStringNotContainsString('unexpected vendor failure', file_get_contents($this->logPath));
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->assertNull(session(\App\Http\Middleware\EnsureAdminTotp::SESSION_KEY));
        $this->assertNull($admin->fresh()->totp_last_used_at);
        $this->assertClean([$secret, $code, self::PW]);
    }

    public function test_unhandled_recovery_exception_trace_never_contains_code(): void
    {
        [$admin] = $this->admin();
        $codes = app(TotpService::class)->generateRecoveryCodes();
        app(TotpService::class)->replaceRecoveryCodes($admin, $codes);
        $sid = $this->login($admin);
        \Illuminate\Support\Facades\Hash::shouldReceive('check')->andReturnUsing(function (): never {
            throw new \RuntimeException('unexpected hash failure');
        });

        $this->http('POST', '/admin/totp/recovery', ['recovery_code' => $codes[0]], $sid)[0]->assertSessionHasErrors('recovery_code');

        $this->assertStringContainsString('Admin TOTP recovery operation failed', file_get_contents($this->logPath));
        $this->assertStringNotContainsString('unexpected hash failure', file_get_contents($this->logPath));
        $this->assertSame(0, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->assertNull(session(\App\Http\Middleware\EnsureAdminTotp::SESSION_KEY));
        $this->assertSame(count($codes), $admin->fresh()->totpRecoveryCodes()->count());
        $this->assertNull($admin->fresh()->totp_last_used_at);
        $this->assertClean([$codes[0], TotpService::normalizeRecoveryCode($codes[0]), self::PW]);
    }

    public function test_unhandled_enrol_exception_trace_never_contains_secret_or_codes(): void
    {
        [$admin] = $this->admin(false);
        $sid = $this->login($admin);
        [, $sid] = $this->http('GET', '/admin/totp/enrol', [], $sid);
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $code = (new Google2FA)->getCurrentOtp($secret);

        $totp = \Mockery::mock(TotpService::class)->makePartial();
        $canaryCodes = ['ABCD-EFGH', 'JKLM-NPQR', 'STUV-WXYZ', '2345-6789', 'ABCD-2345', 'EFGH-6789', 'JKLM-2345', 'NPQR-6789'];
        $totp->shouldReceive('generateRecoveryCodes')->once()->andReturn($canaryCodes);
        $totp->shouldReceive('replaceRecoveryCodes')->once()->andReturnUsing(function (): never {
            throw new \RuntimeException('unexpected enrol database failure');
        });
        $this->app->instance(TotpService::class, $totp);
        $loginSuccesses = AuthEvent::where('event', 'LOGIN_SUCCESS')->count(); // password step only

        $this->http('POST', '/admin/totp/enrol', ['code' => $code], $sid)[0]->assertSessionHasErrors('code');

        $logs = file_get_contents($this->logPath);
        $this->assertStringContainsString('Admin TOTP enrolment transaction failed', $logs);
        $this->assertStringNotContainsString('unexpected enrol database failure', $logs);
        $this->assertSame($loginSuccesses, AuthEvent::where('event', 'LOGIN_SUCCESS')->count());
        $this->assertSame(0, AuthEvent::where('event', 'ENROL_CONFIRMED')->count());
        $this->assertNull(session(\App\Http\Middleware\EnsureAdminTotp::SESSION_KEY));
        $this->assertNull($admin->fresh()->totp_secret);
        $this->assertNull($admin->fresh()->totp_required_at);
        $this->assertNull($admin->fresh()->totp_last_used_at);
        $this->assertSame(0, $admin->fresh()->totpRecoveryCodes()->count());
        $this->assertClean([$secret, $code, self::PW, $canaryCodes[0], TotpService::normalizeRecoveryCode($canaryCodes[0])]);
    }

    public function test_unhandled_enrol_verification_exception_trace_never_contains_secret_or_code(): void
    {
        [$admin] = $this->admin(false);
        $sid = $this->login($admin);
        [, $sid] = $this->http('GET', '/admin/totp/enrol', [], $sid);
        $secret = Crypt::decryptString(session(AdminTotpEnrolController::PENDING_SECRET));
        $code = (new Google2FA)->getCurrentOtp($secret);

        $vendor = \Mockery::mock(Google2FA::class);
        $vendor->shouldReceive('verifyKeyNewer')->once()->andReturnUsing(function () use ($secret, $code): never {
            throw new \RuntimeException("unexpected enrol vendor failure {$secret} {$code}");
        });
        $this->app->instance(Google2FA::class, $vendor);

        $this->http('POST', '/admin/totp/enrol', ['code' => $code], $sid)[0]->assertSessionHasErrors('code');

        $logs = file_get_contents($this->logPath);
        $this->assertStringContainsString('Admin TOTP enrolment verification failed', $logs);
        $this->assertStringNotContainsString('unexpected enrol vendor failure', $logs);
        $this->assertSame(0, AuthEvent::where('event', 'ENROL_CONFIRMED')->count());
        $this->assertNull($admin->fresh()->totp_secret);
        $this->assertClean([$secret, $code, self::PW]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sessionFailureFlows')]
    public function test_session_marker_write_failure_does_not_record_completion(string $flow): void
    {
        [$admin, $secret] = $this->admin($flow !== 'enrol');
        $codes = app(TotpService::class)->generateRecoveryCodes();
        if ($flow === 'recovery') {
            app(TotpService::class)->replaceRecoveryCodes($admin, $codes);
        }
        $session = new class('audit_failure', new \Illuminate\Session\ArraySessionHandler(120)) extends \Illuminate\Session\Store
        {
            public function put($key, $value = null)
            {
                if ($key === \App\Http\Middleware\EnsureAdminTotp::SESSION_KEY) {
                    throw new \RuntimeException('session marker unavailable');
                }
                parent::put($key, $value);
            }
        };
        $session->start();
        if ($flow === 'enrol') {
            $session->put(AdminTotpEnrolController::PENDING_SECRET, Crypt::encryptString($secret));
        }
        $field = $flow === 'recovery' ? 'recovery_code' : 'code';
        $value = $flow === 'recovery' ? $codes[0] : (new Google2FA)->getCurrentOtp($secret);
        $request = \Illuminate\Http\Request::create('/admin/totp', 'POST', [$field => $value]);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $admin);
        $before = AuthEvent::count();

        try {
            if ($flow === 'enrol') {
                app(AdminTotpEnrolController::class)->store($request);
            } else {
                $controller = app(\App\Http\Controllers\AdminTotpController::class);
                $flow === 'recovery' ? $controller->recovery($request) : $controller->verify($request);
            }
            $this->fail('The injected session marker failure must be reached.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('session marker unavailable', $exception->getMessage());
        }

        $this->assertNull($session->get(\App\Http\Middleware\EnsureAdminTotp::SESSION_KEY));
        $this->assertSame(0, AuthEvent::whereIn('event', ['LOGIN_SUCCESS', 'ENROL_CONFIRMED', 'SESSION_ROTATED'])->count());
        // Recovery consumption precedes session completion and is still audited.
        $this->assertSame($before + ($flow === 'recovery' ? 1 : 0), AuthEvent::count());
        $this->assertSame($flow === 'recovery' ? 1 : 0, AuthEvent::where('event', 'RECOVERY_CODE_USED')->count());
        $this->assertNotNull($admin->fresh()->totp_last_used_at);
        $this->assertClean([$secret, $value, self::PW]);
    }

    public static function sessionFailureFlows(): array
    {
        return [['totp'], ['recovery'], ['enrol']];
    }

    public static function exceptionClasses(): array
    {
        return [
            [\PragmaRX\Google2FA\Exceptions\InvalidCharactersException::class],
            [\PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException::class],
            [\PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException::class],
        ];
    }
}
