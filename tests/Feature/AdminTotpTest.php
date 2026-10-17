<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminTotp;
use App\Models\Admin;
use App\Models\AdminTotpRecoveryCode;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTotpTest extends TestCase
{
    private const PW = 'Test-only!Password123';

    /** @var list<array{level:string,message:string,context:array}> */
    private array $logs = [];

    private string $defaultGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultGuard = config('auth.defaults.guard');
        Artisan::call('migrate', ['--force' => true]);
        Event::listen(MessageLogged::class, function (MessageLogged $e): void {
            $this->logs[] = ['level' => $e->level, 'message' => (string) $e->message, 'context' => $e->context];
        });
    }

    /** One simulated device: own cookie/session id, no leaked attributes from other "devices". */
    private function http(string $method, string $uri, array $data = [], ?string $sid = null): array
    {
        $this->app['session']->driver()->flush();
        Auth::forgetGuards();
        // auth:admin calls Auth::shouldUse('admin'), which writes config('auth.defaults.guard').
        // In production config is rebuilt per request; here it persists, so restore the original.
        Auth::shouldUse($this->defaultGuard);
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());

        return [$response, $this->app['session']->driver()->getId()];
    }

    /** @return array{0:Admin,1:string,2:list<string>} */
    private function totpAdmin(string $email = 'totp@example.test'): array
    {
        $svc = app(TotpService::class);
        $secret = $svc->generateSecret();
        $admin = Admin::create(['name' => 'Protected', 'email' => $email, 'password' => self::PW]);
        $admin->forceFill(['totp_secret' => $secret, 'totp_required_at' => now()])->save();
        $codes = $svc->generateRecoveryCodes();
        $svc->replaceRecoveryCodes($admin, $codes);

        return [$admin->fresh(), $secret, $codes];
    }

    private function otp(string $secret, int $stepOffset = 0): string
    {
        $g = new Google2FA;

        return $g->oathTotp($secret, $g->getTimestamp() + $stepOffset);
    }

    /** Password-login a device; returns sid after login. */
    private function passwordLogin(Admin $admin, ?string &$sid0 = null): string
    {
        [, $sid0] = $this->http('GET', '/admin/login');
        [$res, $sid1] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW], $sid0);
        $res->assertRedirect('/admin/totp');

        return $sid1;
    }

    // ---- baseline ----

    public function test_test_app_key_supports_encrypted_eloquent_cast(): void
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $casts = ['secret' => 'encrypted'];
        };
        $model->secret = 'test-secret';
        $this->assertNotSame('test-secret', $model->getAttributes()['secret']);
        $this->assertSame('test-secret', $model->secret);
    }

    public function test_totp_schema_exists_on_memory_database(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertTrue(Schema::hasColumns('admins', ['totp_secret', 'totp_required_at', 'totp_last_used_at']));
        $this->assertTrue(Schema::hasTable('admin_totp_recovery_codes'));
        $this->assertFalse(Schema::hasColumn('admin_totp_recovery_codes', 'code_hash') && $this->hasUniqueOn('admin_totp_recovery_codes', 'code_hash'));
    }

    private function hasUniqueOn(string $table, string $col): bool
    {
        foreach (Schema::getIndexes($table) as $idx) {
            if ($idx['unique'] && $idx['columns'] === [$col]) {
                return true;
            }
        }

        return false;
    }

    public function test_legacy_admin_can_enter_console(): void
    {
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'legacy@example.test', 'password' => self::PW]);
        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    }

    public function test_legacy_admin_enters_via_real_login_without_code_page(): void
    {
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'legacy2@example.test', 'password' => self::PW]);
        [, $s0] = $this->http('GET', '/admin/login');
        [$res, $s1] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW], $s0);
        $res->assertRedirect('/admin');
        [$res] = $this->http('GET', '/admin', [], $s1);
        $res->assertOk();
    }

    public function test_totp_admin_without_second_factor_gets_no_console(): void
    {
        [$admin] = $this->totpAdmin();
        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect('/admin/totp');
        foreach (['/admin/orders', '/admin/reports/orders.pdf'] as $uri) {
            $this->actingAs($admin, 'admin')->get($uri)->assertRedirect('/admin/totp');
        }
        $this->actingAs($admin, 'admin')->patch('/admin/password', [])->assertRedirect('/admin/totp');
    }

    public function test_web_guard_session_gives_no_console_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_unverified_admin_can_still_log_out(): void
    {
        [$admin] = $this->totpAdmin();
        $sid = $this->passwordLogin($admin);
        [$res] = $this->http('POST', '/admin/logout', [], $sid);
        $res->assertRedirect('/admin/login');
    }

    // ---- full flow ----

    public function test_full_flow_rotates_session_id_twice_and_grants_console(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $sid1 = $this->passwordLogin($admin, $sid0);
        [$res] = $this->http('GET', '/admin', [], $sid1);
        $res->assertRedirect('/admin/totp');

        [$res, $sid2] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret)], $sid1);
        $res->assertRedirect('/admin');
        $this->assertCount(3, array_unique([$sid0, $sid1, $sid2]));

        [$res] = $this->http('GET', '/admin', [], $sid2);
        $res->assertOk();
        // old pre-verify session id must not carry console access
        [$res] = $this->http('GET', '/admin', [], $sid1);
        $res->assertRedirect();
        $this->assertNotSame(200, $res->getStatusCode());
    }

    public function test_first_verification_on_never_verified_admin_succeeds(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $this->assertNull($admin->totp_last_used_at);
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, $this->otp($secret));
        $this->assertTrue($out['ok']);
        $this->assertNotNull($admin->fresh()->totp_last_used_at);
    }

    public function test_wrong_code_is_rejected(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $bad = $this->otp($secret) === '000000' ? '000001' : '000000';
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, $bad);
        $this->assertFalse($out['ok']);
        $this->assertSame('invalid_code', $out['reason']);
    }

    public function test_same_code_cannot_be_used_twice(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $code = $this->otp($secret);
        $svc = app(TotpService::class);
        $this->assertTrue($svc->verifyAndAdvance($admin->id, $code)['ok']);
        $second = $svc->verifyAndAdvance($admin->id, $code);
        $this->assertFalse($second['ok']);
        // Sequential replay is rejected by verifyKeyNewer before compare-and-swap is reached.
        $this->assertSame('invalid_code', $second['reason']);
    }

    public function test_two_full_verifications_with_newer_codes_both_succeed(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $svc = app(TotpService::class);
        $first = $svc->verifyAndAdvance($admin->id, $this->otp($secret, -1));
        $this->assertTrue($first['ok']);
        $second = $svc->verifyAndAdvance($admin->id, $this->otp($secret));
        $this->assertTrue($second['ok']);
        $this->assertSame('verified', $second['reason']);
        $this->assertGreaterThan($first['timestamp'], $second['timestamp']);
        $this->assertSame($second['timestamp'], (int) $admin->fresh()->totp_last_used_at);
    }

    // ---- ccb230ef: verification boundaries ----

    public function test_code_three_steps_ahead_is_rejected_and_marker_unchanged(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        // Code minted for a future timestamp; server clock untouched.
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, $this->otp($secret, 3));
        $this->assertFalse($out['ok']);
        $this->assertSame('invalid_code', $out['reason']);
        $this->assertNull(DB::table('admins')->where('id', $admin->id)->value('totp_last_used_at'));
    }

    public function test_raw_db_marker_equals_vendor_timestamp_integer(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $code = $this->otp($secret);
        $expected = (new Google2FA)->verifyKeyNewer($secret, $code, 0, 1);
        $this->assertIsInt($expected);
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, $code);
        $this->assertTrue($out['ok']);
        $raw = DB::table('admins')->where('id', $admin->id)->value('totp_last_used_at');
        $this->assertSame($expected, (int) $raw);
        $this->assertSame((string) $expected, (string) $raw);
        $this->assertSame($expected, $out['timestamp']);
    }

    public function test_lockout_message_states_remaining_seconds_as_number(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $sid = $this->passwordLogin($admin);
        $bad = $this->otp($secret) === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            [, $sid] = $this->http('POST', '/admin/totp', ['code' => $bad], $sid);
        }
        [$res] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret)], $sid);
        $res->assertSessionHasErrors('code');
        $message = session('errors')->first('code');
        $this->assertMatchesRegularExpression('/Coba lagi dalam (\d+) detik\./', $message);
        preg_match('/(\d+) detik/', $message, $m);
        $this->assertGreaterThanOrEqual(1, (int) $m[1]);
        $this->assertLessThanOrEqual(60, (int) $m[1]);
        // Locked out: even the valid code did not advance the marker.
        $this->assertNull(DB::table('admins')->where('id', $admin->id)->value('totp_last_used_at'));
    }

    public function test_compare_and_swap_second_call_with_same_old_returns_zero(): void
    {
        [$admin] = $this->totpAdmin();
        $svc = app(TotpService::class);
        $this->assertSame(1, $svc->compareAndSwapTimestamp($admin->id, null, 100));
        $this->assertSame(0, $svc->compareAndSwapTimestamp($admin->id, null, 101));
        $this->assertSame(100, (int) $admin->fresh()->totp_last_used_at);
    }

    public function test_concurrent_winner_between_read_and_swap_makes_loser_replayed(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $done = false;
        DB::listen(function ($q) use (&$done, $admin): void {
            if (! $done && str_starts_with(strtolower($q->sql), 'select') && str_contains($q->sql, '"admins"')) {
                $done = true; // another request wins right after our read
                DB::table('admins')->where('id', $admin->id)->update(['totp_last_used_at' => 999999999999]);
            }
        });
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, $this->otp($secret));
        $this->assertFalse($out['ok']);
        $this->assertSame('replayed', $out['reason']);
    }

    public function test_stale_model_in_memory_cannot_replay(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $stale = Admin::find($admin->id); // loaded before first use, last_used = null
        $code = $this->otp($secret);
        $svc = app(TotpService::class);
        $this->assertTrue($svc->verifyAndAdvance($stale->id, $code)['ok']);
        $this->assertNull($stale->totp_last_used_at);
        $this->assertFalse($svc->verifyAndAdvance($stale->id, $code)['ok']);
    }

    // ---- recovery codes ----

    public function test_recovery_code_works_once_then_rejected(): void
    {
        [$admin, , $codes] = $this->totpAdmin();
        $svc = app(TotpService::class);
        $this->assertTrue($svc->consumeRecoveryCode($admin->id, $codes[0])['ok']);
        $this->assertFalse($svc->consumeRecoveryCode($admin->id, $codes[0])['ok']);
        $this->assertSame(7, AdminTotpRecoveryCode::where('admin_id', $admin->id)->count());
    }

    public function test_recovery_code_consumed_concurrently_is_rejected(): void
    {
        [$admin, , $codes] = $this->totpAdmin();
        $done = false;
        DB::listen(function ($q) use (&$done): void {
            if (! $done && str_starts_with(strtolower($q->sql), 'select') && str_contains($q->sql, 'admin_totp_recovery_codes')) {
                $done = true; // concurrent consumer deletes every row after we read them
                DB::table('admin_totp_recovery_codes')->delete();
            }
        });
        $this->assertFalse(app(TotpService::class)->consumeRecoveryCode($admin->id, $codes[0])['ok']);
    }

    public function test_recovery_code_allows_console_entry_anti_lockout(): void
    {
        [$admin, , $codes] = $this->totpAdmin();
        $sid1 = $this->passwordLogin($admin);
        [$res, $sid2] = $this->http('POST', '/admin/totp/recovery', ['recovery_code' => $codes[2]], $sid1);
        $res->assertRedirect('/admin');
        [$res] = $this->http('GET', '/admin', [], $sid2);
        $res->assertOk();
    }

    // ---- storage ----

    public function test_secret_is_ciphertext_and_recovery_codes_are_hashed_in_db(): void
    {
        [$admin, $secret, $codes] = $this->totpAdmin();
        $raw = DB::table('admins')->where('id', $admin->id)->value('totp_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, decrypt($raw, false));
        $hashes = DB::table('admin_totp_recovery_codes')->where('admin_id', $admin->id)->pluck('code_hash');
        $this->assertCount(8, $hashes);
        foreach ($codes as $i => $code) {
            $this->assertNotSame($code, $hashes[$i]);
            $this->assertTrue(Hash::check(TotpService::normalizeRecoveryCode($code), $hashes[$i]));
        }
    }

    public function test_password_reset_and_totp_recovery_tokens_are_not_interchangeable(): void
    {
        [$admin, , $codes] = $this->totpAdmin();
        $user = User::factory()->create(['email' => $admin->email]);
        $broker = \Illuminate\Support\Facades\Password::broker('users');
        $reset = $broker->createToken($user);

        $this->assertTrue($broker->tokenExists($user, $reset));
        $this->assertFalse(app(TotpService::class)->consumeRecoveryCode($admin->id, $reset)['ok']);
        $this->assertFalse($broker->tokenExists($user, $codes[0]));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertSame(8, DB::table('admin_totp_recovery_codes')->where('admin_id', $admin->id)->count());
        $this->assertTrue(app(TotpService::class)->consumeRecoveryCode($admin->id, $codes[0])['ok']);
        $this->assertTrue($broker->tokenExists($user, $reset));
    }

    // ---- broken secret / legacy flag ----

    public function test_corrupt_secret_raises_operational_alarm_not_wrong_code(): void
    {
        [$admin] = $this->totpAdmin();
        DB::table('admins')->where('id', $admin->id)->update(['totp_secret' => 'garbage-not-encrypted']);
        $out = app(TotpService::class)->verifyAndAdvance($admin->id, '123456');
        $this->assertSame('broken_secret', $out['reason']);
        $crit = array_values(array_filter($this->logs, fn ($l) => $l['level'] === 'critical'));
        $this->assertCount(1, $crit);
        $this->assertSame(['admin_id', 'exception'], array_keys($crit[0]['context']));

        $sid1 = $this->passwordLogin($admin);
        [$res] = $this->http('POST', '/admin/totp', ['code' => '123456'], $sid1);
        $res->assertSessionHasErrors('code');
        $msg = session('errors')->first('code');
        $this->assertStringContainsString('tidak tersedia', $msg);
        $this->assertStringNotContainsString('salah', strtolower($msg));
    }

    public function test_flag_without_secret_redirects_to_enrol_and_alarms(): void
    {
        $admin = Admin::create(['name' => 'Flag', 'email' => 'flag@example.test', 'password' => self::PW]);
        $admin->forceFill(['totp_required_at' => now()])->save();
        $this->assertFalse($admin->fresh()->requiresTotp());
        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.totp.enrol'));
        $this->assertNotEmpty(array_filter($this->logs, fn ($l) => $l['level'] === 'critical'));
    }

    // ---- K6: monotonic marker / two devices ----

    public function test_verifying_on_device_b_throws_device_a_back_to_challenge(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $a1 = $this->passwordLogin($admin);
        $code = $this->otp($secret);
        [, $aSid] = $this->http('POST', '/admin/totp', ['code' => $code], $a1);
        [$res] = $this->http('GET', '/admin', [], $aSid);
        $res->assertOk();

        $b1 = $this->passwordLogin($admin);
        // same 30s code cannot be reused on device B
        [$res] = $this->http('POST', '/admin/totp', ['code' => $code], $b1);
        $res->assertSessionHasErrors('code');

        // device B uses the next window's code -> marker moves forward
        [$res, $bSid] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret, 1)], $b1);
        $res->assertRedirect('/admin');
        [$res] = $this->http('GET', '/admin', [], $bSid);
        $res->assertOk();

        [$res] = $this->http('GET', '/admin', [], $aSid);
        $res->assertRedirect('/admin/totp');
    }

    // ---- password change ----

    public function test_password_change_voids_totp_state_session_and_remember_token(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $sid1 = $this->passwordLogin($admin);
        [, $sid2] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret)], $sid1);
        $oldRemember = $admin->fresh()->remember_token;
        $new = 'New-only!Password456';

        [$res] = $this->http('PATCH', '/admin/password', [
            'current_password' => self::PW, 'password' => $new, 'password_confirmation' => $new,
        ], $sid2);
        $res->assertRedirect('/admin/login');

        $fresh = $admin->fresh();
        $this->assertNull($fresh->totp_last_used_at);
        $this->assertSame(0, AdminTotpRecoveryCode::where('admin_id', $admin->id)->count());
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertTrue(Hash::check($new, $fresh->password));
        [$res] = $this->http('GET', '/admin', [], $sid2);
        $res->assertRedirect('/admin/login');
    }

    public function test_admin_password_change_does_not_kill_web_customer_session(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $user = User::factory()->create();

        // ONE browser session carrying both guards.
        [, $s0] = $this->http('GET', '/login');
        [$res, $s1] = $this->http('POST', '/login', ['email' => $user->email, 'password' => 'password'], $s0);
        $res->assertRedirect();
        [$res] = $this->http('GET', '/akun', [], $s1);
        $res->assertOk();

        [$res, $s2] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW], $s1);
        $res->assertRedirect('/admin/totp');
        [$res, $s3] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret)], $s2);
        $res->assertRedirect('/admin');
        [$res] = $this->http('GET', '/akun', [], $s3);
        $res->assertOk();

        $new = 'New-only!Password456';
        [$res, $s4] = $this->http('PATCH', '/admin/password', [
            'current_password' => self::PW, 'password' => $new, 'password_confirmation' => $new,
        ], $s3);
        $res->assertRedirect('/admin/login');

        // Admin guard is dead, web guard is still authenticated in the same session.
        [$res] = $this->http('GET', '/admin', [], $s4);
        $res->assertRedirect('/admin/login');
        [$res] = $this->http('GET', '/akun', [], $s4);
        $res->assertOk();
        $this->assertSame($user->id, Auth::guard('web')->id());
    }

    public function test_legacy_password_change_keeps_session_and_rotates_remember_token(): void
    {
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'legacy3@example.test', 'password' => self::PW]);
        $before = $admin->fresh()->remember_token;
        $new = 'New-only!Password456';
        $this->actingAs($admin, 'admin')->patch('/admin/password', [
            'current_password' => self::PW, 'password' => $new, 'password_confirmation' => $new,
        ])->assertSessionHas('success');
        $this->assertNotSame($before, $admin->fresh()->remember_token);
    }

    // ---- remember me OFF ----

    public function test_admin_login_ignores_remember_and_form_has_no_checkbox(): void
    {
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'legacy4@example.test', 'password' => self::PW]);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PW, 'remember' => '1']);
        $this->assertNull($admin->fresh()->remember_token);
        $this->get('/admin/login')->assertDontSee('Ingat saya');
    }

    // ---- rate limit ----

    public function test_verify_rate_limit_is_per_admin_and_ip(): void
    {
        [$a] = $this->totpAdmin('a@example.test');
        [$b] = $this->totpAdmin('b@example.test');
        $sa = $this->passwordLogin($a);
        $bad = '000000';
        $keyA = 'admin-totp:'.$a->id.'|127.0.0.1';
        $keyB = 'admin-totp:'.$b->id.'|127.0.0.1';
        for ($i = 1; $i <= 5; $i++) {
            [$res, $sid] = $this->http('POST', '/admin/totp', ['code' => $bad], $sa);
            $this->assertSame($i, RateLimiter::attempts($keyA));
        }
        [$res, $sid] = $this->http('POST', '/admin/totp', ['code' => $bad], $sa);
        $this->assertSame(5, RateLimiter::attempts($keyA)); // blocked request is not counted again
        $res->assertSessionHasErrors('code');
        $this->assertStringContainsString('detik', session('errors')->first('code'));

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);
        [$res] = $this->http('POST', '/admin/totp', ['code' => $bad], $sa);
        $res->assertSessionHasErrors('code');
        $this->assertStringNotContainsString('detik', session('errors')->first('code'));
        $this->assertSame(1, RateLimiter::attempts('admin-totp:'.$a->id.'|10.0.0.9'));
        $this->assertSame(5, RateLimiter::attempts($keyA));
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $sb = $this->passwordLogin($b);
        [$res] = $this->http('POST', '/admin/totp', ['code' => $bad], $sb);
        $this->assertSame(1, RateLimiter::attempts($keyB));
        $this->assertStringNotContainsString('detik', session('errors')->first('code'));
    }

    // ---- CLI ----

    public function test_cli_provisions_one_admin_and_prints_secrets_once(): void
    {
        $code = Artisan::call('admin:totp-provision', ['email' => 'owner@example.test', '--name' => 'Owner']);
        $out = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('otpauth://', $out);
        $this->assertSame(1, preg_match('/^Password: (\S+)$/m', $out, $pw));
        $this->assertSame(1, preg_match('/^Kunci setup: (\S+)$/m', $out, $key));
        $this->assertSame(8, preg_match_all('/^\s+([A-Z2-9]{5}-[A-Z2-9]{5})$/m', $out, $codes));

        $admin = Admin::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($admin->requiresTotp());
        $this->assertTrue(Hash::check($pw[1], $admin->password));
        $this->assertSame($key[1], $admin->totp_secret);
        $this->assertSame(8, $admin->totpRecoveryCodes()->count());

        $second = Artisan::call('admin:totp-provision', ['email' => 'owner@example.test']);
        $out2 = Artisan::output();
        $this->assertNotSame(0, $second);
        foreach ([$pw[1], $key[1], 'otpauth://', $codes[1][0]] as $secretText) {
            $this->assertStringNotContainsString($secretText, $out2);
        }
        $this->assertSame(1, Admin::where('email', 'owner@example.test')->count());

        $blob = json_encode($this->logs);
        foreach ([$pw[1], $key[1], ...$codes[1]] as $secretText) {
            $this->assertStringNotContainsString($secretText, $blob);
        }
    }

    // ---- log leak ----

    public function test_no_secret_password_or_code_in_logs(): void
    {
        [$admin, $secret, $codes] = $this->totpAdmin();
        $sid1 = $this->passwordLogin($admin);
        $good = $this->otp($secret);
        $this->http('POST', '/admin/totp', ['code' => '000000'], $sid1);
        $this->http('POST', '/admin/totp', ['code' => $good], $sid1);
        $this->http('POST', '/admin/totp/recovery', ['recovery_code' => $codes[0]], $sid1);
        DB::table('admins')->where('id', $admin->id)->update(['totp_secret' => 'garbage']);
        app(TotpService::class)->verifyAndAdvance($admin->id, $good);

        $blob = json_encode($this->logs);
        foreach ([$secret, $good, self::PW, ...$codes, ...array_map(TotpService::normalizeRecoveryCode(...), $codes)] as $needle) {
            $this->assertStringNotContainsString($needle, $blob);
        }
    }

    public function test_two_independent_processes_consuming_same_recovery_code(): void
    {
        $tmpDb = tempnam(sys_get_temp_dir(), 'zafira_sqlite_');
        unlink($tmpDb);
        $tmpDb .= '.sqlite';
        touch($tmpDb);

        try {
            $pdo = new \PDO('sqlite:'.$tmpDb);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE admin_totp_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER, code_hash TEXT, created_at TEXT, updated_at TEXT)');
            $pdo->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, password TEXT, totp_secret TEXT, totp_required_at TEXT, totp_last_used_at INTEGER, remember_token TEXT, created_at TEXT, updated_at TEXT)');

            $hash = Hash::make('ABCDE12345');
            $pdo->prepare('INSERT INTO admins (id, name, email, password, totp_required_at) VALUES (1, "Test", "t@example.test", "pw", "2026-10-17 00:00:00")')->execute();
            $pdo->prepare('INSERT INTO admin_totp_recovery_codes (id, admin_id, code_hash) VALUES (1, 1, ?)')->execute([$hash]);

            $worker = <<<'PHP'
