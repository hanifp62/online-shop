<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $category = Category::create(['cat_title' => 'Parfum']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Attar', 'product_slug' => 'attar-'.uniqid(), 'product_price' => '300000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Atelier', 'recipient_name' => 'Siti',
            'phone' => '08123456789', 'line1' => 'Jl. Atelier 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'shipment-key-1'])
            ->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    public function test_new_order_has_explained_resi_belum_tersedia_state(): void
    {
        $order = $this->createOrder();

        $summary = $order->shipmentSummary();

        $this->assertFalse($summary['awb_assigned']);
        $this->assertSame('Resi belum tersedia', $summary['status_label']);
        $this->assertSame(__('customer_messages.shipment.awb_pending'), $summary['explanation']);
        $this->assertNull($summary['tracking_number']);
        $this->assertEmpty($summary['events']);
    }

    public function test_shipped_transition_creates_manifest_and_allows_sequential_events(): void
    {
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        $order = $checkout->transition($order->fresh(), 'processing');
        $order = $checkout->transition($order->fresh(), 'shipped');

        $shipment = $order->shipment()->first();
        $this->assertNotNull($shipment);
        $this->assertSame(Shipment::STATUS_PENDING_MANIFEST, $shipment->status);

        $shipment->update([
            'courier' => 'JNE Express',
            'tracking_number' => 'JNE-ZAF-998811',
            'status' => Shipment::STATUS_IN_TRANSIT,
        ]);
        $shipment->trackingEvents()->create([
            'status' => Shipment::STATUS_IN_TRANSIT,
            'location' => 'Hub Jakarta Selatan',
            'description' => 'Paket sedang diberangkatkan ke kota tujuan.',
            'recorded_at' => now()->addHour(),
        ]);

        $summary = $order->fresh()->shipmentSummary();
        $this->assertTrue($summary['awb_assigned']);
        $this->assertSame('JNE-ZAF-998811', $summary['tracking_number']);
        $this->assertSame('Dalam pengiriman: JNE-ZAF-998811', $summary['status_label']);
        $this->assertCount(2, $summary['events']);
        $this->assertSame('Hub Jakarta Selatan', $summary['events'][1]['location']);
    }

    public function test_events_are_chronological_and_completion_marks_delivered(): void
    {
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        $checkout->transition($order->fresh(), 'processing');
        $order = $checkout->transition($order->fresh(), 'shipped');
        // Courier events can arrive late; the timeline sorts by recorded_at, not insert order.
        $order->shipment->trackingEvents()->create([
            'status' => Shipment::STATUS_MANIFESTED, 'description' => 'Paket dijemput kurir.', 'recorded_at' => now()->subMinutes(30),
        ]);
        $order = $checkout->transition($order->fresh(), 'completed');

        $summary = $order->fresh()->shipmentSummary();
        $this->assertSame(Shipment::STATUS_DELIVERED, $summary['status']);
        $this->assertNotNull($summary['delivered_at']);
        $this->assertSame(
            [Shipment::STATUS_MANIFESTED, Shipment::STATUS_PENDING_MANIFEST, Shipment::STATUS_DELIVERED],
            array_column($summary['events'], 'status'),
        );
    }

    public function test_customer_order_detail_exposes_shipment_summary(): void
    {
        $order = $this->createOrder();

        $this->getJson('/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('order.shipment.status_label', 'Resi belum tersedia')
            ->assertJsonPath('order.shipment.awb_assigned', false);
    }
}
