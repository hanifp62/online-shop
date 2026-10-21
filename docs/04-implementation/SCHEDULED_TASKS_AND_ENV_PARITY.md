# Tugas Terjadwal dan Paritas Lingkungan

## Tugas terjadwal

| Perintah | Frekuensi | Tujuan |
|---|---|---|
| `carts:prune` | Harian | Pangkas keranjang tamu kedaluwarsa/terpakai. |
| `cart-reminders:send` | Per jam | Kirim pengingat keranjang. |
| `payments:prune-dead-letters` | Harian | Hapus dead-letter pembayaran selesai yang lama. |
| `payments:replay-dead-letters` | Tiap 5 menit | Putar ulang notifikasi pembayaran gagal secara terbatas. |
| `reservations:release` | Tiap menit | Lepaskan reservasi stok kedaluwarsa. |
| `db:backup --verify` (SQLite) / `db:backup` (MySQL/MariaDB) | 02:00 setiap hari | Cadangkan basis data dan terapkan retensi. Uji pulih otomatis hanya untuk SQLite; uji pulih MySQL/MariaDB belum didukung dan tidak pernah dilaporkan lulus. |

Daftarkan cron berikut pada host aplikasi:

```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

`payments:replay-dead-letters`, `reservations:release`, dan `db:backup` memakai `withoutOverlapping`; backup juga memakai `onOneServer`. Jalankan queue worker untuk pekerjaan antrean, misalnya `php artisan queue:work --tries=3`, dengan supervisor/systemd yang menjaga proses tetap hidup.

## Paritas konfigurasi

`tests/Feature/ScheduledTasksAndEnvParityTest.php` membandingkan semua literal `env()` pada `config/*.php` dengan kunci di `.env.example`. Kunci produksi seperti `APP_KEY`, `DB_*`, `MIDTRANS_SERVER_KEY`, `MAIL_*`, dan `BACKUP_*` wajib diisi sesuai secret manager produksi; `.env.example` hanya berisi placeholder aman.

Prosedur lokal, staging, dan produksi:

1. Jalankan `cp .env.example .env` hanya untuk lingkungan lokal, lalu isi nilai lokal dan `php artisan key:generate`.
2. Jalankan `php artisan config:clear`, migrasi sesuai prosedur lingkungan, dan `php artisan test tests/Feature/ScheduledTasksAndEnvParityTest.php`.
3. Staging dan produksi mengambil secret dari secret manager, bukan dari Git. Bandingkan nama kunci dengan `.env.example`, tanpa menyalin nilai rahasia.
4. Verifikasi `php artisan schedule:list`, cron host, queue worker, dan akses direktori backup pada staging sebelum rilis.

Batas: tes memeriksa keberadaan dan kontrak jadwal secara kode; staging/produksi nyata belum diverifikasi oleh agent ini.
