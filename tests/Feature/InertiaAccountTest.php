<?php

namespace Tests\Feature;

use App\Models\ConciergeRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\AccountPageProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true, 'frontend.pages' => ['Account/Show'],
            'inertia.testing.page_paths' => [resource_path('js/pages')],
            'services.google.client_id' => null, 'services.apple.client_id' => null,
        ]);
    }

    private function inquiry(User $user, string $service, string $date = '2026-10-22 10:00:00'): ConciergeRequest
    {
        $item = ConciergeRequest::create([
            'user_id' => $user->id, 'service' => $service, 'name' => 'Private contact',
            'email' => 'private-contact@example.test', 'phone' => '08123000',
            'status' => 'confirmed', 'preferred_date' => '2026-11-01', 'preferred_time' => '10:00',
            'notes' => "<Private notes>\nSecond line",
        ]);
        $item->forceFill(['created_at' => $date])->save();

        return $item;
    }

    public function test_guest_cannot_read_account_in_either_representation(): void
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
        $this->get(route('account.show'), ['X-Inertia' => 'true'])->assertRedirect(route('login'));
    }

    public function test_explicit_native_dto_and_real_private_fallback_coexist(): void
    {
        $user = User::factory()->create(['first_name' => 'Ada', 'mobile' => '08123']);
        $response = $this->actingAs($user)->get(route('account.show'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Account/Show')
            ->where('profile.name', $user->name)->where('profile.mobile', '08123')
            ->where('profile.email', $user->email)->where('verified', true)
            ->where('urls.update', route('account.update'))->where('urls.export', route('account.export'))
            ->where('history', [])->where('providers', [])
            ->missing('user')->missing('session')->missing('old')->missing('csrfToken')->missing('html')
        );
        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-react-fallback', false)->assertSee('data-page="app"', false)
            ->assertSee('<main class="auth-page">', false)->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_token"', false)->assertSee('zafira:private-leave', false)
            ->assertSee('document.documentElement.style.visibility', false);
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $this->assertTrue($response->viewData('page')['encryptHistory']);
        $keys = array_keys($response->viewData('page')['props']);
        sort($keys);
        $this->assertSame(['assets', 'copy', 'errors', 'hasPassword', 'history', 'locale', 'profile', 'providers', 'status', 'totp', 'urls', 'verified'], $keys);
    }

    public function test_global_allowlist_and_request_rollbacks_keep_real_forms_and_private_lifecycle(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['allowlist', 'disabled', 'request'] as $mode) {
            config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'allowlist' ? [] : ['Account/Show']]);
            $url = route('account.show').($mode === 'request' ? '?frontend=blade' : '');
            $response = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $response->assertDontSee('data-react-fallback', false)->assertDontSee('data-page="app"', false)
                ->assertSee('action="'.route('account.update').'"', false)
                ->assertSee('name="_method" value="PATCH"', false)
                ->assertSee('document.documentElement.style.visibility', false)
                ->assertSee('if (event.persisted) window.location.reload()', false);
            $this->get($url, ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', $url);
        }
    }

    public function test_profile_old_input_is_allowlisted_and_credentials_and_session_never_become_props(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $old = ['name' => '<Name>', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'mobile' => '08123',
            'address1' => '<Address>', 'address2' => 'Paris', 'cart_reminder_opt_in' => '0',
            'email' => 'not-the-owner@example.test', 'password' => 'password-marker',
            'confirmation' => 'confirmation-marker', 'disable_code' => 'otp-marker',
            'recovery_code' => 'recovery-marker', '_token' => 'csrf-marker'];
        $response = $this->actingAs($user)->withSession(['_old_input' => $old, 'private_state' => 'session-marker'])
            ->get(route('account.show'))->assertOk();
        $profile = $response->viewData('page')['props']['profile'];
        foreach (['name', 'first_name', 'last_name', 'mobile', 'address1', 'address2'] as $key) {
            $this->assertSame($old[$key], $profile[$key]);
        }
        $this->assertFalse($profile['cart_reminder_opt_in']);
        $this->assertSame($user->email, $profile['email']);
        $serialized = json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR);
        foreach (['password-marker', 'confirmation-marker', 'otp-marker', 'recovery-marker', 'csrf-marker', 'session-marker', 'not-the-owner@example.test'] as $marker) {
            $this->assertStringNotContainsString($marker, $serialized);
        }
        $response->assertSee('value="&lt;Name&gt;"', false)->assertDontSee('value="password-marker"', false);
    }

    public function test_dto_rejects_nested_input_and_status_and_defends_history_owner_and_limit(): void
    {
        $user = User::factory()->create();
        $foreign = $this->inquiry(User::factory()->create(), 'FOREIGN-MARKER');
        $own = $this->inquiry($user, 'Own service');
        $request = Request::create('/akun');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('_old_input', ['name' => ['private' => 'nested-marker'], 'cart_reminder_opt_in' => ['1']]);
        $request->session()->put('success', ['private' => 'nested-marker']);
        $props = AccountPageProps::show($request, $user, collect([$foreign, ...array_fill(0, 12, $own)]));
        $this->assertSame('', $props['profile']['name']);
        $this->assertFalse($props['profile']['cart_reminder_opt_in']);
        $this->assertNull($props['status']);
        $this->assertCount(10, $props['history']);
        $this->assertStringNotContainsString('FOREIGN-MARKER', json_encode($props, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('nested-marker', json_encode($props, JSON_THROW_ON_ERROR));
        $this->assertSame(['service', 'status', 'statusLabel', 'schedule', 'submitted', 'notes'], array_keys($props['history'][0]));
    }

    public function test_controller_returns_only_latest_ten_owned_inquiries_without_contact_or_model_metadata(): void
    {
        $user = User::factory()->create();
        $this->inquiry(User::factory()->create(), 'FOREIGN-MARKER');
        for ($day = 1; $day <= 12; $day++) {
            $this->inquiry($user, 'Service '.$day, sprintf('2026-10-%02d 10:00:00', $day));
        }
        $response = $this->actingAs($user)->get(route('account.show'))->assertOk();
        $history = $response->viewData('page')['props']['history'];
        $this->assertCount(10, $history);
        $this->assertSame('Service 12', $history[0]['service']);
        $this->assertSame('Service 3', $history[9]['service']);
        $json = json_encode($history, JSON_THROW_ON_ERROR);
        foreach (['FOREIGN-MARKER', 'user_id', 'private-contact@example.test', 'Private contact', '08123000', 'created_at'] as $marker) {
            $this->assertStringNotContainsString($marker, $json);
        }
    }

    public function test_five_locales_and_validation_errors_reach_both_renderers_without_unescaped_content(): void
    {
        $user = User::factory()->create();
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => ['<Invalid name>'], 'password' => ['Wrong password']]));
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $response = $this->actingAs($user)->withSession(['errors' => $errors, 'success' => '<Saved>'])
                ->get(route('account.show').'?locale='.$locale)->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->component('Account/Show')
                ->where('locale', $locale)->where('copy.show.title', trans('account_ui.show.title', [], $locale))
                ->where('copy.greeting', trans('account_ui.show.greeting', ['name' => $user->first_name ?? $user->name], $locale))
                ->where('errors.name', '<Invalid name>')->where('errors.password', 'Wrong password')->where('status', '<Saved>')
            );
            $response->assertSee('<html lang="'.$locale.'">', false)->assertSee('aria-describedby="name-error"', false)
                ->assertSee('&lt;Invalid name&gt;', false)->assertDontSee('<Invalid name>', false)->assertDontSee('<Saved>', false);
        }
    }

    public function test_social_links_and_totp_summary_contain_no_provider_credentials_or_codes(): void
    {
        $user = User::factory()->create(['has_password' => false, 'totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_enabled_at' => now()]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'apple', 'provider_id' => 'provider-private-marker']);
        config(['services.google.client_id' => 'synthetic-client', 'services.google.client_secret' => 'oauth-secret-marker', 'services.google.redirect' => 'https://example.test/callback']);
        $response = $this->actingAs($user)->get(route('account.show'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Account/Show')
            ->where('hasPassword', false)->where('totp.enabled', true)
            ->where('providers.0.id', 'google')->where('providers.0.linked', false)->where('providers.0.url', route('social.link', 'google'))
            ->where('providers.1.id', 'apple')->where('providers.1.linked', true)->where('providers.1.url', route('social.unlink', 'apple'))
        );
        $json = json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR);
        foreach (['oauth-secret-marker', 'provider-private-marker', 'JBSWY3DPEHPK3PXP', 'totp_secret', 'recovery_codes'] as $marker) {
            $this->assertStringNotContainsString($marker, $json);
        }
        $response->assertSee('id="delete_confirmation"', false)->assertDontSee('id="delete_password"', false)
            ->assertSee('name="disable_code"', false);
    }

    public function test_inertia_json_is_native_no_store_and_does_not_contain_fallback_html(): void
    {
        $this->actingAs(User::factory()->create());
        $version = $this->get(route('account.show'))->viewData('page')['version'];
        $response = $this->get(route('account.show'), ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''])->assertOk();
        $response->assertHeader('X-Inertia', 'true')->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('component', 'Account/Show')->assertJsonMissingPath('props.csrfToken')
            ->assertJsonMissingPath('props.user')->assertJsonMissingPath('props.session')->assertJsonMissingPath('props.html');
        $this->assertStringNotContainsString('<form', $response->getContent());
    }
}
