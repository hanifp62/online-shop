<?php

namespace Tests\Feature;

use App\Models\OrderReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialClaimIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_copy_does_not_promise_unpublished_benefits(): void
    {
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            foreach (['pdp.assurance_shipping', 'pdp.care_for_life', 'header.announcement', 'footer.shipping_complimentary', 'welcome.announcement', 'welcome.packaging_complimentary', 'welcome.service_3_title', 'welcome.service_3_desc', 'welcome.atelier_desc', 'welcome.atelier_step_3', 'welcome.heritage_2_desc'] as $key) {
                $copy = trans("shop_ui.$key", [], $locale);
                $this->assertDoesNotMatchRegularExpression('/complimentary|cuma-cuma|gratis|free delivery|offert|offerte|omaggio|免费|gracieusement|a vita|à vie|for life|sepanjang masa|generations|冷链|berpendingin|insured|assurée|worldwide|international/i', $copy, "$locale: $key");
            }
            $this->assertStringContainsString((string) OrderReturn::WINDOW_DAYS, trans('shop_ui.welcome.returns_policy', [], $locale));
            $this->assertDoesNotMatchRegularExpression('/halaman pesanan|order page|page de commande|pagina dell’ordine|订单页面/i', trans('mail_ui.order.status.completed.body', [], $locale));
            $this->assertStringContainsString('returns-policy', file_get_contents(resource_path('views/partials/product-footer.blade.php')));
        }
    }

    public function test_storefront_links_to_visible_return_policy(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('id="returns-policy"', false)->assertSee('href="#returns-policy"', false);
        $footer = file_get_contents(resource_path('views/partials/product-footer.blade.php'));
        $this->assertStringContainsString('#returns-policy', $footer);
    }
}
