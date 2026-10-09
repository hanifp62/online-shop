<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique constraint untuk product_slug (route-model binding
     * /produk/{product:product_slug} bergantung pada keunikan ini).
     * Kolom nullable: MySQL/MariaDB dan SQLite mengizinkan banyak NULL
     * dalam unique index, jadi data lama yang slug-nya NULL tetap valid.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('product_slug', 'products_product_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_product_slug_unique');
        });
    }
};
