<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AuthAndErrorLocaleTest extends TestCase
{
    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    private const AUTH_PAGES = ['login', 'register', 'password.request'];

    public function test_auth_pages_render_catalog_copy_and_html_lang_for_each_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::AUTH_PAGES as $page) {
                $this->get(route($page, ['locale' => $locale]))->assertOk()
                    ->assertSee('<html lang="'.$locale.'">', false)
                    ->assertSee(trans('auth_ui.common.email', [], $locale));
            }

            $this->get(route('login', ['locale' => $locale]))
                ->assertSee(trans('auth_ui.login.heading', [], $locale))
                ->assertSee(trans('auth_ui.login.submit', [], $locale));
            $this->get(route('register', ['locale' => $locale]))
                ->assertSee(trans('auth_ui.register.submit', [], $locale));
        }
    }

    public function test_english_auth_copy_is_translated_and_indonesian_copy_is_preserved(): void
    {
        $this->get(route('login', ['locale' => 'en']))
            ->assertSee('Welcome back.')->assertSee('Email address')->assertSee('Sign in to your account')
            ->assertSee('aria-label="Show password"', false)
            ->assertDontSee('Selamat datang kembali.');

        $this->get(route('login', ['locale' => 'id']))
            ->assertSee('<html lang="id">', false)
            ->assertSee('Selamat datang kembali.')->assertSee('Masuk ke akun')
            ->assertSee('aria-label="Tampilkan kata sandi"', false);
    }

    public function test_social_partial_uses_placeholder_with_provider_proper_noun(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 's', 'services.google.redirect' => 'http://localhost/cb']);

        $this->get(route('login', ['locale' => 'fr']))
            ->assertSee('aria-label="'.e(trans('auth_ui.social.group', [], 'fr')).'"', false)
            ->assertSee(trans('auth_ui.social.continue', ['provider' => 'Google'], 'fr'))
            ->assertSee('Google');
        $this->get(route('login', ['locale' => 'id']))->assertSee('Lanjutkan dengan Google');
    }

    public function test_reset_and_totp_views_render_localized_copy(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $reset = view('auth.password-reset', ['token' => 'tok"<x>', 'email' => 'a@b.test', 'errors' => new ViewErrorBag])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $reset);
            $this->assertStringContainsString(e(trans('auth_ui.reset.submit', [], $locale)), $reset);
            $this->assertStringContainsString('value="tok&quot;&lt;x&gt;"', $reset);

            $totp = view('auth.totp', ['errors' => new ViewErrorBag])->render();
            $this->assertStringContainsString(e(trans('auth_ui.totp.heading', [], $locale)), $totp);
            $this->assertStringContainsString(e(trans('auth_ui.totp.recovery_submit', [], $locale)), $totp);
        }
    }

    public function test_error_pages_render_catalog_copy_and_html_lang_for_each_locale(): void
    {
        Route::middleware('web')->get('/__locale-abort/{status}', fn (int $status) => abort($status));

        foreach (self::LOCALES as $locale) {
            foreach ([403, 404, 429, 500, 503] as $status) {
                $this->get('/__locale-abort/'.$status.'?locale='.$locale, ['X-Request-Id' => 'trace-'.$status])
                    ->assertStatus($status)
                    ->assertSee('<html lang="'.$locale.'">', false)
                    ->assertSee(trans('errors_ui.'.$status.'.title', [], $locale))
                    ->assertSee(trans('errors_ui.common.home', [], $locale))
                    ->assertSee(trans('errors_ui.common.incident', [], $locale))
                    ->assertSee('trace-'.$status);
            }
        }

        $this->get('/__locale-abort/404?locale=en')->assertSee('Page not found')->assertSee('Back to home');
        $this->get('/__locale-abort/404?locale=id')->assertSee('Halaman tidak ditemukan')->assertSee('Kembali ke beranda');
    }

    public function test_419_view_is_localized(): void
    {
        app()->setLocale('it');
        $html = view('errors.419')->render();

        $this->assertStringContainsString('<html lang="it">', $html);
        $this->assertStringContainsString(e(trans('errors_ui.419.title', [], 'it')), $html);
    }

    public function test_auth_and_error_catalogs_have_nested_key_parity_and_no_empty_values(): void
    {
        foreach (['auth_ui', 'errors_ui'] as $catalog) {
            $reference = array_keys(Arr::dot(require base_path('lang/id/'.$catalog.'.php')));

            foreach (self::LOCALES as $locale) {
                $values = Arr::dot(require base_path('lang/'.$locale.'/'.$catalog.'.php'));
                $this->assertSame($reference, array_keys($values), $catalog.':'.$locale);
                foreach ($values as $key => $value) {
                    $this->assertIsString($value, "$catalog:$locale:$key");
                    $this->assertNotSame('', trim($value), "$catalog:$locale:$key");
                }
            }
        }
    }
}
