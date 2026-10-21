<?php

namespace Tests\Feature;

use Tests\TestCase;

// Markup/source checks only; runtime counting is verified by the browser runner.
class ShopListCountTest extends TestCase
{
    public function test_shop_lists_render_polite_count_regions_and_update_totals(): void
    {
        $this->get('/belanja')->assertOk()
            ->assertSee('id="address-count" data-list-count="addresses" aria-live="polite"', false)
            ->assertSee('id="order-count" data-list-count="orders" aria-live="polite"', false)
            ->assertSee('id="favorite-count" data-list-count="favorites" aria-live="polite"', false)
            ->assertSee("\$('address-count').textContent = shopText('address_count', '(:count alamat)', {count: data.addresses.length})", false)
            ->assertSee("\$('order-count').textContent = shopText('order_count', '(:count pesanan)', {count: data.total})", false)
            ->assertSee("\$('favorite-count').textContent = shopText('favorite_count', '(:count favorit)', {count: data.favorites.length})", false);
    }

    public function test_count_labels_come_from_each_locale_catalog(): void
    {
        $expectedId = ['address_count' => '(:count alamat)', 'order_count' => '(:count pesanan)', 'favorite_count' => '(:count favorit)'];
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $js = (require base_path("lang/$locale/shop_ui.php"))['shop']['js'];
            foreach (array_keys($expectedId) as $key) {
                $this->assertStringContainsString(':count', $js[$key] ?? '', "$locale:$key");
            }
            $html = $this->get('/belanja?locale='.$locale)->assertOk()->getContent();
            preg_match('/<script[^>]*id="shop-copy"[^>]*>(.*?)<\/script>/s', $html, $match);
            $copy = json_decode($match[1] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
            foreach (array_keys($expectedId) as $key) {
                $this->assertSame($js[$key], $copy['js'][$key] ?? null, "$locale:$key");
            }
        }
        $id = (require base_path('lang/id/shop_ui.php'))['shop']['js'];
        $this->assertSame($expectedId, array_intersect_key($id, $expectedId));
    }
}
