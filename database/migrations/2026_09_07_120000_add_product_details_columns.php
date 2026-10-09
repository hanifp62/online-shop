<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_slug')->nullable()->after('product_title');
            $table->string('product_badge')->nullable()->after('product_image');
            $table->string('product_color')->nullable()->after('product_badge');
            $table->string('product_alt')->nullable()->after('product_color');
            $table->text('product_description')->nullable()->after('product_alt');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_slug',
                'product_badge',
                'product_color',
                'product_alt',
                'product_description',
            ]);
        });
    }
};
