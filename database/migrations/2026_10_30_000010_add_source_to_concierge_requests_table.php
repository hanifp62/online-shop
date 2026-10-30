<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FR-402 / research §12.3: count concierge requests that arrive through the guided assistant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concierge_requests', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('concierge_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
