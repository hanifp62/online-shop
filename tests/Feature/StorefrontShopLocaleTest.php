<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class StorefrontShopLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_request_errors_are_delivered_from_the_active_catalog(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = view('shop.request')->render();
            $this->assertMatchesRegularExpression('/id="shop-request-copy"/', $html);
            preg_match('/<script[^>]*id="shop-request-copy"[^>]*>(.*?)<\/script>/s', $html, $match);
            $copy = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(trans('shop_ui.request', [], $locale), $copy);
            $this->assertNotSame('shop_ui.request', $copy);
        }
    }

    public function test_shop_status_labels_are_delivered_from_the_active_catalog(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = view('shop.index')->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
            $this->assertMatchesRegularExpression('/id="shop-status-copy"/', $html);
            preg_match('/<script[^>]*id="shop-status-copy"[^>]*>(.*?)<\/script>/s', $html, $match);
            $copy = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(trans('shop_ui.status', [], $locale), $copy);
            $this->assertSame(['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'unknown'], array_keys($copy));
        }
    }

    public function test_shop_page_uses_each_locales_own_catalog(): void
    {
        foreach (self::LOCALES as $locale) {
            $catalog = require base_path("lang/$locale/shop_ui.php");
            $this->assertArrayHasKey('shop', $catalog, $locale);
            app()->setLocale($locale);
            $html = view('shop.index')->render();
            $this->assertStringContainsString(e($catalog['shop']['heading']), $html);
            $this->assertStringContainsString(e($catalog['shop']['guest_button']), $html);
            preg_match('/<script[^>]*id="shop-copy"[^>]*>(.*?)<\/script>/s', $html, $match);
            $this->assertSame($catalog['shop'], json_decode($match[1], true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function test_product_page_renders_every_label_from_each_catalog(): void
    {
        $this->seed([CategorySeeder::class, ProductSeeder::class]);
        $product = Product::query()->where('product_slug', 'sac-seraphine')->firstOrFail();
        foreach (self::LOCALES as $locale) {
            $catalog = require base_path("lang/$locale/shop_ui.php");
            foreach (['pdp', 'header', 'footer'] as $group) {
                $this->assertArrayHasKey($group, $catalog, "$locale:$group");
            }
            $html = $this->get('/produk/'.$product->product_slug.'?locale='.$locale)->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
            foreach (['header', 'footer'] as $group) {
                foreach ($catalog[$group] as $key => $text) {
                    if (str_starts_with($key, 'auth_')) {
                        continue; // rendered only for signed-in visitors
                    }
                    $this->assertStringContainsString(e($text), $html, "$locale:$group.$key");
                }
            }
            foreach (['back', 'color', 'add', 'consult', 'share', 'assurance_shipping', 'personalization', 'monogram_label', 'monogram_help', 'monogram_aria', 'skip'] as $key) {
                $this->assertStringContainsString(e($catalog['pdp'][$key]), $html, "$locale:pdp.$key");
            }
            $this->assertDoesNotMatchRegularExpression('/shop_ui\.(pdp|header|footer|cart)\./', $html);
        }
    }

    public function test_product_page_source_has_no_indonesian_literals(): void
    {
        $sources = implode("\n", array_map(fn ($p) => file_get_contents(resource_path("views/$p.blade.php")), [
            'products/show', 'partials/product-header', 'partials/product-footer', 'partials/purchase-cart',
        ]));
        foreach (['Lewati ke konten', 'Kembali ke koleksi', 'Tambahkan ke tas', 'Stok habis', 'Bagikan kreasi', 'Lengkapkan kisah',
            'Buka menu', 'Navigasi utama', 'Profil saya', 'Keluar', 'Tampilkan favorit', 'Seluruh hak', 'Apa yang Anda cari',
            'Nama lengkap', 'Konfirmasi permintaan', 'Ketersediaan', 'Panduan ukuran', 'lang="id"'] as $literal) {
            $this->assertStringNotContainsString($literal, $sources, $literal);
        }
    }

    public function test_purchase_cart_renders_every_label_from_each_catalog(): void
    {
        foreach (self::LOCALES as $locale) {
            $catalog = require base_path("lang/$locale/shop_ui.php");
            $this->assertArrayHasKey('cart', $catalog, $locale);
            app()->setLocale($locale);
            $html = view('partials.purchase-cart')->render();
            foreach ($catalog['cart'] as $key => $text) {
                $this->assertStringContainsString(e($text), $html, "$locale:$key");
            }
            $this->assertStringNotContainsString('shop_ui.cart.', $html);
        }
    }

    public function test_catalog_purchase_copy_renders_in_each_locale(): void
    {
        $this->seed([CategorySeeder::class, ProductSeeder::class]);
        $this->actingAs(User::factory()->create());
        foreach (self::LOCALES as $locale) {
            $catalog = require base_path("lang/$locale/shop_ui.php");
            $this->assertArrayHasKey('catalog', $catalog);
            $html = $this->get('/koleksi?locale='.$locale)->assertOk()->getContent();
            foreach (['bag', 'notice', 'home', 'notice_end', 'shop', 'favorite'] as $key) {
                $this->assertStringContainsString(e($catalog['catalog'][$key]), $html);
            }
            preg_match('/<script[^>]*id="catalog-purchase-copy"[^>]*>(.*?)<\/script>/s', $html, $match);
            $this->assertSame($catalog['catalog']['js'], json_decode($match[1], true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function test_shop_dynamic_messages_use_catalog_lookup(): void
    {
        $source = file_get_contents(resource_path('views/shop/index.blade.php'));
        $catalog = require base_path('lang/id/shop_ui.php');
        foreach (array_keys($catalog['shop']['js']) as $key) {
            $this->assertStringContainsString("shopText('$key',", $source, $key);
        }
    }

    public function test_request_catalogs_have_identical_keys_and_preserve_indonesian_copy(): void
    {
        $path = base_path('lang/id/shop_ui.php');
        $this->assertFileExists($path);
        $reference = Arr::dot(require $path);
        foreach (self::LOCALES as $locale) {
            $this->assertFileExists(base_path("lang/$locale/shop_ui.php"));
            $values = Arr::dot(require base_path("lang/$locale/shop_ui.php"));
            $this->assertSame(array_keys($reference), array_keys($values));
            foreach ($values as $key => $value) {
                $this->assertIsString($value, "$locale:$key");
                $this->assertNotSame('', trim($value), "$locale:$key");
            }
            if ($locale !== 'id') {
                $this->assertNotSame($reference['request.network'], $values['request.network']);
            }
        }
        $this->assertSame('Jaringan terputus. Periksa koneksi, lalu coba lagi.', $reference['request.network']);
        $this->assertSame('Silakan masuk, lalu kembali ke Belanja.', $reference['request.401']);
    }
}
