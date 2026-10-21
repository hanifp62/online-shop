<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Akses kategori hanya lewat Category::getById() (Eloquent, berparameter, teruji).
    // Procedure warisan `getcat` dihapus agar tidak ada jalur baca data di luar aplikasi.
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE `getcat`(IN `cid` INT)
            SELECT * FROM `categories` WHERE `cat_id` = `cid`
            SQL);
    }
};
