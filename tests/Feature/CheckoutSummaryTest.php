<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_shop_page_keeps_summary_before_two_compact_form_groups(): void
    {
        $response = $this->get('/belanja')->assertOk();

        $response->assertSee('id="checkout-summary"', false)
            ->assertSee('Ringkasan pesanan', false)
            ->assertSee('Memuat ringkasan pesanan…', false)
            ->assertSee('aria-live', false)
            ->assertSee('Potongan voucher dan total akhir dipastikan saat pesanan dibuat.', false)
            ->assertSee('<fieldset class="stack"><legend>Langkah 1/2 · Kontak</legend>', false)
            ->assertSee('<fieldset class="stack"><legend>Langkah 2/2 · Alamat pengiriman</legend>', false);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Langkah 1/2'));
        $this->assertSame(1, substr_count($html, 'Langkah 2/2'));
        $this->assertStringNotContainsString('Langkah 3', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<summary', $html);
        $this->assertStringNotContainsString('overflow-y:auto', $html);
        $this->assertLessThan(
            strpos($html, 'id="guest-checkout-form"'),
            strpos($html, 'id="checkout-summary"'),
            'summary must render before the checkout form so it stays visible'
        );
    }

    public function test_authenticated_shop_page_renders_checkout_summary(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/belanja')->assertOk()
            ->assertSee('id="checkout-summary"', false)
            ->assertSee('Ringkasan pesanan', false)
            ->assertSee('Memuat ringkasan pesanan…', false)
            ->assertSee('aria-live', false)
            ->assertSee('Potongan voucher dan total akhir dipastikan saat pesanan dibuat.', false)
            ->assertSee('id="checkout-form"', false)
            ->assertDontSee('id="guest-checkout-form"', false);

        $html = $response->getContent();
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<summary', $html);
        $this->assertLessThan(
            strpos($html, 'id="checkout-form"'),
            strpos($html, 'id="checkout-summary"'),
            'summary must render before the account checkout form so it stays visible'
        );
    }
}
