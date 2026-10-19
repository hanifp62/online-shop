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
            ->assertSee("\$('address-count').textContent = `(\${data.addresses.length} alamat)`", false)
            ->assertSee("\$('order-count').textContent = `(\${data.total} pesanan)`", false)
            ->assertSee("\$('favorite-count').textContent = `(\${data.favorites.length} favorit)`", false);
    }
}
