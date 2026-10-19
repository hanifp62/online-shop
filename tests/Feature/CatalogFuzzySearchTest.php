<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogFuzzySearchTest extends TestCase
{
    use RefreshDatabase;

    private function piece(string $title, array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Tas']);

        return Product::create(array_replace([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => $title, 'product_slug' => Str::slug($title),
            'product_price' => '100.00', 'stock' => 1,
        ], $attributes));
    }

    private function assertLinkQuery($response, string $label, array $expected): void
    {
        preg_match('/<a[^>]+href="([^"]+)"[^>]*>\s*'.preg_quote($label, '/').'\s*<\/a>/u', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $location = parse_url(html_entity_decode($matches[1]));
        $this->assertSame('/koleksi', $location['path']);
        parse_str($location['query'] ?? '', $query);
        ksort($query);
        ksort($expected);
        $this->assertSame($expected, $query);
    }

    public function test_typos_in_name_material_and_color_find_products_and_keep_original_query(): void
    {
        $this->piece('Sutra Aurora', ['product_material' => 'Cashmere', 'product_color' => 'Sapphire']);

        foreach (['aurorra', 'cashmre', 'saphpire', 'sutrra'] as $term) {
            $this->get('/koleksi?q='.urlencode($term))->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 1)
                ->assertViewHas('filters', fn ($f) => $f['q'] === $term)
                ->assertSee('Menampilkan kreasi dengan ejaan terdekat.');
        }
    }

    public function test_unicode_edits_and_transposition_do_not_admit_two_unrelated_edits(): void
    {
        $this->piece('Écharpe');
        foreach (['écharpx', 'échaarpe', 'éhcarpe'] as $term) {
            $this->get('/koleksi?q='.urlencode($term))->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 1);
        }
        $this->get('/koleksi?q='.urlencode('éxharpx'))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0);
    }

    public function test_multi_word_and_three_character_queries_do_not_trigger_fuzzy_matching(): void
    {
        $this->piece('Silk');
        $this->piece('Silk Scarf');
        $this->get('/koleksi?q=silk scraf')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', fn ($r) => $r === null)
            ->assertViewHas('isFuzzy', false)
            ->assertDontSee('Menampilkan kreasi dengan ejaan terdekat.');
        $this->get('/koleksi?q=slk')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertViewHas('searchRecovery', fn ($r) => $r === null)
            ->assertViewHas('isFuzzy', false)
            ->assertDontSee('Menampilkan kreasi dengan ejaan terdekat.');
    }

    public function test_literal_multi_word_search_finds_exact_match(): void
    {
        $this->piece('Silk Scarf');
        $this->get('/koleksi?q=silk scarf')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1)
            ->assertViewHas('isFuzzy', false)
            ->assertDontSee('Menampilkan kreasi dengan ejaan terdekat.');
    }

    public function test_literal_results_take_precedence_over_fuzzy_matches(): void
    {
        $this->piece('Aurora');
        $this->piece('Aurorra');

        $this->get('/koleksi?q=aurora')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Aurora'])
            ->assertDontSee('Menampilkan kreasi dengan ejaan terdekat.');
    }

    public function test_fuzzy_results_preserve_filters_sorting_pagination_and_visibility(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->piece('Aurora '.$i);
        }
        $this->piece('Aurora Hidden', ['is_active' => false]);
        $this->piece('Aurora Sold', ['stock' => 0]);
        $this->piece('Aurora Expensive', ['product_price' => '101.00']);

        $this->get('/koleksi?q=aurorra&kategori=tas&tersedia=1&harga_max=10000&urut=terbaru&page=2')
            ->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 10
                && $p->currentPage() === 2 && $p->pluck('product_title')->all() === ['Aurora 2', 'Aurora 1']);
        $this->get('/koleksi?q=aurorra&color=Gold')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0);
    }

    public function test_no_match_offers_search_recovery_without_dropping_other_filters_or_locale(): void
    {
        $this->piece('Aurora');
        $response = $this->get('/koleksi?q=zzzzzz&kategori=tas&locale=en');
        $response->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertSee('Check the spelling or try a shorter name, material or colour.')
            ->assertSee('Clear search');
        $this->assertLinkQuery($response, 'Clear search', ['kategori' => 'tas', 'locale' => 'en']);
    }

    public function test_empty_search_recovery_is_localized_after_page_overflow_redirect(): void
    {
        $labels = require resource_path('views/catalog/labels.php');
        foreach (['id', 'en', 'zh', 'fr', 'it'] as $locale) {
            $response = $this->get('/koleksi?q=missing&locale='.$locale.'&color=Gold&urut=harga&page=2');
            $response->assertStatus(302)->assertRedirect();
            $location = parse_url($response->headers->get('Location'));
            $this->assertSame('/koleksi', $location['path']);
            parse_str($location['query'] ?? '', $redirectQuery);
            $expectedRedirect = ['q' => 'missing', 'color' => 'Gold', 'urut' => 'harga', 'locale' => $locale, 'page' => '1'];
            ksort($redirectQuery);
            ksort($expectedRedirect);
            $this->assertSame($expectedRedirect, $redirectQuery);

            $followed = $this->followRedirects($response);
            $followed->assertOk()->assertSee($labels[$locale]['search_empty_hint'])
                ->assertSee($labels[$locale]['clear_search']);
            $this->assertLinkQuery($followed, $labels[$locale]['clear_search'], [
                'color' => 'Gold', 'urut' => 'harga', 'locale' => $locale,
            ]);
        }
    }

    public function test_short_wildcard_and_invalid_queries_do_not_trigger_unrelated_fuzzy_results(): void
    {
        $this->piece('Aurora');
        foreach (['au', '%', '_', 'zzzzzz', 'aurora missing'] as $term) {
            $this->get('/koleksi?q='.urlencode($term))->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === ($term === 'au' ? 1 : 0));
        }
        $this->get('/koleksi?q[]=aurora')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1);
    }
}
