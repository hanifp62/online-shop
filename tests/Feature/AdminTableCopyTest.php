<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ConciergeRequest;
use App\Models\Order;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_order_empty_state_distinguishes_status_search_and_unfiltered_results(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()->assertSeeText('Belum ada pesanan.')
            ->assertDontSeeText('Tidak ada pesanan yang cocok dengan filter ini.');
        foreach ([['ostatus' => 'shipped'], ['oq' => 'tidak-ada'], ['oq' => '0']] as $filters) {
            $this->get(route('admin.dashboard', $filters + ['uq' => 'other-panel']))->assertOk()
                ->assertSeeText('Tidak ada pesanan yang cocok dengan filter ini.')
                ->assertDontSeeText('Belum ada pesanan.')
                ->assertSee('href="'.route('admin.dashboard', ['uq' => 'other-panel']).'#panel-pesanan">Reset</a>', false);
        }
    }
}
