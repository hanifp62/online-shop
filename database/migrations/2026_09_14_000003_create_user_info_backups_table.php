<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_info_backups', function (Blueprint $table) {
            $table->id();
            // Pengganti tabel dump `user_info_backup` + trigger
            // `after_user_info_insert`. Diisi via UserObserver, bukan trigger
            // SQL, agar portable SQLite/MySQL dan ter-cover RefreshDatabase.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 300);
            $table->string('mobile', 30)->nullable();
            $table->string('address1', 300)->nullable();
            $table->string('address2', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_info_backups');
    }
};
