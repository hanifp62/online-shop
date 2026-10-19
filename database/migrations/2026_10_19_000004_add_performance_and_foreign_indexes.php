<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table => columns. Index name is "{table}_{column}_index".
     * FK columns here have no covering (leftmost) index on SQLite; MySQL already
     * auto-indexes FKs, so those are skipped there and down() never drops an FK's only index.
     *
     * @var array<string, list<string>>
     */
    public const INDEXES = [
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

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column) {
                $name = "{$tableName}_{$column}_index";
                if (Schema::hasColumn($tableName, $column)
                    && ! Schema::hasIndex($tableName, $name)
                    && ! $this->hasLeadingIndex($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->index($column, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column) {
                $name = "{$tableName}_{$column}_index";
                if (Schema::hasIndex($tableName, $name)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($name));
                }
            }
        }
    }

    private function hasLeadingIndex(string $tableName, string $column): bool
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }
};
