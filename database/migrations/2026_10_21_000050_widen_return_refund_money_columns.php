<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONEY_COLUMNS = [
        'returns' => ['requested_refund_amount'],
        'return_items' => ['unit_price', 'subtotal'],
        'refunds' => ['amount'],
    ];

    public function up(): void
    {
        foreach (self::MONEY_COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column) {
                    $definition = $table->unsignedBigInteger($column);
                    if ($tableName === 'returns') {
                        $definition->default(0);
                    }
                    $definition->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Run with application writers quiesced: MySQL DDL can auto-commit,
        // and a transaction cannot close the check-to-ALTER race across tables.
        foreach (self::MONEY_COLUMNS as $tableName => $columns) {
            foreach ($columns as $column) {
                if (DB::table($tableName)->where($column, '>', 4294967295)->exists()) {
                    throw new RuntimeException("Cannot narrow {$tableName}.{$column}: amount exceeds unsigned INTEGER capacity.");
                }
            }
        }

        foreach (self::MONEY_COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column) {
                    $definition = $table->unsignedInteger($column);
                    if ($tableName === 'returns') {
                        $definition->default(0);
                    }
                    $definition->change();
                }
            });
        }
    }
};
