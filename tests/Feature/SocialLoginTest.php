<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    private function fakeGoogle(string $id, ?string $email, bool $verified = true): void
    {
        $identity = (new SocialiteUser)
            ->setRaw(['sub' => $id, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Madame Vivienne', 'email' => $email]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($identity);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_storefront_social_buttons_are_accessible_and_admin_login_has_none(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('aria-label="Masuk dengan akun sosial"', false)
            ->assertSee('data-social-provider="google"', false)
            ->assertSee('Lanjutkan dengan Google');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('social-auth')
            ->assertDontSee('social.redirect')
            ->assertDontSee('Socialite');
    }

    public function test_disabled_provider_is_hidden_and_redirect_fails_closed(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertDontSee('Lanjutkan dengan Google');
        $this->get('/auth/google/redirect')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }

    public function test_redirect_sends_user_to_google_with_state(): void
    {
        $this->get('/login')->assertSee('Lanjutkan dengan Google')->assertDontSee('Lanjutkan dengan Apple');

        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        $this->assertStringContainsString('state=', $response->headers->get('Location'));
    }

    public function test_new_verified_identity_creates_account_and_logs_in(): void
    {
        $this->fakeGoogle('g-1', 'Vivienne@Example.com');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'vivienne@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
    }

    public function test_linked_identity_logs_in_existing_user_without_duplicate(): void
    {
        $user = User::factory()->create(['email' => 'other@example.com']);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-2']);
        $this->fakeGoogle('g-2', 'changed@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_existing_password_account_is_not_auto_linked(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);
        $this->fakeGoogle('g-3', 'owner@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeGoogle('g-4', 'new@example.com', verified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_social_created_user_has_no_password_flag(): void
    {
        $this->fakeGoogle('g-5', 'fresh@example.com');
        $this->get('/auth/google/callback');

        $this->assertFalse(User::where('email', 'fresh@example.com')->firstOrFail()->has_password);
    }

    public function test_logged_in_user_links_google_from_account_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/akun')->assertSee('Tautkan akun Google');

        $this->actingAs($user)->get('/akun/tautan/google')->assertRedirect();
        $this->fakeGoogle('g-6', 'any@example.com');
        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-6']);
    }

    public function test_linking_identity_owned_by_another_user_is_rejected(): void
    {
        $owner = User::factory()->create();
        SocialAccount::create(['user_id' => $owner->id, 'provider' => 'google', 'provider_id' => 'g-7']);
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['social_link' => 'google']);
        $this->fakeGoogle('g-7', 'any@example.com');
        $this->get('/auth/google/callback')->assertRedirect(route('account.show'))->assertSessionHasErrors('social');

        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_logged_in_callback_without_link_intent_does_not_switch_account(): void
    {
        $other = User::factory()->create();
        SocialAccount::create(['user_id' => $other->id, 'provider' => 'google', 'provider_id' => 'g-8']);
        $user = User::factory()->create();
        $this->fakeGoogle('g-8', 'any@example.com');

        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unlink_removes_link_but_guards_last_link_without_password(): void
    {
        $user = User::factory()->create();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-9']);
        $this->actingAs($user)->delete('/akun/tautan/google')->assertRedirect(route('account.show'));
        $this->assertSame(0, $user->fresh()->socialAccounts()->count());

        $socialOnly = User::factory()->create();
        $socialOnly->forceFill(['has_password' => false])->save();
        SocialAccount::create(['user_id' => $socialOnly->id, 'provider' => 'google', 'provider_id' => 'g-10']);
        $this->actingAs($socialOnly)->delete('/akun/tautan/google')->assertSessionHasErrors('social');
        $this->assertSame(1, $socialOnly->fresh()->socialAccounts()->count());
    }

    public function test_invalid_state_is_rejected_gracefully(): void
    {
        $this->get('/auth/google/callback?code=x&state=forged')
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
