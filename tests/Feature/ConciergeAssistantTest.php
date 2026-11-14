<?php

namespace Tests\Feature;

use App\Mail\ConciergeRequested;
use App\Models\Admin;
use App\Models\AssistantFeedback;
use App\Models\ConciergeRequest;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// FR-401/FR-402: the guided assistant hands off through the existing concierge endpoint.
class ConciergeAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, ProductSeeder::class]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Madame Vivienne',
            'email' => 'vivienne@maison.id',
            'phone' => '+62 811 2233 4455',
            'service' => 'virtual-consultation',
            'notes' => "[Dari asisten bantuan]\nHalaman: Beranda\nPertanyaan:\n- barang datang rusak",
        ], $extra);
    }

    public function test_assistant_handoff_is_stored_with_its_source_and_returns_a_reference(): void
    {
        Mail::fake();

        $response = $this->postJson('/concierge', $this->payload(['source' => 'assistant']));

        $concierge = ConciergeRequest::firstOrFail();
        $response->assertCreated()->assertJson([
            'reference' => 'ZC-'.str_pad((string) $concierge->id, 6, '0', STR_PAD_LEFT),
        ]);
        $this->assertSame('assistant', $concierge->source);
        $this->assertStringContainsString('barang datang rusak', $concierge->notes);
        Mail::assertQueued(ConciergeRequested::class);
    }

    public function test_plain_concierge_form_keeps_a_null_source_and_still_gets_a_reference(): void
    {
        Mail::fake();

        $this->postJson('/concierge', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message', 'reference']);

        $this->assertNull(ConciergeRequest::firstOrFail()->source);
    }

    public function test_unknown_source_is_rejected_without_creating_a_request(): void
    {
        Mail::fake();

        $this->postJson('/concierge', $this->payload(['source' => 'chatgpt']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');

        $this->assertDatabaseCount('concierge_requests', 0);
        Mail::assertNothingQueued();
    }

    public function test_storefront_pages_ship_the_assistant_copy_in_the_active_locale(): void
    {
        $this->get('/?locale=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Storefront/Home')
                ->where('copy.assistant.launcher', 'Help & Concierge')
                ->where('copy.assistant.disclosure', fn (string $text) => str_contains($text, 'official Maison catalogue')));

        $slug = Product::where('is_active', true)->value('product_slug');
        $this->get(route('products.show', $slug).'?locale=id')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Products/Show')
                ->where('copy.assistant.launcher', 'Bantuan & Concierge'));
    }

    public function test_product_search_matches_every_word_across_languages_accents_and_typos(): void
    {
        $names = fn (string $q) => array_column($this->getJson('/bantuan/produk?q='.rawurlencode($q))->assertOk()->json('items'), 'name');

        $this->assertSame(['Minaudière Saphir'], $names('tas sutra'));
        $this->assertSame(['Robe Élan'], $names('gaun biru'));
        $this->assertSame(['Écharpe Éclat'], $names('ada syal warna emas?'));
        $this->assertSame(['Écharpe Éclat'], $names('echarpe'));
        $this->assertSame(['Mocassin Riva'], $names('sepatu kulit'));
        $this->assertContains('Robe Élan', $names('sutraa'));
        $this->assertSame(['Montre Nocturne'], $names('jam tangan'));
    }

    public function test_product_search_reports_partial_matches_limits_cards_and_never_shows_inactive_products(): void
    {
        $response = $this->getJson('/bantuan/produk?q=tas+hitam')->assertOk();
        $this->assertTrue($response->json('partial'));
        $this->assertNotEmpty($response->json('items'));

        $this->assertLessThanOrEqual(4, count($this->getJson('/bantuan/produk?q=atelier')->json('items')));

        Product::where('product_slug', 'saphir-clutch')->update(['is_active' => false]);
        $this->assertSame([], $this->getJson('/bantuan/produk?q=minaudiere')->json('items'));

        $empty = $this->getJson('/bantuan/produk?q=apa+ya')->assertOk();
        $this->assertSame([], $empty->json('terms'));
        $this->assertSame(0, $empty->json('total'));
        $this->getJson('/bantuan/produk')->assertUnprocessable();
    }

    public function test_product_search_reads_live_stock_and_exposes_only_card_fields(): void
    {
        Product::where('product_slug', 'saphir-clutch')->update(['stock' => 3]);
        $item = $this->getJson('/bantuan/produk?q=minaudiere')->assertOk()->assertHeader('Cache-Control')->json('items.0');

        $this->assertTrue($item['available']);
        $this->assertSame(['alt', 'available', 'category', 'hasSizes', 'href', 'id', 'image', 'name', 'priceLabel', 'productId'], collect($item)->keys()->sort()->values()->all());
    }

    public function test_feedback_is_anonymous_and_limited_to_known_topics(): void
    {
        $this->postJson('/bantuan/umpan-balik', ['topic' => 'delivery', 'helpful' => false])->assertNoContent();
        $this->postJson('/bantuan/umpan-balik', ['topic' => 'chatgpt', 'helpful' => true])->assertUnprocessable();

        $vote = AssistantFeedback::sole();
        $this->assertSame(['delivery', false], [$vote->topic, $vote->helpful]);
        $this->assertSame(['created_at', 'helpful', 'id', 'locale', 'topic', 'updated_at'], collect($vote->getAttributes())->keys()->sort()->values()->all());
    }

    public function test_admin_sees_the_reference_and_can_search_by_it(): void
    {
        Mail::fake();
        $this->postJson('/concierge', $this->payload(['source' => 'assistant']))->assertCreated();
        $this->postJson('/concierge', $this->payload(['email' => 'other@maison.id', 'name' => 'Other Client']))->assertCreated();
        $assistant = ConciergeRequest::where('source', 'assistant')->sole();
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');

        $this->get('/admin?q='.$assistant->reference().'&frontend=blade')
            ->assertOk()
            ->assertSee($assistant->reference())
            ->assertSee(__('admin_ui.dashboard.via_assistant'))
            ->assertDontSee('Other Client');
    }
}
