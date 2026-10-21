<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_login_uses_locale_and_catalog(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->get(route('admin.login', ['locale' => $locale]))->assertOk()
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee(trans('admin_ui.login.heading', [], $locale))
                ->assertDontSee('admin_ui.');
        }
    }

    public function test_console_pages_use_locale_and_catalog(): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator', 'email' => 'locale-admin@example.test', 'password' => 'Password123!',
        ]), 'admin');

        foreach (self::LOCALES as $locale) {
            foreach (['admin.dashboard' => 'dashboard.heading', 'admin.auth-events.index' => 'events.heading'] as $route => $key) {
                $this->get(route($route, ['locale' => $locale]))->assertOk()
                    ->assertSee('<html lang="'.$locale.'">', false)
                    ->assertSee(trans('admin_ui.'.$key, [], $locale))
                    ->assertSee(trans('admin_ui.common.logout', [], $locale))
                    ->assertDontSee('admin_ui.');
            }
        }
    }

    public function test_two_factor_views_use_catalog_without_translating_codes(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (['totp' => 'totp.heading', 'totp-enrol' => 'enrol.heading', 'totp-enrol-recovery' => 'recovery.heading'] as $view => $key) {
                $html = view('admin.'.$view, [
                    'errors' => new ViewErrorBag, 'qr' => 'data:image/png;base64,',
                    'secret' => 'TESTONLY', 'uri' => 'otpauth://test', 'codes' => ['UNCHANGED-123'],
                ])->render();
                $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
                $this->assertStringContainsString(e(trans('admin_ui.'.$key, [], $locale)), $html);
                $this->assertStringNotContainsString('admin_ui.', $html);
                if ($view === 'totp-enrol-recovery') {
                    $this->assertStringContainsString('UNCHANGED-123', $html);
                }
            }
        }
    }

    public function test_invalid_login_flash_is_localized(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->from(route('admin.login'))
                ->post(route('admin.login.process', ['locale' => $locale]), ['email' => 'x@example.test', 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => trans('admin_ui.flash.invalid_login', [], $locale)]);
        }
    }

    public function test_catalogs_have_recursive_key_parity_and_non_empty_values(): void
    {
        $this->assertFileExists(lang_path('id/admin_ui.php'));
        $reference = array_keys(Arr::dot(require lang_path('id/admin_ui.php')));
        foreach (self::LOCALES as $locale) {
            $this->assertFileExists(lang_path($locale.'/admin_ui.php'));
            $values = Arr::dot(require lang_path($locale.'/admin_ui.php'));
            $this->assertSame($reference, array_keys($values), $locale);
            foreach ($values as $key => $value) {
                $this->assertIsString($value, "$locale:$key");
                $this->assertNotSame('', trim($value), "$locale:$key");
            }
        }
    }
}
