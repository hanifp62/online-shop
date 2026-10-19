<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const INDEXES = [
        'orders' => ['status', 'created_at', 'voucher_id'],
        'products' => ['product_cat'],
        'order_items' => ['order_id', 'product_id'],
        'addresses' => ['user_id'],
        'reviews' => ['order_id', 'product_id'],
        'favorites' => ['product_id'],
        'concierge_requests' => ['user_id'],
        'admin_totp_recovery_codes' => ['admin_id'],
        'cart_items' => ['product_id'],
    ];

    public function test_up_creates_all_expected_indexes(): void
    {
        $allIndexes = array_merge(...array_values(self::INDEXES));
        $this->assertCount(13, $allIndexes);

        $migration = require database_path('migrations/2026_10_19_000004_add_performance_and_foreign_indexes.php');
        $migration->up();

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                $this->assertTrue(Schema::hasIndex($table, $name), "Missing index {$name}");
            }
        }
    }

    public function test_indexes_exist_after_migration_and_down_drops_them_cleanly(): void
    {
        $migration = require database_path('migrations/2026_10_19_000004_add_performance_and_foreign_indexes.php');
        $migration->up();

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                $this->assertTrue(
                    Schema::hasIndex($table, $name),
                    "Missing index {$name} on {$table} after up()."
                );
            }
        }

        $migration->down();

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                $this->assertFalse(
                    Schema::hasIndex($table, $name),
                    "Index {$name} on {$table} was not dropped by down()."
                );
            }
        }

        // Re-running down() must not error (idempotent, defensive drops).
        $migration->down();
        $this->assertTrue(true);
    }

    public function test_up_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_10_19_000004_add_performance_and_foreign_indexes.php');
        $migration->up();
        $migration->up();

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasIndex($table, "{$table}_{$column}_index"),
                    "Index missing after double up()."
                );
            }
        }
    }
}
