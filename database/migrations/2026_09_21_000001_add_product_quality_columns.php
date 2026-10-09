<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand: tambah kolom provenance kualitas produk. Semua nullable agar
     * data lama tetap valid (forward path aman tanpa backfill wajib).
     * Rollback: drop kolom (data kolom baru hilang, data inti utuh).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_material', 255)->nullable()->after('product_description');
            $table->string('product_composition', 500)->nullable()->after('product_material');
            $table->string('product_origin', 255)->nullable()->after('product_composition');
            $table->text('product_care')->nullable()->after('product_origin');
            $table->text('product_craft')->nullable()->after('product_care');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_material',
                'product_composition',
                'product_origin',
                'product_care',
                'product_craft',
            ]);
        });
    }
};
