<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_notifications', function (Blueprint $table) {
            // Outbox lease: null means ready/never attempted. A token claims the row;
            // expires_at bounds the claim so a crashed worker cannot starve the row.
            $table->string('lease_token', 40)->nullable()->after('dispatched_at');
            $table->timestamp('lease_expires_at')->nullable()->after('lease_token');
        });
    }

    public function down(): void
    {
        Schema::table('order_notifications', function (Blueprint $table) {
            $table->dropColumn(['lease_expires_at', 'lease_token']);
        });
    }
};
