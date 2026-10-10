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

        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
            DB::unprepared(<<<'SQL'
                CREATE PROCEDURE `getcat`(IN `cid` INT)
                SELECT * FROM `categories` WHERE `cat_id` = `cid`
                SQL);
        } catch (Throwable $e) {
            // Jika mysql.proc di instance MySQL/MariaDB lokal memerlukan mysql_upgrade,
            // lewati pembuatan procedure tanpa menggagalkan migrasi, karena seluruh kode
            // aplikasi menggunakan Category::getById() yang 100% kompatibel.
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'])) {
            return;
        }

        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS `getcat`');
        } catch (Throwable $e) {
            // Abaikan jika procedure tidak ada atau mysql.proc tidak dapat diakses
        }
    }
};
