<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_notifications', function (Blueprint $table) {
            // Outbox marker: null means the mail job was never confirmed pushed, so a retry may resend.
            $table->timestamp('dispatched_at')->nullable()->after('channel');
        });

        // Historical safety: backfill existing rows to created_at solely to prevent
        // an uncontrolled mass re-dispatch flood upon migration. Delivery of older rows
        // remains unproven; any historical gaps require separate manual audit/reconciliation.
        DB::table('order_notifications')->whereNull('dispatched_at')->update(['dispatched_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('order_notifications', function (Blueprint $table) {
            $table->dropColumn('dispatched_at');
        });
    }
};
