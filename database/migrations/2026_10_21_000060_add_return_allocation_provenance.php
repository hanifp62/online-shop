<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No backfill: null means unknown/legacy allocation, never inferred provenance.
        Schema::table('return_items', function (Blueprint $table) {
            $table->string('allocation_version', 32)->nullable();
            $table->unsignedInteger('allocation_quantity_before')->nullable();
            $table->unsignedBigInteger('allocation_amount_before')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('return_items')->where(function ($query) {
            $query->whereNotNull('allocation_version')
                ->orWhereNotNull('allocation_quantity_before')->orWhereNotNull('allocation_amount_before');
        })->exists()) {
            throw new RuntimeException('Rollback would discard return allocation provenance; reconcile before rollback.');
        }
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn(['allocation_version', 'allocation_quantity_before', 'allocation_amount_before']);
        });
    }
};
