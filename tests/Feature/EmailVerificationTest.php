<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verifyUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_registration_sends_signed_verification_link(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ratna Dewi',
            'email' => 'ratna@example.com',
            'password' => 'Saphir#2026',
            'password_confirmation' => 'Saphir#2026',
        ])->assertRedirect('/');

        $user = User::where('email', 'ratna@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/email/verifikasi/'.$user->id.'/') && str_contains($url, 'signature=');
        });
    }

    public function test_valid_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/akun')->assertSee('Email belum terverifikasi');

        $this->actingAs($user)->get($this->verifyUrl($user))->assertRedirect(route('account.show'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_or_foreign_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verifyUrl($user).'x')
            ->assertRedirect(route('account.show'))->assertSessionHasErrors('email');
        $this->actingAs($user)->get($this->verifyUrl($user, 'wrong@example.com'))->assertForbidden();
        $this->actingAs($user)->get($this->verifyUrl($other))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_unverified_user_is_guided_before_linking_social_account(): void
    {
        config(['services.google.client_id' => 'c', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/akun/tautan/google')
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->followingRedirects()->get('/akun/tautan/google')
            ->assertSee('Verifikasi email Anda terlebih dahulu');
    }

    public function test_login_failure_message_guides_without_revealing_account_existence(): void
    {
        User::factory()->create(['email' => 'real@example.com']);

        $known = $this->post('/login', ['email' => 'real@example.com', 'password' => 'Wrong#1234'])->getSession()->get('errors')->first('email');
        $unknown = $this->post('/login', ['email' => 'ghost@example.com', 'password' => 'Wrong#1234'])->getSession()->get('errors')->first('email');

        $this->assertSame($known, $unknown);
        $this->assertStringContainsString('Google atau Apple', $known);
    }

    public function test_guest_must_log_in_before_verifying(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verifyUrl($user))->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_only_for_unverified_users(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->actingAs($unverified)->post('/email/verifikasi-ulang')->assertSessionHas('success');
        $this->actingAs($verified)->post('/email/verifikasi-ulang')->assertRedirect(route('account.show'));

        Notification::assertSentTo($unverified, VerifyEmail::class);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_verification_persists_across_devices_and_stays_isolated_per_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'vivienne@example.com', 'password' => Hash::make('password')]);
        $other = User::factory()->unverified()->create();

        // Perangkat A: verifikasi lewat tautan email.
        $this->actingAs($user)->get($this->verifyUrl($user))->assertRedirect(route('account.show'));
        $this->post('/logout');
        $this->flushSession();
        Auth::forgetGuards();

        // Perangkat B: sesi baru, status terbaca dari basis data, bukan dari sesi.
        $this->post('/login', ['email' => 'vivienne@example.com', 'password' => 'password'])->assertRedirect('/');
        $this->get('/akun')->assertOk()->assertDontSee('Email belum terverifikasi');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
        $this->actingAs($other)->get('/akun')->assertSee('Email belum terverifikasi');
    }
}
