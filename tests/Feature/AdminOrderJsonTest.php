<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderJsonTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
    }

    private function order(): Order
    {
        $cat = Category::firstOrCreate(['cat_title' => 'Sutra'], ['cat_title' => 'Sutra']);
        $product = Product::firstOrCreate(['product_title' => 'Sutra'], [
            'product_cat' => $cat->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_slug' => 'sutra-'.uniqid(),
            'product_price' => '125000.00',
        ]);
        $user = User::factory()->create(['name' => 'Madame', 'mobile' => '081234567890']);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'subtotal' => 125000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 125000,
            'idempotency_key' => 'json-'.uniqid(),
            'payload_hash' => str_repeat('a', 64),
            'shipping_address' => ['line1' => 'Jalan Sutra 1', 'city' => 'Jakarta'],
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->product_id,
            'product_title' => 'Sutra',
            'unit_price' => 125000,
            'quantity' => 1,
            'subtotal' => 125000,
        ]);

        return $order;
    }

    public function test_index_returns_summary_without_contact_or_nested_user_data(): void
    {
        $this->order();

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/admin/orders')->assertOk();

        $this->assertArrayNotHasKey('shipping_address', $response->json('data.0'));
        $this->assertArrayNotHasKey('customer_phone', $response->json('data.0'));
        $this->assertArrayNotHasKey('items', $response->json('data.0'));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0.user')));
        $this->assertSame(['id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total', 'user', 'created_at', 'updated_at'], array_keys($response->json('data.0')));
    }

    public function test_show_includes_fulfilment_address_and_items_but_no_excess_user_or_contact_fields(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->admin(), 'admin')->getJson('/admin/orders/'.$order->id)->assertOk();
        $detail = $response->json('order');

        $this->assertSame('Jalan Sutra 1', $detail['shipping_address']['line1']);
        $this->assertSame('Sutra', $detail['items'][0]['product_title']);
        $this->assertSame(['id', 'product_id', 'product_title', 'unit_price', 'quantity', 'subtotal'], array_keys($detail['items'][0]));
        $this->assertSame(['id', 'name'], array_keys($detail['user']));
        $this->assertSame(['id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total', 'user', 'created_at', 'updated_at', 'shipping_address', 'items'], array_keys($detail));
    }

    public function test_status_update_json_returns_only_order_summary(): void
    {
        $order = $this->order();
        $order->update(['status' => 'paid']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->patchJson('/admin/orders/'.$order->id, ['status' => 'processing'])->assertOk();

        $this->assertSame('processing', $response->json('order.status'));
        $this->assertArrayNotHasKey('shipping_address', $response->json('order'));
        $this->assertArrayNotHasKey('items', $response->json('order'));
        $this->assertSame(['id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total', 'user', 'created_at', 'updated_at'], array_keys($response->json('order')));
    }

    public function test_index_rejects_unknown_status_and_excessive_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/orders?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($admin, 'admin')->getJson('/admin/orders?page=1001')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_index_accepts_each_defined_status_filter(): void
    {
        $admin = $this->admin();
        foreach (array_keys(Order::STATUS_FLOW) as $status) {
            $this->order()->update(['status' => $status]);
            $this->actingAs($admin, 'admin')->getJson('/admin/orders?status='.$status)
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', $status);
        }
    }

    public function test_index_clamps_page_size_and_out_of_range_page(): void
    {
        $this->order();
        $this->actingAs($this->admin(), 'admin')->getJson('/admin/orders?per_page=999&page=1000')
            ->assertOk()->assertJsonPath('per_page', 50)->assertJsonPath('current_page', 1)->assertJsonCount(1, 'data');
        $this->getJson('/admin/orders?per_page=0')->assertOk()->assertJsonPath('per_page', 1);
        $this->getJson('/admin/orders?per_page[]=50')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_manual_pending_and_paid_transitions_remain_rejected_and_html_redirect_works(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin(), 'admin');
        foreach (['pending', 'paid'] as $status) {
            $this->patchJson('/admin/orders/'.$order->id, ['status' => $status])->assertUnprocessable();
            $this->assertSame('pending', $order->fresh()->status);
        }
        $order->update(['status' => 'paid']);
        $this->from('/admin')->patch('/admin/orders/'.$order->id, ['status' => 'processing'])
            ->assertRedirect('/admin')->assertSessionHas('success');
    }
}
