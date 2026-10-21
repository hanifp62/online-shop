<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\Favorite;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_export_or_delete(): void
    {
        $this->get(route('account.export'))->assertRedirect(route('login'));
        $this->delete(route('account.destroy'))->assertRedirect(route('login'));
    }

    public function test_user_can_export_their_own_personal_data_as_json(): void
    {
        $user = User::factory()->create([
            'name' => 'Madame Vivienne',
            'email' => 'vivienne@zafira.test',
            'mobile' => '08123456789',
        ]);
        NewsletterSubscriber::create(['email' => $user->email]);
        Address::create([
            'user_id' => $user->id,
            'label' => 'Atelier Utama',
            'recipient_name' => 'Vivienne',
            'phone' => '08123456789',
            'line1' => 'Jl. Kemang Raya No. 10',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12730',
        ]);

        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Robe Soie',
            'product_slug' => 'robe-soie',
            'product_price' => '250000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 10]);
        Favorite::create(['user_id' => $user->id, 'product_id' => $product->product_id]);

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="zafira-data-pribadi.json"');
        $json = $response->json();

        $this->assertSame('vivienne@zafira.test', $json['profile']['email']);
        $this->assertTrue($json['newsletter_subscribed']);
        $this->assertCount(1, $json['addresses']);
        $this->assertSame('Atelier Utama', $json['addresses'][0]['label']);
        $this->assertEquals([$product->product_id], $json['favorites']);
        $this->assertArrayNotHasKey('password', $json['profile']);
        $this->assertArrayNotHasKey('remember_token', $json['profile']);
    }

    public function test_account_deletion_is_blocked_if_orders_are_active(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);
        Order::create([
            'user_id' => $user->id,
            'status' => 'processing',
            'subtotal' => 100000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 100000,
            'idempotency_key' => 'idem-active',
            'payload_hash' => str_repeat('b', 64),
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_is_blocked_if_return_or_refund_is_pending(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'subtotal' => 100000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 100000,
            'idempotency_key' => 'idem-done',
            'payload_hash' => str_repeat('c', 64),
        ]);
        OrderReturn::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'return_number' => 'RET-001',
            'status' => OrderReturn::STATUS_PENDING,
            'reason' => 'damaged',
            'requested_refund_amount' => 50000,
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'password' => 'salah123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_anonymizes_financial_data_and_deletes_pii(): void
    {
        $user = User::factory()->create([
            'email' => 'client@zafira.test',
            'password' => bcrypt('rahasia123'),
        ]);
        NewsletterSubscriber::create(['email' => $user->email]);
        Address::create([
            'user_id' => $user->id,
            'label' => 'Rumah',
            'recipient_name' => 'Client',
            'phone' => '08123',
            'line1' => 'Jl. Mawar',
            'city' => 'Bandung',
            'province' => 'Jabar',
            'postal_code' => '40111',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Client Zafira',
            'customer_email' => $user->email,
            'status' => 'completed',
            'subtotal' => 100000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 100000,
            'idempotency_key' => 'idem-completed',
            'payload_hash' => str_repeat('d', 64),
        ]);
        $ret = OrderReturn::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'return_number' => 'RET-DONE',
            'status' => OrderReturn::STATUS_COMPLETED,
            'reason' => 'damaged',
            'requested_refund_amount' => 50000,
        ]);
        $ref = Refund::create([
            'order_id' => $order->id,
            'return_id' => $ret->id,
            'user_id' => $user->id,
            'refund_number' => 'REF-DONE',
            'amount' => 50000,
            'status' => Refund::STATUS_DITERIMA,
            'reason' => 'Barang cacat selesai',
        ]);
        $concierge = ConciergeRequest::create([
            'user_id' => $user->id,
            'name' => 'Client Asli',
            'email' => $user->email,
            'phone' => '08123',
            'service' => 'Bespoke Evening Gown',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest('web');

        // User & PII dihapus
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'client@zafira.test']);

        // Data transaksi bertahan untuk audit akuntansi dengan user_id null
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null, 'customer_name' => 'Client Zafira']);
        $this->assertDatabaseHas('returns', ['id' => $ret->id, 'user_id' => null]);
        $this->assertDatabaseHas('refunds', ['id' => $ref->id, 'user_id' => null]);

        // Concierge dianonymkan
        $this->assertDatabaseHas('concierge_requests', [
            'id' => $concierge->id,
            'user_id' => null,
            'name' => 'Akun dihapus',
            'email' => 'dihapus@invalid',
        ]);
    }

    public function test_social_only_user_without_password_deletes_using_confirmation_phrase(): void
    {
        $user = User::factory()->create([
            'has_password' => false,
            'email' => 'oauth@zafira.test',
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'confirmation' => 'HAPUS AKUN SAYA',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
