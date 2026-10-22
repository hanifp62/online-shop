<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concierge_requests', function (Blueprint $table) {
            $table->date('preferred_date')->nullable()->change();
            $table->string('preferred_time', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (['preferred_date', 'preferred_time'] as $column) {
            if (DB::table('concierge_requests')->whereNull($column)->exists()) {
                throw new RuntimeException("Cannot restore NOT NULL concierge_requests.{$column} while unscheduled rows exist.");
            }
        }

        Schema::table('concierge_requests', function (Blueprint $table) {
            $table->date('preferred_date')->nullable(false)->change();
            $table->string('preferred_time', 20)->nullable(false)->change();
        });
    }
};
