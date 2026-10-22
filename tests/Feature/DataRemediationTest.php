<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\StorefrontController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * REM-20261021 / FR-REM-004 / NFR-REM-001,003.
 * RED handoff only: execution belongs to the serial integrator.
 * SQLite acceptance does not prove MySQL precision, unsigned range, or DDL safety.
 */
class DataRemediationTest extends TestCase
{
    use RefreshDatabase;

    private mixed $originalDatabaseManager = null;

    protected function tearDown(): void
    {
        if ($this->originalDatabaseManager !== null) {
            DB::swap($this->originalDatabaseManager);
            $this->originalDatabaseManager = null;
        }
        parent::tearDown();
    }

    private function mockDatabase(): void
    {
        $this->originalDatabaseManager = DB::getFacadeRoot();
        DB::swap(Mockery::mock($this->originalDatabaseManager)->makePartial());
    }

    private const MONEY_COLUMNS = [
        'returns' => ['requested_refund_amount'],
        'return_items' => ['unit_price', 'subtotal'],
        'refunds' => ['amount'],
    ];

    public static function acceptedPrices(): array
    {
        return [[0], [50000000], ['50000000.00'], ['99999999.00']];
    }

    #[DataProvider('acceptedPrices')]
    public function test_data03_store_and_update_accept_integral_idr_within_decimal_schema(int|string $price): void
    {
        $payload = $this->productPayload();
        $payload['product_price'] = $price;
        $this->postJson(route('admin.products.store'), $payload)->assertRedirect();
        $product = Product::where('product_slug', $payload['product_slug'])->firstOrFail();
        $this->assertSame((int) $price, (int) $product->product_price);

        $this->patchJson(route('admin.products.update', $product->product_id), [
            'product_price' => $price,
        ])->assertRedirect();
        $this->assertSame((int) $price, (int) $product->fresh()->product_price);
    }

    public static function rejectedProductInputs(): array
    {
        return [
            'fraction' => ['product_price', '125000.50'],
            'small fraction' => ['product_price', '0.01'],
            'above decimal precision' => ['product_price', '100000000'],
            'negative' => ['product_price', '-1'],
            'image above varchar capacity' => ['product_image', 'https://example.test/'.str_repeat('a', 235)],
        ];
    }

