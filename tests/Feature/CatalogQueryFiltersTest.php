<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogQueryFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function piece(string $category, string $name, string $price, int $stock = 1): Product
    {
        $cat = Category::firstOrCreate(['cat_title' => $category]);

        return Product::create(['product_cat' => $cat->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => $name, 'product_slug' => strtolower($name),
            'product_price' => $price, 'stock' => $stock]);
    }

    public function test_repeated_categories_and_combined_minor_unit_filters_use_paginator_total(): void
    {
        $this->piece('Tas', 'Alpha', '99.99');
        $this->piece('Sepatu', 'Beta', '100.00');
        $this->piece('Tas', 'Gamma', '100.01');
        $this->piece('Sepatu', 'Delta', '99.99', 0);
        $this->piece('Lain', 'Epsilon', '99.99');
        $this->get('/koleksi?kategori=tas&kategori=sepatu&harga_min=9999&harga_max=10000&tersedia=1&urut=harga')
            ->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 2
                && $p->pluck('product_title')->all() === ['Alpha', 'Beta']);
    }

    public function test_invalid_query_shapes_are_ignored_without_errors(): void
    {
        $this->piece('Tas', 'Alpha', '0.30');
        foreach (['harga_min[]=1&harga_max=no&tersedia[]=1&urut[]=harga&page[]=1',
            'harga_min=999999999999999999999999999&kategori[evil][]=tas',
            'harga_min=-1&harga_max=1.1&urut=unknown&kategori=unknown'] as $query) {
            $this->get('/koleksi?'.$query)->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 1);
        }
    }

    public function test_inclusive_decimal_boundaries_and_inverted_range(): void
    {
        $this->piece('Tas', 'Alpha', '0.30');
        $this->piece('Tas', 'Beta', '0.31');
        $this->get('/koleksi?harga_min=30&harga_max=30')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Alpha']);
        $this->get('/koleksi?harga_min=31&harga_max=30')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0);
    }

    public function test_negative_stock_is_never_exposed_in_catalog_markup(): void
    {
        $this->piece('Tas', 'Negative', '1.00', -3);

        $this->get('/koleksi')->assertOk()
            ->assertSee('Stok: 0', false)
            ->assertDontSee('Stok: -3', false)
            ->assertDontSee('data-stock="-3"', false);
    }

    public function test_negative_stock_is_never_exposed_in_fuzzy_or_availability_results(): void
    {
        $this->piece('Tas', 'Negative', '1.00', -3);

        foreach (['/koleksi?q=negatve', '/koleksi?urut=tersedia'] as $url) {
            $this->get($url)->assertOk()
                ->assertViewHas('products', fn ($p) => $p->total() === 1)
                ->assertSee('Stok: 0', false)
                ->assertSee('data-stock="0"', false)
                ->assertSee('disabled', false)
                ->assertDontSee('-3', false);
        }
    }

    public function test_ambiguous_category_slugs_are_not_used(): void
    {
        $this->piece('Tas!', 'Alpha', '1.00');
        $this->piece('Tas', 'Beta', '1.00');
        $this->get('/koleksi?kategori=tas')->assertOk()
            ->assertViewHas('filters', fn ($f) => ! isset($f['kategori']));
    }

    public function test_availability_sort_puts_in_stock_first_then_product_id(): void
    {
        $out = $this->piece('Tas', 'Out', '1.00', 0);
        $in = $this->piece('Tas', 'In', '1.00', 2);
        $negative = $this->piece('Tas', 'Negative', '1.00', -3);

        $this->get('/koleksi?urut=tersedia')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_id')->all() === [$in->product_id, $out->product_id, $negative->product_id]);
    }

    public function test_price_and_newest_sort_are_stable_and_hide_inactive_products(): void
    {
        $this->piece('Tas', 'Alpha', '2.00');
        $this->piece('Tas', 'Beta', '1.00');
        $this->piece('Tas', 'Gamma', '1.00');
        $this->piece('Tas', 'Hidden', '0.01')->update(['is_active' => false]);
        $this->get('/koleksi?urut=harga')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Beta', 'Gamma', 'Alpha']);
        $this->get('/koleksi?urut=terbaru')->assertOk()
            ->assertViewHas('products', fn ($p) => $p->pluck('product_title')->all() === ['Gamma', 'Beta', 'Alpha']);
    }
}
