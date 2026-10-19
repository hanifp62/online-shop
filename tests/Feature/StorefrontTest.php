<?php

namespace Tests\Feature;

use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Models\ConciergeRequest;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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

    public function test_newsletter_subscription_is_idempotent_with_uniform_response(): void
    {
        Mail::fake();

        $first = $this->postJson('/newsletter', ['email' => 'maison@example.com']);
        $second = $this->postJson('/newsletter', ['email' => 'maison@example.com']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('message'), $second->json('message'));

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Mail::assertQueued(NewsletterWelcome::class, 1);
    }

    private function conciergePayload(array $orderItems, array $extra = []): array
    {
        return array_merge([
            'name' => 'Madame Valerie',
            'email' => 'valerie@maison.id',
            'phone' => '+62 811 2233 4455',
            'service' => 'virtual-consultation',
            'order_items' => json_encode($orderItems),
        ], $extra);
    }

    public function test_concierge_order_items_use_server_side_name_and_price(): void
    {
        Mail::fake();

        $this->postJson('/concierge', $this->conciergePayload([
            ['id' => 'sac-seraphine', 'name' => 'Nama Palsu', 'price' => 1, 'quantity' => 2],
        ]))->assertCreated();

        $notes = ConciergeRequest::where('email', 'valerie@maison.id')->firstOrFail()->notes;
        $this->assertStringContainsString('Sac Séraphine (2x) Rp18.750.000', $notes);
        $this->assertStringContainsString('Total: Rp37.500.000', $notes);
        $this->assertStringNotContainsString('Nama Palsu', $notes);
    }

    public function test_concierge_accepts_empty_hidden_order_items(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ConvertEmptyStringsToNull::class);

        $this->postJson('/concierge', $this->conciergePayload([], ['order_items' => '']))
            ->assertCreated();

        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'valerie@maison.id',
            'notes' => null,
        ]);
    }

    public function test_authenticated_concierge_with_alternate_contact_email_stays_in_account_history(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->actingAs($user)->postJson('/concierge', $this->conciergePayload([], [
            'email' => 'contact@example.com', 'order_items' => null,
        ]))->assertCreated();

        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'contact@example.com', 'user_id' => $user->id,
        ]);
        $concierge = ConciergeRequest::where('email', 'contact@example.com')->firstOrFail();
        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertViewHas('history', fn ($history) => $history->contains('id', $concierge->id))
            ->assertSee('virtual-consultation');
    }

    public function test_concierge_rejects_invalid_order_items(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        $valid = ['id' => 'sac-seraphine', 'quantity' => 1];
        $cases = [
            'unknown product' => [['id' => 'tidak-ada', 'quantity' => 1]],
            'quantity zero' => [['id' => 'sac-seraphine', 'quantity' => 0]],
            'quantity too high' => [['id' => 'sac-seraphine', 'quantity' => 11]],
            'quantity not integer' => [['id' => 'sac-seraphine', 'quantity' => 'abc']],
            'too many items' => array_fill(0, 21, $valid),
            'not a list' => 'bukan-json',
        ];

        foreach ($cases as $label => $items) {
            $payload = $this->conciergePayload([]);
            $payload['order_items'] = is_string($items) ? $items : json_encode($items);

            $this->postJson('/concierge', $payload)
                ->assertUnprocessable("Kasus '{$label}' seharusnya ditolak.");
        }

        $this->assertDatabaseCount('concierge_requests', 0);
    }
}
