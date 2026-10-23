<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class AdminProductStockUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    private function productPanelText(string $html): string
    {
        $panels = $this->xpath($html)->query('//*[@data-admin-table="produk"]');
        $this->assertSame(1, $panels->length);

        return $panels->item(0)->textContent;
    }

    private function assertProductEmptyState(string $html, bool $filtered): void
    {
        $xpath = $this->xpath($html);
        $empties = $xpath->query('//*[@data-admin-table="produk"]//p[contains(concat(" ", normalize-space(@class), " "), " admin-empty ")][not(@data-table-error)]');
        $this->assertSame(1, $empties->length);
        $this->assertSame($filtered ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk.', trim($empties->item(0)->textContent));
        if (! $filtered) {
            $this->assertStringContainsString('Etalase masih kosong. Tambahkan karya pertama Maison ZAFIRA.', $this->productPanelText($html));
        }
        $links = $xpath->query('//*[@data-admin-table="produk"]//div[contains(concat(" ", normalize-space(@class), " "), " admin-empty-action ")]//a');
        $this->assertSame(1, $links->length);
        $this->assertSame($filtered ? 'Hapus pencarian' : '+ Tambah Produk Baru', trim($links->item(0)->textContent));
        $this->assertSame($filtered ? route('admin.dashboard').'#panel-produk' : '#form-produk', $links->item(0)->getAttribute('href'));
    }

    public function test_product_visibility_oracle_rejects_copy_only_json_and_missing_actions(): void
    {
        $labels = ['empty' => 'Belum ada produk.', 'encouragement' => 'Etalase masih kosong. Tambahkan karya pertama Maison ZAFIRA.', 'filtered' => 'Tidak ada produk yang cocok dengan pencarian.', 'clear' => 'Hapus pencarian'];
        $json = '<script data-page="app" type="application/json">'.json_encode($labels, JSON_THROW_ON_ERROR).'</script>';
        foreach ([false, true] as $filtered) {
            $message = $filtered ? $labels['filtered'] : $labels['empty'];
            $extra = $filtered ? '' : '<span>'.$labels['encouragement'].'</span>';
            $href = $filtered ? route('admin.dashboard').'#panel-produk' : '#form-produk';
            $label = $filtered ? $labels['clear'] : '+ Tambah Produk Baru';
            $paragraph = '<p class="admin-empty">'.$message.'</p>';
            $link = '<a href="'.$href.'">'.$label.'</a>';
            $fixture = $json.'<section data-admin-table="produk">'.$paragraph.'<div class="admin-empty-action">'.$extra.$link.'</div></section>';
            $this->assertProductEmptyState($fixture, $filtered);
            $counterexamples = [
                str_replace($paragraph, '', $fixture),
                str_replace($link, '', $fixture),
                str_replace($link, '<a href="'.$href.'"></a>', $fixture),
                str_replace($link, '<a href="#wrong">'.$label.'</a>', $fixture),
            ];
            if (! $filtered) {
                $counterexamples[] = str_replace($extra, '', $fixture);
            }
            foreach ($counterexamples as $broken) {
                $this->assertStringContainsString($json, $broken);
                $rejected = false;
                try {
                    $this->assertProductEmptyState($broken, $filtered);
                } catch (ExpectationFailedException) {
                    $rejected = true;
                }
                $this->assertTrue($rejected, 'Inert JSON must not satisfy missing panel text or an invalid action.');
            }
        }
    }

    public function test_admin_can_create_product_with_stock_and_is_active(): void
    {
        $admin = Admin::first();
        $category = Category::first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
            'product_title' => 'Echarpe Cachemire',
            'product_cat' => $category->cat_id,
            'product_price' => 4500000,
            'product_image' => 'https://images.unsplash.com/photo-example',
            'stock' => 12,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'product_title' => 'Echarpe Cachemire',
            'stock' => 12,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_product_stock_and_visibility(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Test',
            'product_slug' => 'sac-test',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.products.update', $product->product_id), [
            'stock' => 25,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $fresh = $product->fresh();
        $this->assertSame(25, $fresh->stock);
        $this->assertFalse($fresh->is_active);
    }

    public function test_product_list_exposes_inline_stock_field_per_product(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Inline',
            'product_slug' => 'sac-inline',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 7,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Stok Sac Inline"', false)
            ->assertSee('value="7"', false);
        $this->assertStringNotContainsString('Etalase masih kosong', $this->productPanelText($response->getContent()));
    }

    public function test_empty_catalog_shows_encouraging_state_with_open_create_form(): void
    {
        $admin = Admin::first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        $this->assertProductEmptyState($response->getContent(), false);
        $this->assertMatchesRegularExpression('/<details id="form-produk"\s+open/', $response->getContent());
    }

    public function test_empty_search_result_offers_reset_instead_of_create(): void
    {
        $admin = Admin::first();
        $category = Category::first();
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Ada',
            'product_slug' => 'sac-ada',
            'product_price' => 2000000,
            'product_image' => 'https://example.com/img.jpg',
            'stock' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', ['pq' => 'tidak-ada-produk-ini']))
            ->assertOk();

        $this->assertProductEmptyState($response->getContent(), true);
        $this->assertStringNotContainsString('Etalase masih kosong', $this->productPanelText($response->getContent()));
    }
}
