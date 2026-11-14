<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('size', 32)->default('')->after('product_id');
            $table->string('monogram', 8)->default('')->after('size');
            $table->index('cart_id', 'cart_items_cart_id_idx');
            $table->dropUnique(['cart_id', 'product_id']);
            $table->unique(['cart_id', 'product_id', 'size', 'monogram'], 'cart_items_cart_product_variant_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size', 32)->nullable()->after('product_title');
            $table->string('monogram', 8)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        $hasCartVariants = DB::table('cart_items')
            ->where('size', '!=', '')
            ->orWhere('monogram', '!=', '')
            ->exists();
        $hasOrderVariants = DB::table('order_items')
            ->whereNotNull('size')
            ->orWhereNotNull('monogram')
            ->exists();

        if ($hasCartVariants || $hasOrderVariants) {
            throw new RuntimeException('Cannot roll back variant columns while variant cart or order items exist.');
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['size', 'monogram']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_product_variant_unique');
            $table->unique(['cart_id', 'product_id']);
            $table->dropIndex('cart_items_cart_id_idx');
            $table->dropColumn(['size', 'monogram']);
        });
    }
};
