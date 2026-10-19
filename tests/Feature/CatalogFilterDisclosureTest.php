<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_count_reset_locale_and_pagination_query_are_preserved(): void
    {
        $category = Category::create(['cat_title' => 'Tas']);
        for ($i = 1; $i <= 10; $i++) {
            Product::create([
                'product_cat' => $category->cat_id,
                'product_brand' => 'ZAFIRA',
                'product_title' => 'Kreasi '.$i,
                'product_slug' => 'kreasi-'.$i,
                'product_price' => 100000,
                'product_color' => 'Saphir',
                'product_material' => 'Sutra',
            ]);
        }

        $this->get('/koleksi?locale=en&category='.$category->cat_id.'&color=Saphir&material=Sutra&page=2')
            ->assertOk()
            ->assertSee('id="catalog-filters"', false)
            ->assertSee('data-active-count="3"', false)
            ->assertSee('Filters <span class="catalog-filter-count">(3)</span>', false)
            ->assertSee('href="'.url('/koleksi').'?locale=en"', false)
            ->assertDontSee('href="'.url('/koleksi').'"', false)
            ->assertSee('color=Saphir&amp;material=Sutra&amp;category='.$category->cat_id.'&amp;locale=en&amp;page=1', false);

        $this->get('/koleksi?locale=fr')->assertOk()
            ->assertSee('data-active-count="0"', false)
            ->assertSee('name="locale" value="fr"', false)
            ->assertSee('href="'.url('/koleksi').'?locale=fr"', false);
    }

    public function test_status_avoids_server_jargon_and_explains_personalization_path(): void
    {
        $this->get('/koleksi')->assertOk()
            ->assertDontSee('Tas di halaman ini tersimpan di server.')
            ->assertSee('Untuk personalisasi, ajukan permintaan concierge dari')
            ->assertSee('jangan lanjutkan checkout.');
    }
}
