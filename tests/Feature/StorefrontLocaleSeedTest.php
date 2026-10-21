<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLocaleSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_seeds_js_with_server_locale_and_post_url(): void
    {
        foreach (['id', 'en', 'fr', 'it'] as $locale) {
            $this->get('/?locale='.$locale)->assertOk()
                ->assertSee('<html lang="'.$locale.'"', false)
                ->assertSee('data-server-locale="'.$locale.'"', false)
                ->assertSee('data-locale-url="'.route('locale.update').'"', false);
        }
    }

    public function test_homepage_renders_visible_polite_locale_status_region(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="locale-status" role="status" aria-live="polite" aria-atomic="true"', false);
    }

    public function test_zh_homepage_renders_chinese_copy_and_body_language(): void
    {
        $this->get('/?locale=zh')->assertOk()
            ->assertSee('<html lang="zh" data-server-locale="zh"', false)
            ->assertSee('<body class="storefront" lang="zh">', false)
            ->assertSee('<span lang="fr">Édition No. 01</span>', false)
            ->assertSee('关闭个人资料')
            ->assertSee('一段时光，')
            ->assertSee('搜索目录中的作品')
            ->assertDontSee('Lahir dari Cahaya,')
            ->assertDontSee('Ringkasan pesanan Anda')
            ->assertDontSee('Tutup Profil');
    }

    public function test_catalog_language_choice_propagates_to_homepage(): void
    {
        $this->get('/koleksi?locale=it')->assertOk();
        $this->get('/')->assertOk()->assertSee('data-server-locale="it"', false);
    }

    public function test_profile_locale_wins_after_anonymous_browsing_and_post_login(): void
    {
        User::factory()->create(['email' => 'fr@example.test', 'password' => bcrypt('password'), 'locale' => 'fr']);
        $this->get('/')->assertOk()->assertSee('data-server-locale="id"', false);
        $this->post('/login', ['email' => 'fr@example.test', 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('data-server-locale="fr"', false);
    }

    public function test_password_toggle_labels_are_translated_data_attributes(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path.'?locale=en')->assertOk()
                ->assertSee('data-i18n-hide="Hide"', false)
                ->assertSee('data-i18n-aria-hide="Hide password', false);
        }
    }
}
