<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_required_at')->nullable()->index();
            $table->unsignedBigInteger('totp_last_used_at')->nullable();
        });

        Schema::create('admin_totp_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_totp_recovery_codes');
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropIndex(['totp_required_at']);
            $table->dropColumn(['totp_secret', 'totp_required_at', 'totp_last_used_at']);
        });
    }
};
