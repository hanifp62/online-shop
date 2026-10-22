<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // All tables must pass before any FK/NOT NULL DDL can auto-commit.
        foreach (['returns', 'refunds'] as $table) {
            if (DB::table($table)->whereNull('user_id')->exists()) {
                throw new RuntimeException("Cannot restore NOT NULL {$table}.user_id while ownerless rows exist.");
            }
        }

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
