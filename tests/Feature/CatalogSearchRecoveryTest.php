<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function piece(string $title, array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Tas']);

        return Product::create([...['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => $title, 'product_slug' => 'piece-'.Product::count(),
            'product_price' => '10.00', 'stock' => 1, 'is_active' => true], ...$attributes]);
    }

    public function test_single_unicode_edits_and_transposition_recover_whole_words(): void
    {
        $this->piece('Écharpe');
        foreach (['écharpe', 'écharpx', 'écharp', 'échaarpe', 'éhcarpe'] as $term) {
            $this->get('/koleksi?q='.urlencode($term))->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 1);
        }
        foreach (['éxharpx', 'éch', 'écharpe bleue', 'écha%', 'écha_'] as $term) {
            $this->get('/koleksi?q='.urlencode($term))->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 0);
        }
    }

    public function test_decomposed_query_recovers_nfc_title(): void
    {
        $this->piece('Écharpe');

        $this->get('/koleksi?q='.urlencode("e\u{301}charpx"))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1)
            ->assertViewHas('searchRecovery', 'similar');
    }

    public function test_short_decomposed_query_does_not_become_eligible_after_normalization(): void
    {
        $this->piece("Q\u{301}xz");

        $this->get('/koleksi?q='.urlencode("q\u{301}xy"))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', null)
            ->assertViewHas('isFuzzy', false);
    }

    public function test_literal_results_take_priority_over_similar_titles(): void
    {
        $this->piece('Silky');
        $this->piece('Silk');
        $this->get('/koleksi?q=silky')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Silky'])
            ->assertViewHas('isFuzzy', false);
    }

    public function test_fuzzy_preserves_filters_sort_and_eight_item_pagination(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->piece('Silk '.$i, ['product_material' => 'Satin', 'product_color' => 'Gold']);
        }
        $this->piece('Silky', ['is_active' => false]);
        $this->piece('Silky', ['stock' => 0]);
        $this->piece('Silky', ['product_price' => '99.00']);
        $this->piece('Silky', ['product_color' => 'Red']);
        $this->get('/koleksi?q=silky&kategori=tas&color=Gold&material=Satin&harga_min=1000&harga_max=1000&tersedia=1&urut=harga&page=2')
            ->assertOk()->assertViewHas('searchRecovery', 'similar')
            ->assertViewHas('products', fn ($p) => $p->total() === 10 && $p->perPage() === 8
                && $p->pluck('product_title')->all() === ['Silk 8', 'Silk 9']);
    }

    public function test_500_candidates_are_complete_but_501_never_return_partial_matches(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $this->piece('Silk '.$i);
        }
        $this->get('/koleksi?q=silky')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 500)
            ->assertViewHas('searchRecovery', 'similar');
        $this->piece('Silk last');
        $this->get('/koleksi?q=silky')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', 'narrow');
        $this->get('/koleksi?q=Silk')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 501)
            ->assertViewHas('isFuzzy', false);
        $this->piece('Silk filtered', ['product_color' => 'Gold']);
        $this->get('/koleksi?q=silky&color=Gold')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1);
    }

    public function test_multiword_typo_does_not_fuzzy_match_but_literal_multiword_is_case_insensitive(): void
    {
        $this->piece('Sutra Aurora');
        $this->get('/koleksi?q='.urlencode('sutra aurorra'))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', null);
        $this->get('/koleksi?q='.urlencode('SUTRA aurora'))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1)
            ->assertViewHas('isFuzzy', false);
    }

    public function test_three_characters_never_fuzzy_but_four_characters_do(): void
    {
        $this->piece('Bag');
        $this->piece('Bags');
        $this->get('/koleksi?q=bga')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', null);
        $this->get('/koleksi?q=bgas')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Bags'])
            ->assertViewHas('searchRecovery', 'similar');
    }

    public function test_decomposed_title_is_recovered_by_nfc_query(): void
    {
        $this->piece("E\u{301}charpe");
        $this->get('/koleksi?q='.urlencode('écharpx'))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1)
            ->assertViewHas('searchRecovery', 'similar');
    }

    public function test_narrow_guidance_is_localized(): void
    {
        for ($i = 0; $i < 501; $i++) {
            $this->piece('Silk '.$i);
        }
        foreach (['id', 'en', 'zh', 'fr', 'it'] as $locale) {
            $labels = trans('zafira', [], $locale);
            $this->get('/koleksi?q=silky&locale='.$locale)->assertOk()
                ->assertViewHas('searchRecovery', 'narrow')
                ->assertSee($labels['search_narrow']);
        }
    }

    public function test_guidance_and_clear_search_preserve_locale_and_filters(): void
    {
        foreach (['id', 'en', 'zh', 'fr', 'it'] as $locale) {
            $labels = trans('zafira', [], $locale);
            $response = $this->get('/koleksi?q=missing&locale='.$locale.'&color=Gold&urut=harga&page=2');
            $response->assertStatus(302);
            $location = parse_url($response->headers->get('Location'));
            $this->assertSame('/koleksi', $location['path']);
            parse_str($location['query'] ?? '', $redirectQuery);
            $expectedRedirect = ['q' => 'missing', 'color' => 'Gold', 'urut' => 'harga', 'locale' => $locale, 'page' => '1'];
            ksort($redirectQuery);
            ksort($expectedRedirect);
            $this->assertSame($expectedRedirect, $redirectQuery);
            $followed = $this->get($response->headers->get('Location'));
            $followed->assertOk()
                ->assertSee($labels['search_empty_hint'])
                ->assertSee($labels['clear_search']);

            preg_match('/<a[^>]+href="([^"]+)"[^>]*>\s*'.preg_quote($labels['clear_search'], '/').'\s*<\/a>/u', $followed->getContent(), $matches);
            $this->assertNotEmpty($matches);
            $urlParts = parse_url(html_entity_decode($matches[1]));
            $this->assertSame('/koleksi', $urlParts['path']);
            parse_str($urlParts['query'] ?? '', $query);
            $expected = ['color' => 'Gold', 'urut' => 'harga', 'locale' => $locale];
            ksort($query);
            ksort($expected);
            $this->assertSame($expected, $query);
        }
    }
}
