<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            // Baris lama tetap 'admin' agar tidak ada yang kehilangan akses; staff harus diberikan eksplisit.
            $table->string('role', 20)->default('admin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
