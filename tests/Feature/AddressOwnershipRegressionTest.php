<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressOwnershipRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_update_or_delete_another_users_address(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $address = Address::create([
            'user_id' => $owner->id, 'label' => 'Rumah', 'recipient_name' => 'Pemilik',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
        $before = $address->fresh()->only(['user_id', 'label', 'recipient_name', 'phone', 'line1', 'city', 'province', 'postal_code']);

        $this->actingAs($other)->patchJson('/addresses/'.$address->id, [
            'label' => 'Curian', 'recipient_name' => 'Penyerang', 'phone' => '080000000000',
            'line1' => 'Jalan Lain 9', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40111',
        ])->assertNotFound();
        $this->actingAs($other)->deleteJson('/addresses/'.$address->id)->assertNotFound();

        $this->assertNotNull($address->fresh());
        $this->assertSame($before, $address->fresh()->only(array_keys($before)));
    }
}
