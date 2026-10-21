<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\TotpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Regression: a TOTP code accepted once must be rejected when replayed from a
 * fresh session (logout -> login again) inside the same ±1 step window.
 */
class AdminTotpReplayTest extends TestCase
{
    private const PW = 'Test-only!Password123';

    private string $defaultGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultGuard = config('auth.defaults.guard');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** One simulated browser request with its own session cookie (same helper as AdminTotpTest). */
    private function http(string $method, string $uri, array $data = [], ?string $sid = null): array
    {
        $this->app['session']->driver()->flush();
        Auth::forgetGuards();
        Auth::shouldUse($this->defaultGuard);
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());

        return [$response, $this->app['session']->driver()->getId()];
    }

    private function passwordLogin(Admin $admin): string
    {
        [, $sid0] = $this->http('GET', '/admin/login');
        [$res, $sid1] = $this->http('POST', '/admin/login', ['email' => $admin->email, 'password' => self::PW], $sid0);
        $res->assertRedirect('/admin/totp');

        return $sid1;
    }

    public function test_same_code_replayed_after_logout_and_relogin_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 12:00:05', 'UTC'));
        // Google2FA uses time(), so bridge only its clock to Laravel's frozen clock.
        // Code generation, verification, transaction and CAS remain real.
        $g = new class extends Google2FA
        {
            public function getTimestamp()
            {
                return intdiv(now()->timestamp, 30);
            }
        };
        $this->app->instance(TotpService::class, new TotpService($g));

        $svc = app(TotpService::class);
        $secret = $svc->generateSecret();
        $admin = Admin::create(['name' => 'Replay', 'email' => 'replay@example.test', 'password' => self::PW]);
        $admin->forceFill(['totp_secret' => $secret, 'totp_required_at' => now()])->save();

        $code = $g->oathTotp($secret, $g->getTimestamp());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Session 1: password + TOTP -> console.
        $sid1 = $this->passwordLogin($admin);
        [$res, $sidOk] = $this->http('POST', '/admin/totp', ['code' => $code], $sid1);
        $res->assertRedirect('/admin');
        [$res] = $this->http('GET', '/admin', [], $sidOk);
        $res->assertOk();
        $markerAfterFirst = DB::table('admins')->where('id', $admin->id)->value('totp_last_used_at');
        $this->assertNotNull($markerAfterFirst);

        // Logout -> brand-new session.
        [$res] = $this->http('POST', '/admin/logout', [], $sidOk);
        $res->assertRedirect('/admin/login');

        // Session 2: password again, then replay the SAME code.
        $sid2 = $this->passwordLogin($admin);
        $this->assertNotSame($sid1, $sid2);
        $this->assertSame((int) $markerAfterFirst, $g->getTimestamp());
        $this->assertTrue($g->verifyKey($secret, $code, 1));
        [$res, $sidReplay] = $this->http('POST', '/admin/totp', ['code' => $code], $sid2);
        $res->assertSessionHasErrors('code');
        $this->assertStringContainsString('sudah dipakai', session('errors')->first('code'));

        // No console access and marker unchanged.
        [$res] = $this->http('GET', '/admin', [], $sidReplay);
        $res->assertRedirect('/admin/totp');
        $this->assertSame(
            (int) $markerAfterFirst,
            (int) DB::table('admins')->where('id', $admin->id)->value('totp_last_used_at'),
        );
    }
}
