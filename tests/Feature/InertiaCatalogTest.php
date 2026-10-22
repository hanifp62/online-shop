<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(string $title = 'Kreasi aman'): Product
    {
        $category = Category::create(['cat_title' => 'Tas']);

        return Product::create([
            'product_cat' => $category->cat_id, 'product_title' => $title,
            'product_slug' => 'kreasi-aman', 'product_brand' => 'ZAFIRA',
            'product_price' => 120000, 'stock' => 4, 'is_active' => true,
        ]);
    }

    public function test_catalog_has_native_dto_and_server_rendered_fallback(): void
    {
        $product = $this->product();
        $response = $this->get('/koleksi')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Catalog/Index')
            ->where('locale', 'id')->where('products.total', 1)
            ->where('products.data.0.id', $product->product_id)
            ->where('products.data.0.priceLabel', 'Rp120.000')
            ->where('products.data.0.stock', 4)
            ->where('signedIn', false)
            ->missing('user')->missing('session')->missing('csrfToken')->missing('html')
            ->missing('products.data.0.created_at')->missing('products.data.0.product_brand')
        );
        $response->assertSee('data-react-fallback', false)->assertSee('<h1 class="catalog-title">', false)
            ->assertSee('name="_token"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('data-frontend-handoff', false)->assertSee('zafira:react-claim', false)
            ->assertSee('var setRequests', false);
    }

    public function test_x_inertia_receives_dto_only_and_version_mismatch_reload_is_safe(): void
    {
        $this->product();
        $version = $this->get('/koleksi')->viewData('page')['version'];
        $response = $this->get('/koleksi', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->assertOk();
        $response->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'Catalog/Index')
            ->assertJsonMissingPath('props.html')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString('<form', $response->getContent());
        $this->get('/koleksi', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'outdated'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/koleksi'));
    }

    public function test_inertia_page_url_and_version_reload_preserve_repeated_query_keys(): void
    {
        $this->product();
        Category::create(['cat_title' => 'Sepatu']);
        $url = '/koleksi?kategori=tas&kategori=sepatu&locale=en';
        $version = $this->get('/koleksi')->viewData('page')['version'];
        $this->get($url)->assertInertia(fn (Assert $page) => $page->url($url));
        $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertOk()->assertJsonPath('url', $url);
        $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => 'outdated'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url($url));
    }

    public function test_validated_page_url_keeps_pagination_but_not_rejected_input(): void
    {
        $product = $this->product();
        Category::create(['cat_title' => 'Sepatu']);
        for ($i = 1; $i <= 8; $i++) {
            $product->replicate()->fill(['product_slug' => 'kreasi-'.$i])->save();
        }
        $expected = '/koleksi?kategori=tas&kategori=sepatu&locale=en&page=2';
        $url = $expected.'&unknown=yes&harga_min[]=0';
        $response = $this->get($url)->assertOk()->assertDontSee('unknown=yes', false);
        $response->assertInertia(fn (Assert $page) => $page->url($expected)->where('products.current_page', 2)
            ->where('canonical', url($expected))->where('filters.kategori', ['tas', 'sepatu']));
        $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $response->viewData('page')['version']])
            ->assertOk()->assertJsonPath('url', $expected)->assertJsonPath('props.products.current_page', 2);
        $this->get('/login?locale=fr')->assertInertia(fn (Assert $page) => $page->url('/login?locale=fr'));
    }

    public function test_catalog_dto_filters_locale_urls_and_escaping_preserve_existing_contract(): void
    {
        $this->product('</script><script>alert(1)</script>');
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $response = $this->get('/koleksi?locale='.$locale.'&kategori=tas')->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->where('locale', $locale)
                ->where('filters.kategori', ['tas'])->where('categories.0.slug', 'tas')
                ->where('t.title', trans('zafira.title', [], $locale))
                ->has('links.locales', 5)
            );
            $response->assertDontSee('</script><script>alert(1)</script>', false)
                ->assertSee('data-inertia="canonical"', false);
        }
    }

    public function test_rollback_keeps_legacy_renderer_and_interactions(): void
    {
        $this->product();
        foreach ([false, true] as $enabled) {
            config(['frontend.enabled' => $enabled]);
            $response = $this->get('/koleksi'.($enabled ? '?frontend=blade' : ''))->assertOk();
            $response->assertDontSee('data-react-fallback', false)->assertSee('var setRequests', false)
                ->assertSee('Kreasi aman');
        }
    }

    public function test_active_spa_rollbacks_use_document_visits_with_the_current_version(): void
    {
        $this->product();
        $version = $this->get('/koleksi')->viewData('page')['version'];
        foreach (['global', 'allowlist', 'request'] as $mode) {
            config(['frontend.enabled' => $mode !== 'global', 'frontend.pages' => $mode === 'allowlist' ? [] : ['Catalog/Index']]);
            $url = '/koleksi?kategori=tas&kategori=sepatu'.($mode === 'request' ? '&frontend=blade' : '');
            $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
                ->assertStatus(409)->assertHeader('X-Inertia-Location', url($url))
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->get($url)->assertOk()->assertSee('var setRequests', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_non_page_api_and_sitemap_contracts_are_not_converted_to_inertia(): void
    {
        $this->product();
        $this->getJson('/cart')->assertOk()->assertJsonStructure(['items', 'subtotal']);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')
            ->assertHeader('Cache-Control', 'max-age=3600, public');
    }
}
