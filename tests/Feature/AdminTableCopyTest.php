<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ConciergeRequest;
use App\Models\Order;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class AdminTableCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::create([
            'name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!',
        ]), 'admin');
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

    public function test_status_labels_cover_filters_badges_transitions_and_active_filters(): void
    {
        $orders = ['pending' => 'Menunggu', 'paid' => 'Dibayar', 'processing' => 'Diproses', 'shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        $concierge = ['new' => 'Baru', 'contacted' => 'Dihubungi', 'confirmed' => 'Dikonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        $user = User::factory()->create();
        foreach ($orders as $status => $label) {
            Order::create(['user_id' => $user->id, 'status' => $status, 'total' => 100, 'idempotency_key' => 'copy-'.$status, 'payload_hash' => str_repeat('a', 64)]);
        }
        foreach ($concierge as $status => $label) {
            ConciergeRequest::create(['name' => 'Klien', 'email' => 'client@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => $status]);
        }
        $xpath = $this->xpath($this->get(route('admin.dashboard'))->assertOk()->getContent());
        foreach (['pesanan' => $orders, 'concierge' => $concierge] as $panel => $labels) {
            foreach ($labels as $status => $label) {
                $nodes = $xpath->query("//section[@id='panel-{$panel}']//option[@value='{$status}'] | //section[@id='panel-{$panel}']//span[contains(@class, 'badge-{$status}')]");
                $this->assertGreaterThanOrEqual(2, $nodes->length);
                foreach ($nodes as $node) {
                    $this->assertSame($label, trim($node->textContent));
                }
            }
        }
        $this->get(route('admin.dashboard', ['ostatus' => 'processing', 'status' => 'contacted']))
            ->assertOk()->assertSeeText('Status pesanan: Diproses')->assertSeeText('Status permintaan: Dihubungi');
    }

    public function test_jargon_and_sort_labels_use_plain_language(): void
    {
        $response = $this->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('UserObserver', false)->assertDontSee('cache di-flush', false)
            ->assertSeeText('Dibuat otomatis setiap klien mendaftar.');
        $xpath = $this->xpath($response->getContent());
        foreach (['product_id' => 'No. katalog', 'user_id' => 'No. klien'] as $value => $label) {
            $this->assertSame($label, $xpath->evaluate("string(//option[@value='{$value}'])"));
        }
    }

    public function test_inert_page_copy_keeps_both_order_empty_labels_without_rendering_both(): void
    {
        config(['frontend.enabled' => true, 'frontend.pages' => ['Admin/Dashboard']]);
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $scripts = $xpath->query('//script[@data-page="app"]');
        $this->assertSame(1, $scripts->length);
        $page = json_decode($scripts->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Belum ada pesanan.', $page['props']['copy']['dashboard']['orders_empty']);
        $this->assertSame('Tidak ada pesanan yang cocok dengan filter ini.', $page['props']['copy']['dashboard']['orders_empty_filtered']);
        $panels = $xpath->query('//*[@data-admin-table="pesanan"]');
        $this->assertSame(1, $panels->length);
        $this->assertStringContainsString('Belum ada pesanan.', $panels->item(0)->textContent);
        $this->assertStringNotContainsString('Tidak ada pesanan yang cocok dengan filter ini.', $panels->item(0)->textContent);
    }

    private function assertOrderEmptyState(string $html, bool $filtered, ?string $resetHref = null): void
    {
        $cells = $this->xpath($html)->query('//*[@data-admin-table="pesanan"]//tbody//td[@colspan="7"]');
        $this->assertSame(1, $cells->length);
        $this->assertSame($filtered ? 'Tidak ada pesanan yang cocok dengan filter ini.' : 'Belum ada pesanan.', trim($cells->item(0)->firstChild?->textContent ?? ''));
        if ($filtered) {
            $links = $cells->item(0)->getElementsByTagName('a');
            $this->assertSame(1, $links->length);
            $this->assertSame('Reset', trim($links->item(0)->textContent));
            $this->assertSame($resetHref, $links->item(0)->getAttribute('href'));
        }
    }

    public function test_order_visibility_oracle_rejects_json_only_text_and_missing_reset(): void
    {
        $labels = ['empty' => 'Belum ada pesanan.', 'filtered' => 'Tidak ada pesanan yang cocok dengan filter ini.', 'reset' => 'Reset'];
        $json = '<script data-page="app" type="application/json">'.json_encode($labels, JSON_THROW_ON_ERROR).'</script>';
        $href = route('admin.dashboard', ['uq' => 'other-panel']).'#panel-pesanan';
        $link = '<a href="'.$href.'">Reset</a>';
        $fixture = $json.'<section data-admin-table="pesanan"><table><tbody><tr><td colspan="7">'.$labels['filtered'].' '.$link.'</td></tr></tbody></table></section>';
        $this->assertOrderEmptyState($fixture, true, $href);
        foreach ([
            str_replace($labels['filtered'].' '.$link, $link, $fixture),
            str_replace($link, '', $fixture),
            str_replace($link, '<a href="'.$href.'"></a>', $fixture),
            str_replace($link, '<a href="#wrong">Reset</a>', $fixture),
        ] as $broken) {
            $this->assertStringContainsString($json, $broken);
            $rejected = false;
            try {
                $this->assertOrderEmptyState($broken, true, $href);
            } catch (ExpectationFailedException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'Inert JSON must not satisfy missing order text or an invalid Reset link.');
        }
    }

    public function test_order_empty_state_distinguishes_status_search_and_unfiltered_results(): void
    {
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertOrderEmptyState($response->getContent(), false);
        $this->assertStringNotContainsString('Tidak ada pesanan yang cocok dengan filter ini.', $this->xpath($response->getContent())->evaluate('string(//*[@data-admin-table="pesanan"])'));
        foreach ([['ostatus' => 'shipped'], ['oq' => 'tidak-ada'], ['oq' => '0']] as $filters) {
            $response = $this->get(route('admin.dashboard', $filters + ['uq' => 'other-panel']))->assertOk();
            $this->assertOrderEmptyState($response->getContent(), true, route('admin.dashboard', ['uq' => 'other-panel']).'#panel-pesanan');
            $this->assertStringNotContainsString('Belum ada pesanan.', $this->xpath($response->getContent())->evaluate('string(//*[@data-admin-table="pesanan"])'));
        }
    }
}
