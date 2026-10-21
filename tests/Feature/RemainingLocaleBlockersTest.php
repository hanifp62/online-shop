<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderReturnController;
use App\Http\Middleware\AuthorizeAdmin;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RemainingLocaleBlockersTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    private function createCompletedOrderWithReturn(): array
    {
        $category = Category::create(['cat_title' => 'Parfum']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Attar', 'product_slug' => 'attar-'.uniqid(), 'product_price' => '500000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Atelier', 'recipient_name' => 'Siti',
            'phone' => '08123456789', 'line1' => 'Jl. Atelier 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $orderId = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'locale-'.uniqid()])
            ->assertCreated()->json('order.id');
        $order = Order::with('items')->findOrFail($orderId);

        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        foreach (['processing', 'shipped', 'completed'] as $status) {
            $order = $checkout->transition($order->fresh(), $status);
        }

        $return = OrderReturn::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'return_number' => OrderReturn::generateReturnNumber($order->id),
            'status' => OrderReturn::STATUS_APPROVED, 'reason' => 'damaged', 'requested_refund_amount' => 500000,
        ]);

        return [$user, $order, $return];
    }

    public function test_authenticated_endpoints_serialize_labels_in_each_requested_locale(): void
    {
        [$user, $order, $return] = $this->createCompletedOrderWithReturn();
        $refund = Refund::create([
            'order_id' => $order->id, 'return_id' => $return->id, 'user_id' => $user->id,
            'refund_number' => Refund::generateRefundNumber($order->id), 'amount' => 500000,
            'status' => Refund::STATUS_DIAJUKAN, 'reason' => 'Approved return', 'submitted_at' => now(),
        ]);
        $shipment = $order->shipment()->firstOrFail();
        $shipment->update(['courier' => 'JNE Express', 'tracking_number' => 'AWB-123', 'status' => Shipment::STATUS_IN_TRANSIT]);

        $expected = [
            'id' => ['Barang rusak / cacat produksi', 'Pengembalian dana diajukan', 'Dalam pengiriman: AWB-123'],
            'en' => ['Item damaged / manufacturing defect', 'Refund requested', 'In transit: AWB-123'],
            'fr' => ['Article endommagé / défaut de fabrication', 'Remboursement demandé', 'En cours de livraison : AWB-123'],
            'it' => ['Articolo danneggiato / difetto di fabbricazione', 'Rimborso richiesto', 'In transito: AWB-123'],
            'zh' => ['商品损坏／制造缺陷', '已申请退款', '运输中：AWB-123'],
        ];

        foreach ($expected as $locale => [$reasonLabel, $refundLabel, $shipmentLabel]) {
            $this->actingAs($user)->getJson('/returns?locale='.$locale)
                ->assertOk()->assertJsonPath('returns.0.reason_label', $reasonLabel);
            $this->getJson('/refunds?locale='.$locale)
                ->assertOk()->assertJsonPath('refunds.0.status_label', $refundLabel);
            $this->getJson('/orders/'.$order->id.'?locale='.$locale)
                ->assertOk()->assertJsonPath('order.shipment.status_label', $shipmentLabel);
        }
    }

    public function test_each_locale_serializes_return_refund_and_shipment_labels_without_changing_codes(): void
    {
        $return = new OrderReturn(['reason' => 'damaged']);
        $return->setRelation('items', collect());
        $refund = new Refund(['status' => Refund::STATUS_DIAJUKAN]);
        $shipment = new Shipment(['tracking_number' => 'AWB-123', 'status' => Shipment::STATUS_IN_TRANSIT]);
        $shipment->setRelation('trackingEvents', collect());

        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $reasons = OrderReturn::reasons();
            $this->assertSame(array_keys(OrderReturn::REASONS), array_keys($reasons));
            $this->assertSame(trans('customer_messages.returns.reasons.damaged', [], $locale), $reasons['damaged']);
            $this->assertSame('damaged', $return->reason);
            $summary = (new \ReflectionMethod(OrderReturnController::class, 'summary'))->invoke(null, $return);
            $this->assertSame($reasons['damaged'], $summary['reason_label']);
            $this->assertSame(Refund::STATUS_DIAJUKAN, RefundService::summary($refund)['status']);
            $this->assertSame(trans('customer_messages.refund.status_labels.diajukan', [], $locale), RefundService::summary($refund)['status_label']);
            $this->assertSame(trans('customer_messages.shipment.awb_pending', [], $locale), Shipment::notYetShipped()['explanation']);
            $this->assertSame(trans('customer_messages.shipment.awb_unavailable', [], $locale), Shipment::notYetShipped()['status_label']);
            $this->assertSame(trans('customer_messages.shipment.in_transit', ['tracking_number' => 'AWB-123'], $locale), $shipment->trackingSummary()['status_label']);
            $this->assertNull($shipment->trackingSummary()['explanation']);
            $shipment->tracking_number = null;
            $this->assertSame(trans('customer_messages.shipment.awb_unavailable', [], $locale), $shipment->trackingSummary()['status_label']);
            $this->assertSame(trans('customer_messages.shipment.awb_pending', [], $locale), $shipment->trackingSummary()['explanation']);
            $shipment->tracking_number = 'AWB-123';
            $this->assertSame('unknown', Refund::statusLabel('unknown'));
            $this->assertNull(Refund::statusLabel(null));
            $return->reason = 'unknown';
            $unknownSummary = (new \ReflectionMethod(OrderReturnController::class, 'summary'))->invoke(null, $return);
            $this->assertSame('unknown', $unknownSummary['reason_label']);
            $return->reason = 'damaged';
            $this->assertStringContainsString('AWB-123', trans('customer_messages.shipment.in_transit', ['tracking_number' => 'AWB-123'], $locale));
            $this->assertStringNotContainsString(':tracking_number', trans('customer_messages.shipment.in_transit', ['tracking_number' => 'AWB-123'], $locale));
            $this->assertStringNotContainsString(':tracking_number', $shipment->trackingSummary()['status_label']);
            if ($locale !== 'id') {
                $this->assertNotSame(OrderReturn::REASONS['damaged'], $reasons['damaged'], "$locale must not fall back to Indonesian");
            } else {
                $this->assertSame(OrderReturn::REASONS['damaged'], $reasons['damaged']);
            }
        }
    }

    public function test_five_catalogs_have_same_new_keys_and_no_fallback(): void
    {
        $keys = ['returns.reasons.damaged', 'returns.reasons.wrong_item', 'returns.reasons.not_as_described',
            'returns.reasons.defective', 'returns.reasons.other', 'refund.status_labels.diajukan',
            'refund.status_labels.disetujui', 'refund.status_labels.ditolak', 'refund.status_labels.dana_dikirim',
            'refund.status_labels.diterima', 'shipment.awb_pending', 'shipment.awb_unavailable',
            'shipment.in_transit', 'auth.verification_invalid', 'auth.admin_denied'];
        $indonesian = Arr::dot(require base_path('lang/id/customer_messages.php'));
        foreach (self::LOCALES as $locale) {
            $catalog = Arr::dot(require base_path("lang/$locale/customer_messages.php"));
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $catalog, "$locale.$key");
                $this->assertNotSame('', trim($catalog[$key]));
                $this->assertSame($catalog[$key], trans("customer_messages.$key", [], $locale), "$locale.$key fallback");
                if ($locale !== 'id') {
                    $this->assertNotSame($indonesian[$key], $catalog[$key], "$locale.$key copies Indonesian");
                }
            }
        }
    }

    public function test_eligibility_keeps_reason_codes_and_localizes_labels_for_ineligible_orders(): void
    {
        $order = new Order(['status' => 'pending']);
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $result = OrderReturnController::eligibilityFor($order);
            $this->assertFalse($result['eligible']);
            $this->assertSame(array_keys(OrderReturn::REASONS), array_keys($result['reasons']));
            $this->assertSame(trans('customer_messages.returns.reasons.other', [], $locale), $result['reasons']['other']);
        }
    }

    public function test_admin_denial_and_invalid_verification_link_follow_request_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            try {
                (new AuthorizeAdmin)->handle(Request::create('/admin'), fn () => response('ok'), 'view-dashboard');
                $this->fail('Admin denial expected');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
                $this->assertSame(trans('customer_messages.auth.admin_denied', [], $locale), $e->getMessage());
            }
        }

        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (self::LOCALES as $locale) {
            $this->get(route('verification.verify', ['id' => $user->id, 'hash' => 'invalid', 'locale' => $locale]))
                ->assertRedirect(route('account.show'))
                ->assertSessionHasErrors(['email' => trans('customer_messages.auth.verification_invalid', [], $locale)]);
        }
    }
}
