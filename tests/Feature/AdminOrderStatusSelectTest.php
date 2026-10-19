<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderStatusSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_order_status_select_requires_an_explicit_transition(): void
    {
        $admin = Admin::create([
            'name' => 'Operator',
            'email' => 'ops@example.test',
            'password' => 'Password123!',
        ]);
        $user = User::factory()->create([
            'name' => 'Client',
            'first_name' => 'Client',
            'email' => 'client@example.test',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total' => 100,
            'idempotency_key' => 'pending-status-select',
            'payload_hash' => str_repeat('a', 64),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $selects = $xpath->query('//*[@data-admin-table="pesanan"]//form[contains(@class, "status-form")]/select[@name="status"]');

        $this->assertSame(1, $selects->length);
        $this->assertTrue($selects->item(0)->parentNode->hasAttribute('data-order-status-form'), 'status form must opt into instant update');
        $select = $selects->item(0);
        $this->assertTrue($select->hasAttribute('required'));
        $placeholder = $xpath->query('.//option[@disabled and @selected and @value=""]', $select);
        $this->assertSame(1, $placeholder->length, 'status placeholder must be selected and disabled');
        $this->assertSame('Pilih status…', trim($placeholder->item(0)->textContent));
        $cancelled = $xpath->query('.//option[@value="cancelled"]', $select);
        $this->assertSame(1, $cancelled->length);
        $this->assertFalse($cancelled->item(0)->hasAttribute('selected'));
        $this->assertSame('Status pesanan #'.$order->id, $select->getAttribute('aria-label'));
    }
}
