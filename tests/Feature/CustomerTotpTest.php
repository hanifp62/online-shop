<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CustomerTotpTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_with_totp_waits_for_second_factor(): void
    {
        $user = User::factory()->create(['email' => 'totp@example.test', 'password' => Hash::make('password')]);
        $secret = app(TotpService::class)->generateSecret();
        $user->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now()])->save();

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login.totp'));
        $this->assertGuest();

        $g = new Google2FA;
        $this->post(route('login.totp.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('login.totp.verify'), ['code' => $g->oathTotp($secret, $g->getTimestamp())])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_recovery_code_is_consumed_once(): void
    {
        $user = User::factory()->create(['email' => 'recovery@example.test', 'password' => Hash::make('password')]);
        $secret = app(TotpService::class)->generateSecret();
        $user->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now()])->save();
        $code = 'ABCDE-23456';
        $user->totpRecoveryCodes()->create(['code_hash' => Hash::make(TotpService::normalizeRecoveryCode($code))]);

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('login.totp.verify'), ['recovery_code' => $code])->assertRedirect('/');
        $this->assertDatabaseCount('user_totp_recovery_codes', 0);
    }

    public function test_totp_can_be_enabled_and_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.totp.enrol'))->assertOk();
        $secret = $this->app['request']->session()->get('customer_totp_secret');
        $secret = Crypt::decryptString($secret);
        $g = new Google2FA;
        $code = $g->oathTotp($secret, $g->getTimestamp());

        $this->actingAs($user)->post(route('account.totp.store'), ['code' => $code])->assertRedirect(route('account.totp.recovery'));
        $this->assertTrue($user->fresh()->hasTotp());
        $this->actingAs($user->fresh())->get(route('account.show'))->assertOk()->assertSee('Sisa kode cadangan: 8');
        // Kode langkah yang sama ditolak (anti-replay); langkah berikutnya dalam jendela diterima.
        $this->actingAs($user->fresh())->delete(route('account.totp.destroy'), ['disable_code' => $code])->assertSessionHasErrors('disable_code');
        $disable = $g->oathTotp($secret, $g->getTimestamp() + 1);
        $this->actingAs($user->fresh())->delete(route('account.totp.destroy'), ['disable_code' => $disable])->assertRedirect(route('account.show'));
        $this->assertFalse($user->fresh()->hasTotp());
    }

    public function test_account_without_totp_logs_in_in_one_step(): void
    {
        $user = User::factory()->create(['email' => 'plain@example.test', 'password' => Hash::make('password')]);
        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_requires_pending_login(): void
    {
        $this->get(route('login.totp'))->assertRedirect(route('login'));
        $this->post(route('login.totp.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_recovery_codes_are_shown_only_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.totp.enrol'));
        $secret = Crypt::decryptString(session('customer_totp_secret'));
        $g = new Google2FA;
        $this->post(route('account.totp.store'), ['code' => $g->oathTotp($secret, $g->getTimestamp())]);
        $this->get(route('account.totp.recovery'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('account.totp.recovery'))->assertRedirect(route('account.show'));
        $this->assertSame(8, $user->totpRecoveryCodes()->count());
    }
}
