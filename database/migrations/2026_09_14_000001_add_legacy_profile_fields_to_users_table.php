<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            // Dump asli: mobile varchar(10) — terlalu pendek, dilebarkan ke 30
            // agar format +62 / spasi / strip tetap valid.
            $table->string('mobile', 30)->nullable()->after('email');
            // Dump asli: address1 varchar(300), address2 varchar(11) — address2
            // dilebarkan ke 255 agar alamat nyata tidak terpotong.
            $table->string('address1', 300)->nullable()->after('mobile');
            $table->string('address2', 255)->nullable()->after('address1');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'mobile', 'address1', 'address2']);
        });
    }
};