    #[DataProvider('rejectedProductInputs')]
    public function test_data03_store_and_update_reject_unrepresentable_input_without_mutation(string $field, string $value): void
    {
        $payload = $this->productPayload();
        $product = Product::create($payload + ['product_brand' => 'ZAFIRA']);
        $before = $product->fresh()->getRawOriginal();
        $payload['product_slug'] = 'rejected-rem-product';
        $payload[$field] = $value;

        $this->postJson(route('admin.products.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseMissing('products', ['product_slug' => 'rejected-rem-product']);
        $this->patchJson(route('admin.products.update', $product->product_id), [$field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $product->fresh()->getRawOriginal());
    }

    public function test_data03_image_exactly_255_characters_round_trips(): void
    {
        $payload = $this->productPayload();
        $payload['product_image'] = 'https://example.test/'.str_repeat('a', 234);
        $this->assertSame(255, strlen($payload['product_image']));
        $this->postJson(route('admin.products.store'), $payload)->assertRedirect();
        $product = Product::where('product_slug', $payload['product_slug'])->firstOrFail();
        $this->assertSame($payload['product_image'], $product->product_image);
        $this->patchJson(route('admin.products.update', $product->product_id), [
            'product_image' => $payload['product_image'],
        ])->assertRedirect();
        $this->assertSame($payload['product_image'], $product->fresh()->product_image);
    }

    public function test_data02_additive_migration_widens_every_return_and_refund_money_column(): void
    {
        $migration = $this->moneyMigration();
        $seen = [];
        Schema::shouldReceive('table')->andReturnUsing(function (string $table, callable $callback) use (&$seen): void {
            $blueprint = new Blueprint(DB::connection(), $table);
            $callback($blueprint);
            foreach ($blueprint->getColumns() as $column) {
                $seen[$table][$column->name] = [$column->type, $column->unsigned, $column->change];
            }
        });
        $migration->up();

        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame(['bigInteger', true, true], $seen[$table][$column] ?? null, "$table.$column must widen in place.");
            }
        }
    }

    public static function overflowingColumns(): array
    {
        return [
            ['returns', 'requested_refund_amount'],
            ['return_items', 'unit_price'],
            ['return_items', 'subtotal'],
            ['refunds', 'amount'],
        ];
    }

    #[DataProvider('overflowingColumns')]
    public function test_data02_down_preflights_all_tables_before_first_ddl(string $unsafeTable, string $unsafeColumn): void
    {
        $migration = $this->moneyMigration();
        // A later-table overflow must not leave earlier columns narrowed on MySQL.
        Schema::shouldReceive('table')->never();
        $this->mockDatabase();
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            $query = Mockery::mock(Builder::class);
            DB::shouldReceive('table')->with($table)->andReturn($query);
            foreach ($columns as $column) {
                $query->shouldReceive('where')->with($column, '>', 4294967295)
                    ->andReturnUsing(function () use ($table, $column, $unsafeTable, $unsafeColumn) {
                        $probe = Mockery::mock();
                        $probe->shouldReceive('exists')->andReturn($table === $unsafeTable && $column === $unsafeColumn);

                        return $probe;
                    });
            }
        }
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_data02_down_allows_uint32_boundary_after_all_preflights(): void
    {
        $migration = $this->moneyMigration();
        $checked = [];
        $this->mockDatabase();
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            $query = Mockery::mock(Builder::class);
            DB::shouldReceive('table')->with($table)->andReturn($query);
            foreach ($columns as $column) {
                $probe = Mockery::mock();
                $probe->shouldReceive('exists')->once()->andReturnUsing(function () use (&$checked, $table, $column): bool {
                    $checked[] = "$table.$column";

                    return false;
                });
                $query->shouldReceive('where')->with($column, '>', 4294967295)->once()->andReturn($probe);
            }
        }
        $narrowed = [];
        Schema::shouldReceive('table')->andReturnUsing(function (string $table, callable $callback) use (&$checked, &$narrowed): void {
            $this->assertCount(4, $checked, 'All capacity checks must finish before the first DDL.');
            $blueprint = new Blueprint($this->originalDatabaseManager->connection(), $table);
            $callback($blueprint);
            foreach ($blueprint->getColumns() as $column) {
                $narrowed[$table][$column->name] = [$column->type, $column->unsigned, $column->change];
            }
        });
        $migration->down();
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame(['integer', true, true], $narrowed[$table][$column] ?? null);
            }
        }
    }

    public static function nullableRollbackCases(): array
    {
        return [
            'return owner' => ['2026_10_20_000005_protect_returns_and_refunds_from_user_delete.php', 'returns', 'user_id'],
            'refund owner' => ['2026_10_20_000005_protect_returns_and_refunds_from_user_delete.php', 'refunds', 'user_id'],
            'concierge date' => ['2026_09_07_125000_make_concierge_schedule_nullable.php', 'concierge_requests', 'preferred_date'],
            'concierge time' => ['2026_09_07_125000_make_concierge_schedule_nullable.php', 'concierge_requests', 'preferred_time'],
        ];
    }

    #[DataProvider('nullableRollbackCases')]
    public function test_data04_any_nullable_column_blocks_rollback_before_first_ddl(string $file, string $unsafeTable, string $unsafeColumn): void
    {
        $migration = require database_path('migrations/'.$file);
        Schema::shouldReceive('table')->never();
        $this->mockDatabase();
        $targets = str_contains($file, 'concierge')
            ? ['concierge_requests' => ['preferred_date', 'preferred_time']]
            : ['returns' => ['user_id'], 'refunds' => ['user_id']];
        foreach ($targets as $table => $columns) {
            $query = Mockery::mock(Builder::class);
            DB::shouldReceive('table')->with($table)->andReturn($query);
            foreach ($columns as $column) {
                $probe = Mockery::mock();
                $probe->shouldReceive('exists')->andReturn($table === $unsafeTable && $column === $unsafeColumn);
                $query->shouldReceive('whereNull')->with($column)->andReturn($probe);
            }
        }
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_data08_homepage_product_query_has_an_explicit_bound(): void
    {
        Cache::flush();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (preg_match('/\bfrom\s+["`]?products["`]?\b/i', $query->sql)) {
                $queries[] = $query->sql;
            }
        });
        app(StorefrontController::class)->index();
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/\blimit\s+[1-9][0-9]*\b/i', $sql, 'Homepage must not select the entire active catalogue.');
        }
    }

    public function test_data08_sitemap_streams_instead_of_building_the_whole_document(): void
    {
        // Streaming is the selected no-view-edit contract, not a latency benchmark.
        $response = app(StorefrontController::class)->sitemap();
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_data08_category_invalidation_does_not_hydrate_products_or_flush_unrelated_cache(): void
    {
        $category = Category::create(['cat_title' => 'REM category']);
        Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'REM product', 'product_slug' => 'rem-invalidation', 'product_price' => 100,
        ]);
        Cache::put('rem:unrelated:rate-limit', 7, 300);
        $hydrated = 0;
        Product::retrieved(function () use (&$hydrated): void {
            $hydrated++;
        });
        AdminCategoryController::flushStorefrontCache();
        $this->assertSame(0, $hydrated, 'Invalidation must not hydrate the catalogue.');
        $this->assertSame(7, Cache::get('rem:unrelated:rate-limit'));
    }

    public function test_data08_homepage_is_limited_but_product_details_remain_accessible(): void
    {
        $category = Category::create(['cat_title' => 'REM featured bound']);
        for ($index = 1; $index <= StorefrontController::FEATURED_PRODUCT_LIMIT + 1; $index++) {
            Product::create([
                'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
                'product_title' => "Featured {$index}", 'product_slug' => "featured-{$index}",
                'product_price' => 100, 'is_active' => true,
            ]);
        }
        $view = app(StorefrontController::class)->index();
        $this->assertCount(StorefrontController::FEATURED_PRODUCT_LIMIT, $view->getData()['products']);
        $this->assertSame('featured-1', $view->getData()['products']->first()['id']);
        $last = StorefrontController::FEATURED_PRODUCT_LIMIT + 1;
        $this->assertSame("featured-{$last}", app(StorefrontController::class)->show("featured-{$last}")->getData()['product']->product_slug);
    }

    public function test_data08_streamed_sitemap_preserves_all_active_urls_and_xml_fields_across_batches(): void
    {
        $category = Category::create(['cat_title' => 'REM sitemap']);
        $rows = [];
        for ($index = 1; $index <= 501; $index++) {
            $rows[] = [
                'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
                'product_title' => "Sitemap {$index}", 'product_slug' => $index === 501 ? 'sitemap-501&atelier' : "sitemap-{$index}",
                'product_price' => 100, 'is_active' => true,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('products')->insert($chunk);
        }
        DB::table('products')->insert([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Hidden', 'product_slug' => 'hidden-rem-sitemap',
            'product_price' => 100, 'is_active' => false,
        ]);
        // Invalid active records occur after the first batch; output must still close.
        foreach ([null, '', '   '] as $slug) {
            DB::table('products')->insert([
                'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
                'product_title' => 'Non-routable', 'product_slug' => $slug,
                'product_price' => 100, 'is_active' => true,
            ]);
        }
        $response = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml');
        $xml = $response->streamedContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertSame(502, substr_count($xml, '<loc>'));
        foreach (array_merge([route('home')], array_map(fn ($row) => route('products.show', $row['product_slug']), $rows)) as $url) {
            $this->assertStringContainsString('<loc>'.htmlspecialchars($url, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8').'</loc>', $xml);
        }
        $this->assertStringNotContainsString('hidden-rem-sitemap', $xml);
        $this->assertStringContainsString('sitemap-501&amp;atelier', $xml);
        $this->assertStringNotContainsString('sitemap-501&atelier', $xml);
        $this->assertSame(502, substr_count($xml, '<lastmod>'.now()->toDateString().'</lastmod>'));
        $this->assertSame(502, substr_count($xml, '<changefreq>weekly</changefreq>'));
        $this->assertSame(502, substr_count($xml, '<priority>0.8</priority>'));
        $this->assertStringEndsWith('</urlset>', $xml);
    }

    public function test_data08_category_rename_refreshes_home_and_related_category_labels(): void
    {
        $payload = $this->productPayload();
        $product = Product::create($payload + ['product_brand' => 'ZAFIRA', 'is_active' => true]);
        $other = Product::create(array_replace($payload, [
            'product_slug' => 'rem-related', 'product_brand' => 'ZAFIRA', 'is_active' => true,
        ]));
        $controller = app(StorefrontController::class);
        $this->assertSame('REM product validation', $controller->index()->getData()['products']->first()['category']);
        $this->assertSame('REM product validation', $controller->show($product->product_slug)->getData()['related']->first()->category->cat_title);
        $revision = AdminCategoryController::storefrontRevision();
        Cache::put('rem:unrelated:rate-limit', 7, 300);
        $this->patchJson(route('admin.categories.update', $payload['product_cat']), ['cat_title' => 'Renamed REM category'])->assertRedirect();
        $this->assertNotSame($revision, AdminCategoryController::storefrontRevision());
        $this->assertSame('Renamed REM category', $controller->index()->getData()['products']->first()['category']);
        $related = $controller->show($product->product_slug)->getData()['related']->first();
        $this->assertSame($other->product_id, $related->product_id);
        $this->assertSame('Renamed REM category', $related->category->cat_title);
        $this->assertSame(7, Cache::get('rem:unrelated:rate-limit'));
    }

    private function productPayload(): array
    {
        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::firstOrFail(), 'admin');
        $category = Category::create(['cat_title' => 'REM product validation']);

        return [
            'product_title' => 'REM integral IDR', 'product_slug' => 'rem-integral-idr',
            'product_cat' => $category->cat_id, 'product_price' => '125000.00',
            'product_image' => 'https://example.test/product.jpg',
        ];
    }

    private function moneyMigration(): object
    {
        // Proposed additive migration contract; do not rewrite historical create migrations.
        $file = database_path('migrations/2026_10_21_000050_widen_return_refund_money_columns.php');
        $this->assertFileExists($file, 'DATA02 requires a new additive migration after the existing migration tail.');

        return require $file;
    }
}
