<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'klien@example.test',
            'password' => Hash::make('Lama#12345'),
        ], $attrs));
    }

    public function test_request_page_renders_and_login_links_to_it(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Lupa kata sandi');
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
    }

    public function test_unknown_email_gets_same_response_as_known_email(): void
    {
        Notification::fake();
        $this->user();

        $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'klien@example.test']);
        $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'tidak-ada@example.test']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(session('success'), 'Jika email terdaftar, tautan pemulihan akan dikirim.');
        $known->assertSessionHas('success');
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_valid_token_resets_password_and_marks_has_password(): void
    {
        $user = $this->user(['has_password' => false]);
        $token = Password::broker('users')->createToken($user);
        $before = $user->fresh()->remember_token;

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('Baru#12345', $user->password));
        $this->assertTrue((bool) $user->has_password);
        $this->assertNotSame($before, $user->remember_token);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_token_is_single_use(): void
    {
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ];

        $this->post(route('password.update'), $payload);
        $this->from(route('password.reset', $token))
            ->post(route('password.update'), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_wrong_and_expired_tokens_are_rejected(): void
    {
        $user = $this->user();
        Password::broker('users')->createToken($user);

        $this->from(route('password.reset', 'palsu'))->post(route('password.update'), [
            'token' => 'palsu',
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ])->assertSessionHasErrors('email');

        $token = Password::broker('users')->createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes((int) config('auth.passwords.users.expire') + 5)]);

        $this->from(route('password.reset', $token))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Lama#12345', $user->fresh()->password));
    }

    public function test_reset_sends_confirmation_notification(): void
    {
        Notification::fake();
        $user = $this->user();
        $token = Password::broker('users')->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ]);

        Notification::assertSentTo($user, PasswordChanged::class);
    }

    public function test_request_is_rate_limited_per_email(): void
    {
        Notification::fake();
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'klien@example.test'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'klien@example.test'])->assertStatus(429);
    }

    public function test_broker_throttle_blocks_immediate_second_token(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    private function browser(string $method, string $uri, array $data = [], ?string $sid = null): array
    {
        $this->app['session']->driver()->flush();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->defaultCookies = $sid ? [config('session.cookie') => $sid] : [];
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());

        return [$response, $this->app['session']->driver()->getId()];
    }

    private function loginBrowser(User $user): string
    {
        [, $sid] = $this->browser('GET', '/login');
        [$response, $sid] = $this->browser('POST', '/login', [
            'email' => $user->email, 'password' => 'Lama#12345',
        ], $sid);
        $response->assertRedirect();
        [$response] = $this->browser('GET', '/akun', [], $sid);
        $response->assertOk();

        return $sid;
    }

    public function test_reset_logically_revokes_both_old_database_sessions_and_preserves_other_user(): void
    {
        config(['session.driver' => 'database']);
        Notification::fake();
        $user = $this->user();
        $other = $this->user(['email' => 'lain@example.test']);
        $oldOne = $this->loginBrowser($user);
        $oldTwo = $this->loginBrowser($user);
        $otherSession = $this->loginBrowser($other);
        $token = Password::broker('users')->createToken($user);

        [$response] = $this->browser('POST', '/reset-kata-sandi', [
            'token' => $token, 'email' => $user->email,
            'password' => 'Baru#12345', 'password_confirmation' => 'Baru#12345',
        ]);
        $response->assertRedirect('/login');
        // Revocation is functional and guard-scoped, not ambiguous bulk deletion by sessions.user_id.
        foreach ([$oldOne, $oldTwo] as $sid) {
            [$response] = $this->browser('GET', '/akun', [], $sid);
            $response->assertRedirect('/login');
            $this->assertGuest('web');
        }
        [$response] = $this->browser('GET', '/akun', [], $otherSession);
        $response->assertOk();
        $this->assertAuthenticatedAs($other, 'web');
        $this->assertDatabaseHas('sessions', ['id' => $otherSession]);
    }

    public function test_failed_reset_keeps_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->user();
        DB::table('sessions')->insert(['id' => 'sesi-aktif', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->post(route('password.update'), [
            'token' => 'salah',
            'email' => $user->email,
            'password' => 'Baru#12345',
            'password_confirmation' => 'Baru#12345',
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'sesi-aktif']);
    }
}
