<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default mati: pengingat hanya untuk pelanggan yang memilih ikut (opt-in).
            $table->boolean('cart_reminder_opt_in')->default(false);
            $table->timestamp('cart_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cart_reminder_opt_in', 'cart_reminded_at']);
        });
    }
};
