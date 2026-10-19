<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('carts')->exists() || DB::table('cart_items')->exists()) {
            throw new RuntimeException('Cannot roll back persistent carts while cart rows exist.');
        }

        Schema::withoutForeignKeyConstraints(function () {
            Schema::dropIfExists('cart_items');
            Schema::dropIfExists('carts');
        });
    }
};
