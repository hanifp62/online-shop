<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Setara procedure dump `getcat(cid INT)`:
        //   SELECT * FROM categories WHERE cat_id = cid
        // Hanya dibuat untuk MySQL/MariaDB. SQLite (dipakai test lokal)
        // tidak mendukung stored procedure, jadi dilewati. Akses portable
        // tersedia via Category::getById($cid).
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'])) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE `getcat`(IN `cid` INT)
            SELECT * FROM `categories` WHERE `cat_id` = `cid`
            SQL);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'])) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
    }
};
