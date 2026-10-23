<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminCategoryController;
use App\Models\Category;
use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Store serialisasi nyata (database) dipakai agar allow-list cache.serializable_classes benar-benar diuji. */
class StorefrontCacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'database']);
        Cache::clearResolvedInstances();
    }

    private function cacheKey(string $key): ?string
    {
        $prefix = Cache::store('database')->getStore()->getPrefix();
        $exists = DB::table(config('cache.stores.database.table', 'cache'))
            ->where('key', $prefix.$key)
            ->exists();

        return $exists ? $key : null;
    }

    private function stockMarkup(string $html): string
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        // These two inert JSON payloads contain copy, not visible availability labels.
        foreach ($xpath->query('//script[@type="application/json" and (@id="zafira-app-copy" or @data-page="app")]') as $script) {
            $script->parentNode->removeChild($script);
        }

        return $document->saveHTML();
    }

    public function test_stock_markup_oracle_excludes_only_the_two_inert_copy_payloads(): void
    {
        $inert = '<script type="application/json" id="zafira-app-copy">{"label":"Stok habis"}</script>'
            .'<script data-page="app" type="application/json">{"props":{"label":"Stok habis"}}</script>';
        $this->assertStringNotContainsString('Stok habis', $this->stockMarkup($inert));
        foreach ([
            '<p>Stok habis</p>',
            '<script type="application/ld+json">{"name":"Stok habis"}</script>',
            '<script type="application/json" id="other-data">{"label":"Stok habis"}</script>',
            '<script>const label = "Stok habis";</script>',
        ] as $retained) {
            $this->assertStringContainsString('Stok habis', $this->stockMarkup($inert.$retained));
        }
    }

    private function product(): Product
    {
        $category = Category::create(['cat_title' => 'Atelier']);

        return Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Lumière', 'product_slug' => 'sac-lumiere',
            'product_price' => 100000, 'product_image' => '/favicon.svg',
            'product_color' => 'Saphir',
        ]);
    }

    public function test_cold_then_warm_request_render_identically_and_second_skips_catalog_queries(): void
    {
        $this->product();
        $revision = AdminCategoryController::storefrontRevision();
        $this->assertNull($this->cacheKey("storefront:products:v4:{$revision}"));

        $cold = $this->get('/')->assertOk()->getContent();

        $key = $this->cacheKey("storefront:products:v4:{$revision}");
        $this->assertNotNull($key);
        $cached = Cache::get($key);
        $this->assertInstanceOf(EloquentCollection::class, $cached);
        $this->assertInstanceOf(Product::class, $cached->first());
        $this->assertInstanceOf(Category::class, $cached->first()->category);
        $this->assertSame('Atelier', $cached->first()->category->cat_title);

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/from ["`](products|categories)["`]/', $query->sql)) {
                // welcome.blade.php resolves purchase ids per render; that lookup is not cached by design.
                if (! preg_match('/product_slug["`] in/', $query->sql)) {
                    $queries++;
                }
            }
        });
        $warm = $this->get('/')->assertOk()->getContent();

        $this->assertSame(0, $queries);
        $this->assertSame($cold, $warm);
    }

    public function test_related_products_are_cached_but_pdp_stock_and_product_are_fresh(): void
    {
        $category = Category::create(['cat_title' => 'Atelier']);
        $first = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sac Lumière', 'product_slug' => 'sac-lumiere', 'product_price' => 100000,
        ]);
        $related = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Étole Saphir', 'product_slug' => 'etole-saphir', 'product_price' => 80000,
        ]);
        DB::table('products')->where('product_id', $first->product_id)->update(['stock' => 4]);
        DB::table('products')->where('product_id', $related->product_id)->update(['stock' => 7]);

        $cold = $this->get('/produk/sac-lumiere')->assertOk()->getContent();
        $this->assertStringContainsString('Étole Saphir', $cold);
        $this->assertStringNotContainsString('Stok habis', $this->stockMarkup($cold));
        $revision = AdminCategoryController::storefrontRevision();
        $relatedKey = $this->cacheKey("storefront:related:{$category->cat_id}:{$first->product_id}:v4:{$revision}");
        $this->assertNotNull($relatedKey);
        $cached = Cache::get($relatedKey);
        $this->assertInstanceOf(EloquentCollection::class, $cached);
        $this->assertInstanceOf(Product::class, $cached->first());
        $this->assertInstanceOf(Category::class, $cached->first()->category);
        $this->assertSame('Étole Saphir', $cached->first()->product_title);
        $this->assertSame(7, (int) $cached->first()->stock);

        DB::table('products')->where('product_id', $first->product_id)->update(['stock' => 0]);
        DB::table('products')->where('product_id', $related->product_id)->update(['stock' => 1]);
        $relatedQueries = 0;
        DB::listen(function ($query) use (&$relatedQueries) {
            if (preg_match('/from ["`]products["`]/', $query->sql) && preg_match('/["`]product_id["`] !=/', $query->sql)) {
                $relatedQueries++;
            }
        });

        $warm = $this->get('/produk/sac-lumiere')->assertOk()->getContent();

        $this->assertSame(0, $relatedQueries);
        $this->assertStringContainsString('Étole Saphir', $warm);
        // PDP main product is not cached, fresh stock reflects the change.
        $this->assertSame(0, (int) $first->fresh()->stock);
        $this->assertStringContainsString('Stok habis', $this->stockMarkup($warm));
        $this->assertStringContainsString('data-stock="7"', $warm); // related card rendered from cache, not live DB (1)
        // Cached related items preserve their serialized stock and collection payload.
        $cached = Cache::get($relatedKey);
        $this->assertInstanceOf(EloquentCollection::class, $cached);
        $this->assertInstanceOf(Product::class, $cached->first());
        $this->assertInstanceOf(Category::class, $cached->first()->category);
        $this->assertSame(7, (int) $cached->first()->stock);
        $this->assertSame('Étole Saphir', $cached->first()->product_title);
    }

    public function test_payload_of_disallowed_class_is_not_unserialized_into_an_object(): void
    {
        Cache::put('storefront:probe', new CheckoutService, 60);

        $value = Cache::get('storefront:probe');

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $value);
        $this->assertSame(
            CheckoutService::class,
            ((array) $value)['__PHP_Incomplete_Class_Name'] ?? null
        );
    }
}
