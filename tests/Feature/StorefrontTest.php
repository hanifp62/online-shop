<?php

namespace Tests\Feature;

use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }

    public function test_storefront_displays_zafira_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('ZAFIRA')
            ->assertSee('Lahir dari Cahaya')
            ->assertSee('Sac Séraphine');
    }

    public function test_product_detail_page_is_accessible(): void
    {
        $product = Product::where('product_slug', 'sac-seraphine')->firstOrFail();

        $this->get(route('products.show', $product->product_slug))
            ->assertOk()
            ->assertSee('Sac Séraphine')
            ->assertSee('Rp18.750.000');
    }

    public function test_sitemap_is_accessible(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('http://localhost', false)
            ->assertSee('sac-seraphine', false);
    }

    public function test_product_detail_page_404_for_unknown_slug(): void
    {
        $this->get('/produk/tidak-ada')
            ->assertNotFound();
    }

    public function test_concierge_request_can_be_created(): void
    {
        Mail::fake();

        $response = $this->postJson('/concierge', [
            'name' => 'Alya Putri',
            'email' => 'alya@example.com',
            'phone' => '+62 812 3456 7890',
            'service' => 'private-styling',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '14:00',
            'notes' => 'Mencari busana untuk gala dinner.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'alya@example.com',
            'service' => 'private-styling',
        ]);

        Mail::assertQueued(ConciergeRequested::class);
    }

    public function test_checkout_request_without_schedule_is_valid(): void
    {
        Mail::fake();

        $this->postJson('/concierge', [
            'name' => 'Bima Sakti',
            'email' => 'bima@example.com',
            'phone' => '+62 813 1111 2222',
            'service' => 'virtual-consultation',
            'notes' => 'Pesanan: Sac Séraphine × 1.',
        ])->assertCreated();

        $this->assertDatabaseHas('concierge_requests', ['email' => 'bima@example.com']);
    }

    public function test_newsletter_subscription_is_idempotent(): void
    {
        Mail::fake();

        $this->postJson('/newsletter', ['email' => 'maison@example.com'])->assertCreated();
        $this->postJson('/newsletter', ['email' => 'maison@example.com'])->assertOk();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Mail::assertQueued(NewsletterWelcome::class, 1);
    }
}
