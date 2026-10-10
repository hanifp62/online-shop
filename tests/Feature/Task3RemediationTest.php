<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ConciergeRequest;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Task3RemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, ProductSeeder::class]);
    }

    public function test_checkout_persists_valid_size_and_monogram_and_rejects_invalid_monogram(): void
    {
        Mail::fake();
        $payload = [
            'name' => 'Alya Putri', 'email' => 'alya@example.com', 'phone' => '081234567890',
            'service' => 'virtual-consultation',
            'order_items' => json_encode([['id' => 'sac-seraphine', 'quantity' => 1, 'size' => 'FR 38', 'monogram' => 'AP']]),
        ];
        $this->postJson('/concierge', $payload)->assertCreated();
        $notes = ConciergeRequest::firstOrFail()->notes;
        $this->assertStringContainsString('Ukuran: FR 38', $notes);
        $this->assertStringContainsString('Monogram: AP', $notes);

        $payload['email'] = 'invalid@example.com';
        $payload['order_items'] = json_encode([['id' => 'sac-seraphine', 'quantity' => 1, 'monogram' => 'A1']]);
        $this->postJson('/concierge', $payload)->assertUnprocessable();
    }

    public function test_storefront_hides_prototype_navigation_in_production_but_keeps_assets(): void
    {
        $this->app['env'] = 'production';
        $response = $this->get('/');
        $response->assertOk()->assertDontSee('href="/prototype/"', false)
            ->assertSee('/prototype/assets/hero_couture.jpg', false)
            ->assertDontSee('USD $', false)->assertDontSee('EUR €', false);
    }

    public function test_product_and_account_pages_do_not_claim_fake_certificates_or_profile_data(): void
    {
        $product = Product::where('product_slug', 'sac-seraphine')->firstOrFail();
        $this->get(route('products.show', $product->product_slug))->assertOk()
            ->assertDontSee('SHA-256')->assertDontSee('alert(')->assertDontSee('24k');

        $user = User::factory()->create();
        ConciergeRequest::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'service' => 'private-styling',
            'status' => 'confirmed',
            'notes' => 'Pesanan khusus',
        ]);

        $this->actingAs($user)->get(route('account.show'))->assertOk()
            ->assertDontSee('Maître Jean-Luc')
            ->assertDontSee('86 cm')
            ->assertDontSee('Pola Drape')
            ->assertDontSee('24k')
            ->assertSee('Dikonfirmasi');

        $this->seed(AdminSeeder::class);
        $admin = Admin::firstOrFail();
        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('SHA-256 Ledger')
            ->assertDontSee('Takt Time Pengerjaan')
            ->assertDontSee('MSI Atelier')
            ->assertSee('Admin Atelier');
    }
}