$db = $argv[1];
$pdo = new PDO('sqlite:'.$db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
$normalized = 'ABCDE12345';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, code_hash FROM admin_totp_recovery_codes WHERE admin_id = 1');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $found = false;
    foreach ($rows as $r) {
        if (password_verify($normalized, $r['code_hash'])) {
            $del = $pdo->prepare('DELETE FROM admin_totp_recovery_codes WHERE id = ?');
            $del->execute([$r['id']]);
            if ($del->rowCount() === 1) {
                $found = true;
                break;
            }
        }
    }
    $pdo->commit();
    echo $found ? "WIN\n" : "FAIL\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "BUSY_OR_ERROR:".$e->getMessage()."\n";
}
PHP;
            $script = tempnam(sys_get_temp_dir(), 'worker_').'.php';
            file_put_contents($script, "<?php\n".$worker);

            $p1 = popen("php {$script} {$tmpDb}", 'r');
            $p2 = popen("php {$script} {$tmpDb}", 'r');
            $out1 = trim((string) fgets($p1));
            $out2 = trim((string) fgets($p2));
            pclose($p1);
            pclose($p2);
            unlink($script);

            $wins = ($out1 === 'WIN' ? 1 : 0) + ($out2 === 'WIN' ? 1 : 0);
            $this->assertLessThanOrEqual(1, $wins);
            $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM admin_totp_recovery_codes')->fetchColumn());
        } finally {
            if (file_exists($tmpDb)) {
                unlink($tmpDb);
            }
        }
    }

    // ---- 11846b4c: Enrolment Self-Service & TotpRequire ----

    public function test_legacy_admin_sees_enrolment_banner_on_dashboard(): void
    {
        $admin = Admin::create(['name' => 'Legacy', 'email' => 'leg@example.test', 'password' => self::PW]);
        $this->actingAs($admin, 'admin')->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.totp.enrol'))
            ->assertSee('Aktifkan Verifikasi Dua Langkah');
    }

    public function test_enrolled_admin_does_not_see_enrolment_banner_on_dashboard(): void
    {
        [$admin, $secret] = $this->totpAdmin();
        $sid1 = $this->passwordLogin($admin);
        [, $sid2] = $this->http('POST', '/admin/totp', ['code' => $this->otp($secret)], $sid1);
        [$res] = $this->http('GET', '/admin', [], $sid2);
        $res->assertOk()->assertDontSee(route('admin.totp.enrol'));
    }

    public function test_admin_with_totp_cannot_re_enrol_via_get_or_post(): void
    {
        [$admin] = $this->totpAdmin();
        $this->actingAs($admin, 'admin')->get('/admin/totp/enrol')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin, 'admin')->post('/admin/totp/enrol', ['code' => '123456'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_enrol_page_generates_new_secret_and_uri_in_session(): void
    {
        $admin = Admin::create(['name' => 'Enrollee', 'email' => 'enrollee@example.test', 'password' => self::PW]);
        $res = $this->actingAs($admin, 'admin')->get('/admin/totp/enrol');
        $res->assertOk();
        $res->assertSessionHas('totp_enrol_secret');
        $res->assertSessionHas('totp_enrol_uri');
        $pendingCiphertext = session('totp_enrol_secret');
        $secret = Crypt::decryptString($pendingCiphertext);
        $this->assertNotEmpty($secret);
        $this->assertNotSame($secret, $pendingCiphertext);
        $res->assertSee($secret);
        $this->assertNull($admin->fresh()->totp_secret);
        $this->assertNull($admin->fresh()->totp_required_at);
    }

    public function test_enrol_post_invalid_code_fails_and_keeps_pending_secret(): void
    {
        $admin = Admin::create(['name' => 'Enrollee', 'email' => 'enrollee2@example.test', 'password' => self::PW]);
        $svc = app(TotpService::class);
        $secret = $svc->generateSecret();
        $res = $this->actingAs($admin, 'admin')
            ->withSession(['totp_enrol_secret' => Crypt::encryptString($secret)])
            ->post('/admin/totp/enrol', ['code' => '000000']);
        $res->assertSessionHasErrors('code');
        $this->assertNull($admin->fresh()->totp_secret);
        $this->assertNull($admin->fresh()->totp_required_at);
    }

    public function test_enrol_post_success_saves_ciphertext_hash_and_shows_codes_once(): void
    {
        $admin = Admin::create(['name' => 'Enrollee', 'email' => 'enrollee3@example.test', 'password' => self::PW]);
        [, $sid0] = $this->http('GET', '/admin/login');
        [, $sid1] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW], $sid0);
        [$enrolGet, $sid1] = $this->http('GET', '/admin/totp/enrol', [], $sid1);
        $enrolGet->assertOk();
        $secret = Crypt::decryptString((string) session('totp_enrol_secret'));
        $code = $this->otp($secret);

        [$res, $sid2] = $this->http('POST', '/admin/totp/enrol', ['code' => $code], $sid1);

        $res->assertRedirect(route('admin.totp.enrol.recovery'));

        $fresh = $admin->fresh();
        $this->assertNotNull($fresh->totp_required_at);
        $this->assertTrue($fresh->requiresTotp());
        $this->assertSame($secret, $fresh->totp_secret);

        // Ciphertext verification via direct DB query
        $rawSecret = DB::table('admins')->where('id', $admin->id)->value('totp_secret');
        $this->assertNotSame($secret, $rawSecret);
        $this->assertStringStartsWith('eyJpdiI6', $rawSecret);

        // 8 recovery codes hashed with bcrypt in DB
        $storedCodes = DB::table('admin_totp_recovery_codes')->where('admin_id', $admin->id)->get();
        $this->assertCount(8, $storedCodes);
        foreach ($storedCodes as $sc) {
            $this->assertStringStartsWith('$2y$', $sc->code_hash);
            $this->assertSame(60, strlen($sc->code_hash));
        }

        // View recovery codes confirmation: shown exactly ONCE
        [$confirm, $sid2] = $this->http('GET', '/admin/totp/enrol/recovery', [], $sid2);
        $confirm->assertOk();
        $confirm->assertSee('Simpan Kode Cadangan');

        // Subsequent GET to recovery must redirect away and NOT show codes again
        [$confirm2, $sid2] = $this->http('GET', '/admin/totp/enrol/recovery', [], $sid2);
        $confirm2->assertRedirect(route('admin.dashboard'));

        // Subsequent GET to enrol must redirect away and NOT show codes
        [$enrol2, $sid2] = $this->http('GET', '/admin/totp/enrol', [], $sid2);
        $enrol2->assertRedirect(route('admin.dashboard'));

        // Console is accessible immediately in the rotated session
        [$dash] = $this->http('GET', '/admin', [], $sid2);
        $dash->assertOk();
    }

    public function test_stale_enrol_request_cannot_replace_an_existing_secret(): void
    {
        $admin = Admin::create(['name' => 'Race', 'email' => 'race@example.test', 'password' => self::PW]);
        $pending = app(TotpService::class)->generateSecret();
        $existing = app(TotpService::class)->generateSecret();
        $this->actingAs($admin, 'admin')->withSession([
            'totp_enrol_secret' => Crypt::encryptString($pending),
        ]);

        // Another enrolment wins after this request's authenticated model was loaded.
        DB::table('admins')->where('id', $admin->id)->update([
            'totp_secret' => Crypt::encryptString($existing),
            'totp_required_at' => now(),
        ]);
        $this->post('/admin/totp/enrol', ['code' => $this->otp($pending)])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($existing, $admin->fresh()->totp_secret);
        $this->assertSame(0, $admin->totpRecoveryCodes()->count());
        $this->assertNull(session('totp_enrol_codes'));
    }

    public function test_enrol_with_corrupt_pending_secret_logs_critical(): void
    {
        $admin = Admin::create(['name' => 'CorruptEnrol', 'email' => 'corrupt-enrol@example.test', 'password' => self::PW]);
        $res = $this->actingAs($admin, 'admin')
            ->withSession(['totp_enrol_secret' => 'not-valid-ciphertext'])
            ->post('/admin/totp/enrol', ['code' => '123456']);
        $res->assertSessionHasErrors('code');
        $crit = array_values(array_filter($this->logs, fn ($l) => $l['level'] === 'critical'));
        $this->assertNotEmpty($crit);
        $this->assertArrayHasKey('admin_id', $crit[0]['context']);
    }

    public function test_provisioning_uri_method_generates_valid_otpauth_uri(): void
    {
        $svc = app(TotpService::class);
        $secret = $svc->generateSecret();
        $uri = $svc->provisioningUri('admin@zafira.test', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
    }

    public function test_admin_totp_require_command_refuses_when_admin_has_no_secret(): void
    {
        $a1 = Admin::create(['name' => 'NoSecret1', 'email' => 'nosecret1@example.test', 'password' => self::PW]);
        $this->artisan('admin:totp-require', ['--all' => true])
            ->expectsOutputToContain('nosecret1@example.test')
            ->expectsOutputToContain('Dibatalkan')
            ->assertExitCode(1);

        $this->assertNull($a1->fresh()->totp_required_at);
    }

    public function test_admin_totp_require_command_succeeds_when_all_admins_have_secrets(): void
    {
        [$a1] = $this->totpAdmin('a1@example.test');
        [$a2] = $this->totpAdmin('a2@example.test');
        DB::table('admins')->update(['totp_required_at' => null]);

        $this->artisan('admin:totp-require', ['--all' => true])
            ->expectsOutputToContain('Berhasil mewajibkan TOTP')
            ->assertExitCode(0);

        $this->assertNotNull($a1->fresh()->totp_required_at);
        $this->assertNotNull($a2->fresh()->totp_required_at);
    }
}
