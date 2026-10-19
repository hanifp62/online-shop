<?php

namespace Tests\Feature;

use App\Models\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTableDraftPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_report_forms_survive_peer_table_replacement(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');
        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);

        foreach (['kategori', 'produk', 'pesanan', 'user', 'audit', 'concierge', 'subscriber'] as $key) {
            $this->assertCount(1, $xpath->query("//*[@id='panel-$key']/descendant-or-self::*[@data-admin-table='$key']"));
            $this->assertCount(1, $xpath->query("//*[@data-admin-table='$key']//h2[@tabindex='-1']"));
            $this->assertCount(1, $xpath->query("//*[@data-admin-table='$key']//form[@data-admin-table-form='$key' and translate(@method, 'get', 'GET')='GET']"));
        }

        foreach ([
            ['panel-kategori', 'admin.categories.store'],
            ['panel-produk', 'admin.products.store'],
            ['panel-pesanan', 'admin.orders.report'],
        ] as [$panel, $route]) {
            $action = route($route);
            $forms = $xpath->query("//*[@id='$panel']//form[@action='$action']");
            $this->assertCount(1, $forms);
            $this->assertCount(0, $xpath->query('ancestor::*[@data-admin-table]', $forms->item(0)));
        }
    }
}
