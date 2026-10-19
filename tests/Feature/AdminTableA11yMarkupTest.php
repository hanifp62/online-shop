<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTableA11yMarkupTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator',
            'email' => 'ops@example.test',
            'password' => 'Password123!',
        ]), 'admin');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    public function test_all_seven_table_wrappers_have_region_tabindex_and_unique_scroll_aria_label(): void
    {
        $this->login();
        $category = Category::create(['cat_title' => 'Sample Category']);
        Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Piece', 'product_slug' => 'piece', 'product_price' => 1000, 'stock' => 5]);
        $user = User::factory()->create(['name' => 'Client', 'first_name' => 'Client', 'email' => 'client@example.test']);
        Order::create(['user_id' => $user->id, 'total' => 1000, 'status' => 'pending', 'idempotency_key' => 'a11y-order', 'payload_hash' => str_repeat('a', 64)]);
        ConciergeRequest::create(['name' => 'Client', 'email' => 'client@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        NewsletterSubscriber::create(['email' => 'client@example.test']);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $wrappers = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " admin-table-wrap ")]');
        $this->assertSame(7, $wrappers->length, 'exactly 7 table overflow wrappers on dashboard');

        $panels = ['kategori', 'produk', 'pesanan', 'user', 'audit', 'concierge', 'subscriber'];
        $seenLabels = [];

        foreach ($panels as $panel) {
            $panelWrapper = $xpath->query('//*[@data-admin-table="'.$panel.'"]//div[contains(concat(" ", normalize-space(@class), " "), " admin-table-wrap ")]');
            $this->assertSame(1, $panelWrapper->length, "panel {$panel} must contain exactly one admin-table-wrap");

            $wrapper = $panelWrapper->item(0);
            $this->assertSame('region', $wrapper->getAttribute('role'), "panel {$panel} wrapper role");
            $this->assertSame('0', $wrapper->getAttribute('tabindex'), "panel {$panel} wrapper tabindex");

            $label = $wrapper->getAttribute('aria-label');
            $this->assertNotEmpty($label, "panel {$panel} wrapper aria-label must not be empty");
            $this->assertStringContainsString('dapat digulir', $label, "panel {$panel} wrapper aria-label explains scroll");
            $this->assertArrayNotHasKey($label, $seenLabels, "wrapper aria-label must be unique: {$label}");
            $seenLabels[$label] = true;
        }

        $this->assertCount(7, $seenLabels);
    }

    public function test_pagination_current_page_indicator_has_aria_current_page(): void
    {
        $this->login();
        $category = Category::create(['cat_title' => 'Sample Category']);
        for ($i = 0; $i < 17; $i++) {
            Product::create([
                'product_cat' => $category->cat_id,
                'product_brand' => 'Maison',
                'product_title' => 'Product '.$i,
                'product_slug' => 'product-'.$i,
                'product_price' => 1000 + $i,
                'stock' => 5,
            ]);
        }

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $currentIndicators = $xpath->query('//*[@data-admin-table="produk"]//nav[contains(concat(" ", normalize-space(@class), " "), " table-pagination ")]//*[@aria-current="page"]');
        $this->assertSame(1, $currentIndicators->length, 'current page indicator must have aria-current="page"');
        $this->assertStringContainsString('Halaman 1 dari', $currentIndicators->item(0)->textContent);

        $prevLink = $xpath->query('//*[@data-admin-table="produk"]//nav[contains(concat(" ", normalize-space(@class), " "), " table-pagination ")]//a[@rel="prev"]');
        if ($prevLink->length > 0) {
            $this->assertFalse($prevLink->item(0)->hasAttribute('aria-current'));
        }
        $nextLink = $xpath->query('//*[@data-admin-table="produk"]//nav[contains(concat(" ", normalize-space(@class), " "), " table-pagination ")]//a[@rel="next"]');
        $this->assertSame(1, $nextLink->length, 'page 1 of 2 must expose a next link');
        $this->assertFalse($nextLink->item(0)->hasAttribute('aria-current'));
        $this->assertSame(0, $xpath->query('//*[@data-admin-table="produk"]//nav[contains(concat(" ", normalize-space(@class), " "), " table-pagination ")]//a[not(@rel)]')->length, 'no numbered page links added');
    }
}
