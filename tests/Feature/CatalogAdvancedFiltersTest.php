<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, string $name, string $price, int $stock = 1, bool $active = true): void
    {
        Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => $name,
            'product_slug' => strtolower($name), 'product_price' => $price, 'stock' => $stock, 'is_active' => $active]);
    }

    public function test_repeated_slug_categories_price_stock_sort_and_exact_total(): void
    {
        $tas = Category::create(['cat_title' => 'Tas']);
        $busana = Category::create(['cat_title' => 'Busana']);
        $lain = Category::create(['cat_title' => 'Aksesori']);
        $this->product($tas, 'Tas Murah', '100.00');
        $this->product($tas, 'Tas Mahal', '100.01');
        $this->product($busana, 'Busana Pas', '100.00');
        $this->product($lain, 'Lain', '100.00');
        $this->product($tas, 'Kosong', '100.00', 0);
        $this->product($tas, 'Nonaktif', '100.00', 1, false);

        $this->get('/koleksi?kategori=tas&kategori=busana&harga_min=10000&harga_max=10000&tersedia=1&urut=harga')
            ->assertOk()->assertSee('2</strong>', false)->assertSee('Tas Murah')->assertSee('Busana Pas')
            ->assertDontSee('Tas Mahal')->assertDontSee('Lain')->assertDontSee('Kosong')->assertDontSee('Nonaktif')
            ->assertSee('kategori=tas&amp;kategori=busana', false);
    }

    public function test_collision_is_not_selectable_and_malformed_input_is_ignored(): void
    {
        $a = Category::create(['cat_title' => 'Tas Baru']);
        $b = Category::create(['cat_title' => 'Tas-Baru']);
        $this->product($a, 'Satu', '12.34');
        $this->product($b, 'Dua', '12.34');
        $this->get('/koleksi?kategori=tas-baru&harga_min[]=100&harga_max=9999999999999999999999999999999&urut[]=harga&unknown=yes')
            ->assertOk()->assertSee('2</strong>', false)->assertDontSee('name="kategori" value="tas-baru"', false)
            ->assertDontSee('unknown=yes', false);
    }

    public function test_chips_remove_one_category_and_reset_keeps_only_locale(): void
    {
        $a = Category::create(['cat_title' => 'Tas']);
        $b = Category::create(['cat_title' => 'Busana']);
        $this->product($a, 'Satu', '10.00');
        $this->product($b, 'Dua', '10.00');
        $this->get('/koleksi?kategori=tas&kategori=busana&tersedia=1&locale=en')
            ->assertOk()->assertSee('href="'.url('/koleksi').'?kategori=busana&amp;tersedia=1&amp;locale=en"', false)
            ->assertSee('href="'.url('/koleksi').'?locale=en"', false)->assertSee('aria-live="polite"', false);
    }

    public function test_page_two_keeps_global_total_repeated_categories_and_checked_controls(): void
    {
        $tas = Category::create(['cat_title' => 'Tas']);
        $busana = Category::create(['cat_title' => 'Busana']);
        $lain = Category::create(['cat_title' => 'Aksesori']);
        for ($i = 1; $i <= 10; $i++) {
            $this->product($tas, 'Tas '.$i, '10.00');
        }
        $this->product($busana, 'Busana 1', '10.00');
        $this->product($busana, 'Busana 2', '10.00');
        $this->product($lain, 'Lain', '10.00');

        $this->get('/koleksi?kategori=tas&kategori=busana&page=2')
            ->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 12 && $p->currentPage() === 2 && $p->count() === 4)
            ->assertSee('12</strong>', false)
            ->assertSee('value="tas" checked', false)
            ->assertSee('value="busana" checked', false)
            ->assertDontSee('value="aksesori" checked', false)
            ->assertSee('href="'.url('/koleksi').'?kategori=tas&amp;kategori=busana&amp;locale=id&amp;page=1"', false);
    }

    public function test_each_chip_removes_only_its_filter_and_drops_page(): void
    {
        $tas = Category::create(['cat_title' => 'Tas']);
        Category::create(['cat_title' => 'Busana']);
        for ($i = 1; $i <= 10; $i++) {
            $this->product($tas, 'Kreasi '.$i, '10.00');
        }
        $base = 'href="'.url('/koleksi').'?';

        $this->get('/koleksi?q=kreasi&kategori=tas&kategori=busana&harga_min=100&tersedia=1&urut=harga&page=2')
            ->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 10 && $p->currentPage() === 2)
            ->assertSee($base.'kategori=tas&amp;kategori=busana&amp;harga_min=100&amp;tersedia=1&amp;urut=harga&amp;locale=id"', false)
            ->assertSee($base.'q=kreasi&amp;kategori=busana&amp;harga_min=100&amp;tersedia=1&amp;urut=harga&amp;locale=id"', false)
            ->assertSee($base.'q=kreasi&amp;kategori=tas&amp;harga_min=100&amp;tersedia=1&amp;urut=harga&amp;locale=id"', false)
            ->assertSee($base.'q=kreasi&amp;kategori=tas&amp;kategori=busana&amp;tersedia=1&amp;urut=harga&amp;locale=id"', false)
            ->assertSee($base.'q=kreasi&amp;kategori=tas&amp;kategori=busana&amp;harga_min=100&amp;urut=harga&amp;locale=id"', false)
            ->assertSee($base.'q=kreasi&amp;kategori=tas&amp;kategori=busana&amp;harga_min=100&amp;tersedia=1&amp;locale=id"', false);
    }

    public function test_availability_sort_is_stable_across_pages_with_filters_and_chip(): void
    {
        $tas = Category::create(['cat_title' => 'Tas']);
        $lain = Category::create(['cat_title' => 'Aksesori']);
        for ($i = 1; $i <= 10; $i++) {
            $this->product($tas, 'Tas '.$i, '10.00', $i % 2 === 0 ? 0 : 3);
        }
        $this->product($lain, 'Lain', '10.00');

        $this->get('/koleksi?kategori=tas&harga_min=1000&urut=tersedia&page=2')
            ->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 10
                && $p->pluck('product_title')->all() === ['Tas 8', 'Tas 10'])
            ->assertSee('<option value="tersedia" selected', false)
            ->assertSee('href="'.url('/koleksi').'?kategori=tas&amp;harga_min=1000&amp;locale=id"', false);
        $this->get('/koleksi?kategori=tas&harga_min=1000&urut=tersedia')
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all()
                === ['Tas 1', 'Tas 3', 'Tas 5', 'Tas 7', 'Tas 9', 'Tas 2', 'Tas 4', 'Tas 6']);
    }

    public function test_catalog_filter_form_disables_browser_autocomplete_for_history_navigation(): void
    {
        $this->get('/koleksi')->assertOk()
            ->assertSee('<form class="catalog-form" method="GET" autocomplete="off" action="'.url('/koleksi').'"', false);
    }

    public function test_zero_results_keep_active_filters_and_reset_path(): void
    {
        $tas = Category::create(['cat_title' => 'Tas']);
        $this->product($tas, 'Satu', '10.00');

        $this->get('/koleksi?kategori=tas&harga_min=999999')
            ->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0)
            ->assertSee('0</strong>', false)
            ->assertSee('Tidak ada kreasi yang cocok.')
            ->assertSee('value="tas" checked', false)
            ->assertSee('name="harga_min" value="999999"', false)
            ->assertSee('class="catalog-chips-section"', false)
            ->assertSee('href="'.url('/koleksi').'?kategori=tas&amp;locale=id"', false)
            ->assertSee('href="'.url('/koleksi').'?harga_min=999999&amp;locale=id"', false)
            ->assertSee('href="'.url('/koleksi').'" class="catalog-btn"', false);
    }
}
