<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('customer_name', 120)->nullable()->after('user_id');
            $table->string('customer_email', 255)->nullable()->after('customer_name');
            $table->string('customer_phone', 30)->nullable()->after('customer_email');
            $table->string('guest_scope_hash', 64)->nullable()->after('idempotency_key');
            $table->string('tracking_token_hash', 64)->nullable()->unique('orders_tracking_token_hash_unique')->after('payload_hash');
            $table->timestamp('tracking_expires_at')->nullable()->after('tracking_token_hash');

            $table->unique(['guest_scope_hash', 'idempotency_key'], 'orders_guest_scope_idempotency_unique');
        });
    }

    public function down(): void
    {
        // Refuse instead of deleting guest orders: user_id cannot become non-null while they exist.
        if (DB::table('orders')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Cannot roll back guest checkout while orders with null user_id exist.');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_guest_scope_idempotency_unique');
            $table->dropUnique('orders_tracking_token_hash_unique');
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone',
                'guest_scope_hash',
                'tracking_token_hash',
                'tracking_expires_at',
            ]);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
