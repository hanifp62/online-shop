<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_r11_rollback_refusal_preserves_cart_tables_and_rows(): void
    {
        $category = Category::firstOrCreate(['cat_title' => 'Rollback']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra', 'product_slug' => 'rollback-cart', 'product_price' => '125000.00']);
        $cartId = DB::table('carts')->insertGetId(['token_hash' => hash('sha256', 'rollback-test'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => $product->product_id, 'quantity' => 2]);
        $carts = DB::table('carts')->get()->all();
        $items = DB::table('cart_items')->get()->all();

        $migration = require database_path('migrations/2026_10_19_000002_create_carts_tables.php');
        try {
            $migration->down();
            $this->fail('Expected rollback refusal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot roll back persistent carts while cart rows exist.', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('carts'));
        $this->assertTrue(Schema::hasTable('cart_items'));
        $this->assertEquals($carts, DB::table('carts')->get()->all());
        $this->assertEquals($items, DB::table('cart_items')->get()->all());
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cartId, 'product_id' => $product->product_id, 'quantity' => 2]);
    }

    public function test_r11_rollback_refuses_when_cart_row_exists_without_items(): void
    {
        DB::table('carts')->insert(['created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_10_19_000002_create_carts_tables.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back persistent carts while cart rows exist.');
        $migration->down();
    }

    public function test_r11_rollback_refuses_and_preserves_orphaned_cart_items(): void
    {
        $category = Category::firstOrCreate(['cat_title' => 'Orphan']);
        $product = Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra', 'product_slug' => 'orphan-item', 'product_price' => '125000.00']);
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dropping the FK is only isolated on transactional-DDL SQLite.');
        }
        Schema::table('cart_items', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->dropForeign(['cart_id']));
        $cartId = 999;
        DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => $product->product_id, 'quantity' => 3]);
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $migration = require database_path('migrations/2026_10_19_000002_create_carts_tables.php');

        try {
            $migration->down();
            $this->fail('Expected rollback refusal for orphaned cart_items.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot roll back persistent carts while cart rows exist.', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('cart_items'));
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cartId, 'product_id' => $product->product_id, 'quantity' => 3]);
    }

    public function test_r11_empty_rollback_drops_both_tables(): void
    {
        // SQLite DDL is transactional, so RefreshDatabase's rollback restores both tables; MySQL DDL auto-commits.
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Destructive schema rollback is only isolated on transactional-DDL SQLite.');
        }
        $migration = require database_path('migrations/2026_10_19_000002_create_carts_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('cart_items'));
        $this->assertFalse(Schema::hasTable('carts'));
    }
}
