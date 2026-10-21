<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TOTP pelanggan bersifat opsional: kolom null = tidak aktif, login tetap satu langkah. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_enabled_at')->nullable();
            $table->unsignedBigInteger('totp_last_used_at')->nullable();
        });

        Schema::create('user_totp_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_totp_recovery_codes');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['totp_secret', 'totp_enabled_at', 'totp_last_used_at']);
        });
    }
};
