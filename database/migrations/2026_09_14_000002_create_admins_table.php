<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            // Pemetaan dump `admin_info`: admin_name -> name,
            // admin_email -> email, admin_password (MD5) -> password (bcrypt).
            $table->string('name', 100);
            $table->string('email', 300)->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
