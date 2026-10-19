<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOwnershipRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_read_or_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::create([
            'user_id' => $owner->id,
            'status' => 'pending',
            'total' => 100,
            'idempotency_key' => 'ownership-order-'.uniqid(),
            'payload_hash' => str_repeat('a', 64),
        ]);

        $this->actingAs($other)->getJson('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($other)->postJson('/orders/'.$order->id.'/cancel')->assertNotFound();

        $this->assertSame('pending', $order->fresh()->status);
    }
}
