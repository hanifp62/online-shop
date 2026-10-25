<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class ResidualLiteralLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_storefront_residual_copy_is_translated_for_every_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (['header.language_selector', 'footer.search_eyebrow', 'footer.motto', 'welcome.wax_success'] as $key) {
                $this->assertTrue(Lang::has("shop_ui.{$key}", $locale, false), "{$locale}: {$key}");
            }
            $html = $this->get(route('home', ['locale' => $locale]))->assertOk();
            $bodies = $this->xpath($html->getContent())->query('//body');
            $this->assertSame(1, $bodies->length, "{$locale}: body element");
            $body = $bodies->item(0);
            $this->assertContains('storefront', preg_split('/\s+/', trim($body->getAttribute('class'))), "{$locale}: storefront class");
            $this->assertSame($locale, $body->getAttribute('lang'), "{$locale}: body language");
            $html->assertDontSee("msg.textContent = 'Segel lilin khas Maison ZAFIRA telah disematkan.'", false);
            $html->assertSee(e(trans('shop_ui.header.language_selector', [], $locale)), false)
                ->assertSee(e(trans('shop_ui.footer.search_eyebrow', [], $locale)), false)
                ->assertSee(e(trans('shop_ui.welcome.wax_success', [], $locale)), false)
                ->assertSee(e(trans('shop_ui.footer.motto', [], $locale)), false);
        }
    }

    public function test_homepage_copy_is_server_rendered_in_every_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->get(route('home', ['locale' => $locale]))->assertOk()->getContent();
            foreach (['category_title', 'packaging_title', 'newsletter_title', 'shortcuts_title', 'profile_drawer_title'] as $key) {
                $this->assertStringContainsString('data-i18n="'.$key.'"', $html);
                $this->assertStringContainsString(e(trans('shop_ui.welcome.'.$key, [], $locale)), $html, "$locale: $key");
            }
            foreach (['hero_title', 'maison_title', 'atelier_title', 'editorial_title', 'services_title'] as $key) {
                $parts = range(1, $key === 'maison_title' ? 3 : 2);
                $expected = implode('<br>', array_map(fn ($part) => e(trans('shop_ui.welcome.'.$key.'_'.$part, [], $locale)), $parts));
                $this->assertStringContainsString('data-i18n="'.$key.'">'.$expected, $html, "$locale: $key");
            }
            foreach (['concierge_title_1', 'concierge_intro', 'order_summary', 'confirm'] as $key) {
                $this->assertStringContainsString(e(trans('shop_ui.footer.'.$key, [], $locale)), $html, "$locale: $key");
            }
            $this->assertStringContainsString('aria-label="'.e(trans('shop_ui.welcome.profile_close', [], $locale)).'"', $html);
            $this->assertStringContainsString('placeholder="'.e(trans('shop_ui.welcome.newsletter_placeholder', [], $locale)).'"', $html);
            if ($locale !== 'id') {
                foreach (['Lahir dari Cahaya,', 'Jadikan ia milik Anda,', 'Kemasan Kado Ikonik', 'Pintasan Keyboard ZAFIRA', 'Ringkasan pesanan Anda', 'Tidak ada kreasi yang sesuai.'] as $raw) {
                    $this->assertStringNotContainsString($raw, $html, "$locale: $raw");
                }
            }
        }
    }

    public function test_welcome_product_card_buttons_render_stock_state_in_every_locale(): void
    {
        $category = Category::create(['cat_title' => 'Robe de Soirée']);
        foreach (['card-available' => ['Robe "Aurore" & <Soie>', 3], 'card-sold-out' => ['Robe Minuit', 0]] as $slug => [$title, $stock]) {
            Product::create([
                'product_cat' => $category->cat_id, 'product_brand' => 'Maison ZAFIRA', 'product_title' => $title,
                'product_slug' => $slug, 'product_price' => 1000000, 'stock' => $stock, 'is_active' => true,
            ]);
        }
        Cache::flush();

        foreach (self::LOCALES as $locale) {
            $xpath = $this->xpath($this->get(route('home', ['locale' => $locale]))->assertOk()->getContent());
            $cases = [
                'card-available' => ['Robe "Aurore" & <Soie>', false, 'card_add', 'card_add_aria'],
                'card-sold-out' => ['Robe Minuit', true, 'sold_out', 'card_sold_out_aria'],
            ];
            foreach ($cases as $slug => [$title, $disabled, $labelKey, $ariaKey]) {
                $buttons = $xpath->query('//article[@data-id="'.$slug.'"]//button[@data-card-add]');
                $this->assertSame(1, $buttons->length, "$locale: $slug button");
                $button = $buttons->item(0);
                $this->assertSame($disabled, $button->hasAttribute('disabled'), "$locale: $slug disabled");
                $this->assertSame(trans('shop_ui.pdp.'.$ariaKey, ['title' => $title], $locale), $button->getAttribute('aria-label'), "$locale: $slug aria");
                $this->assertSame(trans('shop_ui.pdp.'.$labelKey, [], $locale), trim($xpath->query('.//span', $button)->item(0)->textContent), "$locale: $slug text");
            }
        }
    }

    public function test_gift_quote_and_profile_or_login_link_are_server_rendered_in_every_locale(): void
    {
        foreach ([false, true] as $signedIn) {
            if ($signedIn) {
                $this->actingAs(User::factory()->create());
            }
            foreach (self::LOCALES as $locale) {
                $html = $this->get(route('home', ['locale' => $locale]))->assertOk()->getContent();
                $this->assertStringContainsString('“'.e(trans('shop_ui.welcome.packaging_quote', [], $locale)).'”', $html, "$locale: quote");
                $links = $this->xpath($html)->query('//aside[@id="welcome-profile-drawer"]//nav//a[contains(@class,"button-sapphire")]');
                $this->assertSame(1, $links->length, "$locale: profile link");
                $this->assertSame(route($signedIn ? 'account.show' : 'login'), $links->item(0)->getAttribute('href'));
                $this->assertSame(trans('shop_ui.header.'.($signedIn ? 'auth_profile' : 'login_aria'), [], $locale), trim($links->item(0)->textContent), "$locale: profile text");
            }
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    public function test_all_storefront_catalogs_have_identical_keys(): void
    {
        $reference = array_keys(Arr::dot(require lang_path('id/shop_ui.php')));
        sort($reference);
        foreach (self::LOCALES as $locale) {
            $keys = array_keys(Arr::dot(require lang_path("{$locale}/shop_ui.php")));
            sort($keys);
            $this->assertSame($reference, $keys, $locale);
        }
    }

    public function test_storefront_mounts_localized_app_copy_and_search_panel(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->get(route('home', ['locale' => $locale]))->assertOk()
                ->assertSee('id="zafira-app-copy"', false)
                ->assertSee(json_encode(trans('shop_ui.app_js.sold_out', [], $locale)), false)
                ->assertSee(trans('shop_ui.footer.search_title', [], $locale))
                ->assertSee(trans('shop_ui.footer.search_close', [], $locale))
                ->assertDontSee('shop_ui.');
        }
    }

    public function test_welcome_offers_every_locale_in_header_mobile_and_select_controls(): void
    {
        foreach (self::LOCALES as $current) {
            $html = $this->get(route('home', ['locale' => $current]))->assertOk()->getContent();

            preg_match_all('/<div class="lang-switcher"[^>]*>(.*?)<\/div>/s', $html, $groups);
            $this->assertCount(2, $groups[1], "header + mobile lang groups ($current)");
            foreach ($groups[1] as $group) {
                preg_match_all('/<button[^>]*data-set-lang="(\w+)"[^>]*role="radio"[^>]*aria-checked="(true|false)"/', $group, $m);
                $this->assertSame(self::LOCALES, $m[1], "switcher locales ($current)");
            }

            $this->assertStringContainsString('data-set-lang="zh" aria-label="中文"', $html);

            preg_match_all('/<select[^>]*zafira-lang-select[^>]*>(.*?)<\/select>/s', $html, $selects);
            $this->assertNotEmpty($selects[1]);
            foreach ($selects[1] as $select) {
                preg_match_all('/<option value="(\w+)"/', $select, $o);
                $this->assertSame(self::LOCALES, $o[1], "select options ($current)");
            }
        }
    }

    public function test_admin_dashboard_mounts_localized_table_copy(): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator', 'email' => 'residual-admin@example.test', 'password' => 'Password123!',
        ]), 'admin');

        foreach (self::LOCALES as $locale) {
            $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()
                ->assertSee('id="zafira-admin-table-copy"', false)
                ->assertSee(json_encode(trans('admin_ui.table_js.loading', [], $locale)), false)
                ->assertSee(trans('admin_ui.dashboard.product_hint_input', [], $locale))
                ->assertDontSee('admin_ui.');
        }
    }
}
