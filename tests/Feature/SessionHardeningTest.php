<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $method, string $uri, array $data = [], ?string $sid = null): array
    {
        session()->flush();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());

        return [$response, session()->getId()];
    }

    private function unsetSuper(array &$bag, string $key): void
    {
        unset($bag[$key]);
    }

    public function test_customer_and_admin_password_sessions_rotate_and_old_ids_cannot_authenticate(): void
    {
        foreach (['web' => '/login', 'admin' => '/admin/login'] as $guard => $path) {
            $model = $guard === 'web' ? User::class : Admin::class;
            $account = $model::create(['name' => $guard, 'email' => $guard.'@example.test', 'password' => bcrypt('Test-only!123')]);
            [, $before] = $this->device('GET', $path);
            [$response, $after] = $this->device('POST', $path, ['email' => $account->email, 'password' => 'Test-only!123'], $before);
            $response->assertRedirect($guard === 'web' ? '/' : route('admin.dashboard'));
            $this->assertNotSame($before, $after);
            $this->assertSame('', session()->getHandler()->read($before));
            [$response] = $this->device('GET', $guard === 'web' ? '/akun' : '/admin', [], $before);
            $response->assertRedirect($path);
            [$response] = $this->device('GET', $guard === 'web' ? '/akun' : '/admin', [], $after);
            $response->assertOk();
        }
    }

    public function test_login_account_limit_spans_ips_and_normalizes_email(): void
    {
        foreach (['/login', '/admin/login'] as $path) {
            for ($i = 1; $i <= 10; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])
                    ->post($path, ['email' => 'Lock@example.test', 'password' => 'wrong'])->assertRedirect();
            }
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])
                ->post($path, ['email' => 'lock@example.test', 'password' => 'wrong']);
            $response->assertStatus(429);
            $seconds = (int) $response->headers->get('Retry-After');
            $this->assertGreaterThan(0, $seconds);
            $response->assertSee("Coba lagi dalam {$seconds} detik.");
        }
    }

    public function test_login_ip_limit_spans_accounts(): void
    {
        foreach (['/login', '/admin/login'] as $path) {
            for ($i = 1; $i <= 10; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                    ->post($path, ['email' => "account{$i}@example.test", 'password' => 'wrong'])->assertRedirect();
            }
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->post($path, ['email' => 'other@example.test', 'password' => 'wrong'])->assertStatus(429);
        }
    }

    public function test_production_defaults_hide_debug_and_secure_session_cookie(): void
    {
        // env() membaca $_SERVER/$_ENV lebih dulu; putenv() saja diabaikan.
        $keys = ['APP_ENV', 'APP_DEBUG', 'SESSION_SECURE_COOKIE'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null];
        }
        unset($_SERVER['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SECURE_COOKIE']);
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = 'true';
        try {
            $app = require base_path('config/app.php');
            $session = require base_path('config/session.php');
            $this->assertFalse($app['debug']);
            $this->assertTrue($session['secure']);
            $this->assertTrue($session['http_only']);
            $this->assertSame('lax', $session['same_site']);
        } finally {
            foreach ($saved as $key => [$server, $env]) {
                $server === null ? $this->unsetSuper($_SERVER, $key) : $_SERVER[$key] = $server;
                $env === null ? $this->unsetSuper($_ENV, $key) : $_ENV[$key] = $env;
            }
        }
    }

    public function test_production_error_response_hides_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/session-hardening-error', function () {
            throw new \RuntimeException('private-stack-marker-86c14287');
        });

        $this->get('/session-hardening-error')->assertStatus(500)
            ->assertDontSee('private-stack-marker-86c14287')
            ->assertDontSee('RuntimeException')
            ->assertDontSee(base_path());
    }

    public function test_malformed_email_requests_do_not_share_a_global_account_bucket_and_response_cookie_flags_are_set(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->postJson('/login', ['email' => ['malformed'], 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        config(['session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
        $cookies = $this->get('/login')->headers->getCookies();
        $sessionCookies = array_values(array_filter($cookies, fn ($cookie) => $cookie->getName() === config('session.cookie')));
        $this->assertCount(1, $sessionCookies);
        $cookie = $sessionCookies[0];
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
