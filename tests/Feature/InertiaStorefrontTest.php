<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\StorefrontPageProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['frontend.enabled' => true, 'frontend.pages' => ['Storefront/Home', 'Products/Show'], 'cache.default' => 'array']);
    }

    private function product(string $slug = 'sac-seraphine'): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Busana']);

        return Product::create([
            'product_cat' => $category->cat_id, 'product_title' => 'Sutra aman',
            'product_slug' => $slug, 'product_brand' => 'ZAFIRA',
            'product_price' => 120000, 'stock' => 4, 'is_active' => true,
            'product_image' => '/favicon.svg', 'product_material' => 'Sutra',
        ]);
    }

    public function test_home_native_projection_preserves_override_numeric_id_and_fallback(): void
    {
        $product = $this->product();
        $response = $this->get('/')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Storefront/Home')
            ->where('products.0.productId', $product->product_id)
            ->where('products.0.image', asset('prototype/assets/minaudiere_saphir.jpg'))
            ->where('products.0.priceLabel', 'Rp120.000')->where('products.0.stock', 4)
            ->where('links.shop', route('shop.index'))->where('user', null)
            ->has('links.locales', 5)->missing('html')->missing('rawHtml')->missing('session')
            ->missing('products.0.created_at')->missing('products.0.product_brand'));
        $response->assertSee('data-react-fallback', false)->assertSee('data-storefront-fallback-loader', false)
            ->assertSee('application/ld+json', false)->assertSee('hero_couture', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_all_seven_home_image_overrides_are_projected(): void
    {
        foreach (StorefrontPageProps::IMAGE_OVERRIDES as $slug => $file) {
            $this->product($slug);
        }
        $this->get('/')->assertInertia(function (Assert $page) {
            $page->has('products', 7);
            foreach (array_values(StorefrontPageProps::IMAGE_OVERRIDES) as $index => $file) {
                $page->where("products.{$index}.image", asset("prototype/assets/{$file}.jpg"))
                    ->where("products.{$index}.srcset", asset("prototype/assets/{$file}-600.webp").' 600w, '.asset("prototype/assets/{$file}.webp").' 896w');
            }
        });
    }

    public function test_pdp_projection_and_schema_remain_fresh_after_stock_changes(): void
    {
        $product = $this->product();
        $this->get('/produk/sac-seraphine')->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')->where('product.stock', 4)->where('product.hasSizes', true)
            ->where('product.productId', $product->product_id)->missing('product.created_at'));
        $product->forceFill(['stock' => 0])->save();
        $response = $this->get('/produk/sac-seraphine')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('product.stock', 0));
        $schema = json_decode($response->viewData('jsonLd'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
        $response->assertSee('rel="canonical"', false)->assertSee('property="og:url"', false);
        $product->forceFill(['is_active' => false])->save();
        $this->get('/produk/sac-seraphine')->assertNotFound();
    }

    public function test_locale_and_user_are_allowlisted_without_credentials(): void
    {
        $this->product();
        $user = User::factory()->create();
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $this->actingAs($user)->get('/?locale='.$locale)->assertInertia(fn (Assert $page) => $page
                ->where('locale', $locale)->where('user.name', $user->name)->where('user.email', $user->email)
                ->missing('user.password')->missing('user.remember_token')->missing('user.id')
                ->where('links.favorites', route('favorites.index')));
        }
    }

    public function test_explicit_rollback_and_allowlist_gate_keep_blade(): void
    {
        $this->product();
        foreach (['/?frontend=blade', '/produk/sac-seraphine?frontend=blade'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('data-react-fallback', false)->assertSee('Sutra aman');
        }
        config(['frontend.pages' => []]);
        $this->get('/')->assertOk()->assertDontSee('data-react-fallback', false);
    }

    public function test_inertia_receives_only_data_and_literal_untrusted_titles(): void
    {
        $product = $this->product();
        $product->forceFill(['product_title' => '</script><script>hostile()</script>'])->save();
        $response = $this->get('/produk/sac-seraphine')->assertOk();
        $response->assertDontSee('</script><script>hostile()</script>', false);
        $this->get('/produk/sac-seraphine', ['X-Inertia' => 'true', 'X-Inertia-Version' => $response->viewData('page')['version']])
            ->assertOk()->assertJsonPath('component', 'Products/Show')
            ->assertJsonPath('props.product.name', '</script><script>hostile()</script>')
            ->assertJsonMissingPath('props.html')->assertJsonMissingPath('props.rawHtml');
    }
}
