<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ValidationLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_validation_catalogs_have_matching_rules_and_placeholders(): void
    {
        $reference = Arr::dot(require base_path('lang/id/validation.php'));
        foreach (self::LOCALES as $locale) {
            $catalog = Arr::dot(require base_path("lang/{$locale}/validation.php"));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale);
            foreach ($catalog as $key => $message) {
                $this->assertIsString($message, "$locale.$key");
                $this->assertNotSame('', trim($message), "$locale.$key");
                preg_match_all('/(?<!:):([a-z_]+)/i', $reference[$key], $expected);
                preg_match_all('/(?<!:):([a-z_]+)/i', $message, $actual);
                $expectedTokens = $expected[1];
                $actualTokens = $actual[1];
                sort($expectedTokens);
                sort($actualTokens);
                $this->assertSame($expectedTokens, $actualTokens, "$locale.$key");
            }
        }
    }

    public function test_validation_messages_follow_request_locale_with_translated_attributes(): void
    {
        $expected = [
            'id' => 'nama wajib diisi.',
            'en' => 'The name field is required.',
            'fr' => 'Le champ nom est obligatoire.',
            'it' => 'Il campo nome è obbligatorio.',
            'zh' => '姓名为必填项。',
        ];
        $user = User::factory()->create();
        foreach ($expected as $locale => $message) {
            $this->actingAs($user)->patch('/akun?locale='.$locale, ['name' => ''])
                ->assertSessionHasErrors(['name' => $message]);
        }

        $this->actingAs($user)->patch('/akun?locale=fr', ['name' => 'Valid', 'mobile' => str_repeat('1', 31)])
            ->assertSessionHasErrors(['mobile' => 'Le champ numéro de portable ne doit pas dépasser 30 caractères.']);
    }

    public function test_profile_messages_follow_request_locale(): void
    {
        $user = User::factory()->create();
        $expected = ['id' => 'Profil Anda telah diperbarui.', 'en' => 'Your profile has been updated.'];
        foreach (self::LOCALES as $locale) {
            $this->actingAs($user)->patch('/akun?locale='.$locale, ['name' => 'Valid Name'])
                ->assertSessionHas('success', $expected[$locale] ?? trans('customer_messages.profile.updated', [], $locale));
        }

        Order::create(['user_id' => $user->id, 'status' => 'pending', 'total' => 1000, 'idempotency_key' => 'k1', 'payload_hash' => 'h']);
        $this->actingAs($user)->delete('/akun?locale=it', ['password' => 'password'])
            ->assertSessionHasErrors(['delete' => trans('customer_messages.profile.delete_blocked', [], 'it')]);

        $this->actingAs($user)->delete('/akun?locale=en', ['password' => 'wrong-password'])
            ->assertSessionHasErrors(['password' => 'The password is incorrect.']);
    }

    public function test_profile_deletion_success_follows_request_locale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete('/akun?locale=zh', ['password' => 'password'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', '您的账户已删除。');
        $this->assertNull($user->fresh());
    }

    public function test_admin_enrol_errors_follow_request_locale(): void
    {
        $admin = Admin::create(['name' => 'Test', 'email' => 'enrol-locale@example.test', 'password' => 'Password1!']);
        foreach (self::LOCALES as $locale) {
            $this->actingAs($admin, 'admin')->post('/admin/totp/enrol?locale='.$locale, ['code' => '123456'])
                ->assertSessionHasErrors(['code' => trans('admin_ui.flash.enrol_expired', [], $locale)]);
        }
        $this->assertSame('启用会话已过期。请重新开始启用。', trans('admin_ui.flash.enrol_expired', [], 'zh'));

        $secret = app(TotpService::class)->generateSecret();
        $this->actingAs($admin, 'admin')->withSession([
            'totp_enrol_secret' => Crypt::encryptString($secret),
            'totp_enrol_state' => ['admin_id' => $admin->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ])
            ->post('/admin/totp/enrol?locale=en', ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'The code does not match. Check your device clock.']);

        $this->actingAs($admin, 'admin')->withSession([
            'totp_enrol_secret' => 'not-valid-ciphertext',
            'totp_enrol_state' => ['admin_id' => $admin->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ])
            ->post('/admin/totp/enrol?locale=fr', ['code' => '123456'])
            ->assertSessionHasErrors(['code' => trans('admin_ui.flash.enrol_unavailable', [], 'fr')]);

        $key = 'admin-totp-enrol:'.$admin->id.'|127.0.0.1';
        RateLimiter::clear($key);
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 60);
        }
        $response = $this->actingAs($admin, 'admin')->post('/admin/totp/enrol?locale=it', ['code' => '123456']);
        $response->assertSessionHasErrors('code');
        $this->assertSame(trans('admin_ui.flash.rate_limited', ['seconds' => 60], 'it'), session('errors')->first('code'));
    }

    public function test_organization_json_ld_follows_request_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->get('/?locale='.$locale)->assertOk()
                ->assertSee(trans('customer_messages.storefront.organization_description', [], $locale), false);
        }
        $this->get('/?locale=en')->assertSee('A Paris–Italy–Indonesia fashion house', false);
    }
}
