<?php

namespace Tests\Feature;

use App\Support\AuthPageProps;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InertiaAuthTest extends TestCase
{
    private const PAGES = ['Auth/Login', 'Auth/Register', 'Auth/ForgotPassword'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true,
            'frontend.pages' => self::PAGES,
            'inertia.testing.page_paths' => [resource_path('js/pages')],
            'services.google.client_id' => null,
            'services.apple.client_id' => null,
        ]);
    }

    public static function pages(): array
    {
        return [
            'login' => ['/login', 'Auth/Login', 'login', 'login.process'],
            'register' => ['/register', 'Auth/Register', 'register', 'register.store'],
            'forgot password' => ['/lupa-kata-sandi', 'Auth/ForgotPassword', 'forgotten', 'password.email'],
        ];
    }

    #[DataProvider('pages')]
    public function test_native_page_props_and_real_blade_fallback_coexist(string $url, string $component, string $catalog, string $submitRoute): void
    {
        $response = $this->get($url)->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('copy.page.heading', trans("auth_ui.$catalog.heading"))
            ->where('urls.submit', route($submitRoute))
            ->where('locale', 'id')
            ->where('providers', [])
            ->missing('password')->missing('csrfToken')->missing('user')->missing('session')
        );
        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-react-fallback', false)
            ->assertSee('data-page="app"', false)
            ->assertSee('<main class="auth-page">', false)
            ->assertSee('action="'.route($submitRoute).'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('<meta name="csrf-token"', false);

        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $this->assertStringContainsString('<h1>'.e(trans("auth_ui.$catalog.heading")).'</h1>', $response->getContent());
    }

    #[DataProvider('pages')]
    public function test_each_page_supports_allowlist_global_and_request_rollbacks(string $url, string $component, string $catalog, string $submitRoute): void
    {
        foreach (['allowlist', 'disabled', 'request'] as $mode) {
            config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'allowlist' ? [] : self::PAGES]);
            $response = $this->get($url.($mode === 'request' ? '?frontend=blade' : ''))->assertOk();
            $response->assertDontSee('data-react-fallback', false)
                ->assertDontSee('data-page="app"', false)
                ->assertSee('action="'.route($submitRoute).'"', false);
            $this->assertStringContainsString('<h1>'.e(trans("auth_ui.$catalog.heading")).'</h1>', $response->getContent());
        }
    }

    public function test_only_allowlisted_nonsecret_old_input_crosses_the_wire_and_password_fallback_stays_empty(): void
    {
        $old = [
            'name' => '<Client>', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'email' => 'ada@example.test', 'mobile' => '0812340000', 'address1' => '<Address>',
            'address2' => 'not-in-register-form', 'remember' => '1',
            'password' => 'synthetic-password-marker', 'password_confirmation' => 'synthetic-confirmation-marker',
            'code' => 'synthetic-otp-marker', 'recovery_code' => 'synthetic-recovery-marker',
            '_token' => 'synthetic-csrf-marker', 'token' => 'synthetic-reset-marker',
        ];
        foreach (['/login' => ['email'], '/register' => ['name', 'first_name', 'last_name', 'email', 'mobile', 'address1'], '/lupa-kata-sandi' => ['email']] as $url => $fields) {
            $response = $this->withSession(['_old_input' => $old, 'private_state' => 'synthetic-private-marker'])->get($url)->assertOk();
            $props = $response->viewData('page')['props'];
            $this->assertSame(array_intersect_key($old, array_flip($fields)), $props['old']);
            $keys = array_keys($props);
            sort($keys);
            $this->assertSame(['assets', 'copy', 'errors', 'locale', 'old', 'providers', 'status', 'urls'], $keys);
            $serialized = json_encode($props, JSON_THROW_ON_ERROR);
            foreach (['synthetic-password-marker', 'synthetic-confirmation-marker', 'synthetic-otp-marker', 'synthetic-recovery-marker', 'synthetic-csrf-marker', 'synthetic-reset-marker', 'synthetic-private-marker'] as $marker) {
                $this->assertStringNotContainsString($marker, $serialized);
            }
            preg_match_all('/<input\b[^>]*type="password"[^>]*>/', $response->getContent(), $passwordInputs);
            $this->assertCount($url === '/register' ? 2 : ($url === '/login' ? 1 : 0), $passwordInputs[0]);
            foreach ($passwordInputs[0] as $input) {
                $this->assertStringNotContainsString('value=', $input);
            }
        }
    }

    public function test_dto_rejects_nested_old_values_and_non_string_status(): void
    {
        $request = Request::create('/register');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('_old_input', ['email' => ['nested' => 'private-marker'], 'name' => '<Name>']);
        $request->session()->put('success', ['private' => 'private-marker']);
        $props = AuthPageProps::register($request);
        $this->assertSame('', $props['old']['email']);
        $this->assertSame('<Name>', $props['old']['name']);
        $this->assertNull($props['status']);
        $this->assertStringNotContainsString('private-marker', json_encode($props, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('pages')]
    public function test_locale_and_shared_validation_errors_reach_both_renderers(string $url, string $component, string $catalog, string $submitRoute): void
    {
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['<Invalid email>']]));
            $response = $this->withSession(['errors' => $errors, 'success' => '<Status>'])->get($url.'?locale='.$locale)->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('locale', $locale)
                ->where('copy.page.heading', trans("auth_ui.$catalog.heading", [], $locale))
                ->where('copy.common.email', trans('auth_ui.common.email', [], $locale))
                ->where('errors.email', '<Invalid email>')
                ->where('status', '<Status>')
            );
            $response->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee('aria-describedby="email-error"', false)
                ->assertSee('&lt;Invalid email&gt;', false)
                ->assertDontSee('<Invalid email>', false)
                ->assertDontSee('<Status>', false);
        }
    }

    public function test_social_provider_dto_contains_only_enabled_full_document_links_and_labels(): void
    {
        config([
            'services.google.client_id' => 'synthetic-id', 'services.google.client_secret' => 'synthetic-oauth-secret',
            'services.google.redirect' => 'https://example.test/callback',
            'services.apple.client_id' => 'synthetic-apple-id', 'services.apple.redirect' => 'https://example.test/apple',
        ]);
        foreach (['/login', '/register'] as $url) {
            $response = $this->get($url.'?locale=fr')->assertOk();
            $props = $response->viewData('page')['props'];
            $this->assertSame([
                ['id' => 'google', 'url' => route('social.redirect', 'google'), 'label' => trans('auth_ui.social.continue', ['provider' => 'Google'], 'fr')],
                ['id' => 'apple', 'url' => route('social.redirect', 'apple'), 'label' => trans('auth_ui.social.continue', ['provider' => 'Apple'], 'fr')],
            ], $props['providers']);
            $this->assertStringNotContainsString('synthetic-oauth-secret', json_encode($props, JSON_THROW_ON_ERROR));
        }
        $this->get('/lupa-kata-sandi')->assertInertia(fn (Assert $page) => $page->where('providers', []));
    }

    public function test_inertia_requests_return_native_component_data_without_csrf_or_legacy_html(): void
    {
        $version = $this->get('/login')->viewData('page')['version'];
        $response = $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''])->assertOk();
        $response->assertHeader('X-Inertia', 'true')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('component', 'Auth/Login')
            ->assertJsonMissingPath('props.csrfToken')
            ->assertJsonMissingPath('props.html');
        $this->assertStringNotContainsString('<form', $response->getContent());
    }
}
