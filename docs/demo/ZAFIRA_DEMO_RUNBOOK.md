# ZAFIRA AMADIS — Demo Runbook

Tanggal: 2026-10-12 (catatan 13 Oktober: run terisolasi AM-01/02/06/10 telah dieksekusi, lihat `docs/execution/evidence/2026-10-13/acceptance-am-isolated/REPORT.md`; aturan di bawah tetap berlaku). Runbook ini menjalankan demo **hanya pada SQLite terisolasi**. Jangan jalankan pada database operasional.

## Aturan keras

- Jangan baca atau ubah `.env`.
- Jangan gunakan port 8000 atau 8001.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, `DROP TABLE`, atau operasi destruktif lain.
- Jangan menjalankan artisan/test sebelum isolation launcher siap.
- Fixture seluruhnya diberi label `DEMO`; data sintetis bukan inventori nyata.
- Seeder sengaja menolak database yang berisi data non-demo.
- Seeder tidak dimasukkan ke `DatabaseSeeder.php`.

## Prasyarat launcher

Launcher parent wajib membuat direktori kanonis dengan pola:

`/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-<unique>/`

Launcher wajib membuat `identity.json` berisi `root` dan `database`, lalu membuat file SQLite pada `database.sqlite`. Launcher juga wajib menyiapkan env runtime yang disetujui:

- `AMADIS_ROOT=<canonical-root>`
- `AMADIS_DATABASE=<canonical-root>/database.sqlite`
- `DB_CONNECTION=sqlite`
- `DB_URL=` kosong
- `DB_DATABASE=<canonical-root>/database.sqlite`

Gunakan `scripts/amadis/guard.php` sebagai guard; jangan menyalin nilai rahasia.

## Integrasi seeder

Jangan edit `database/seeders/DatabaseSeeder.php`. Parent menjalankan seeder secara eksplisit setelah launcher dan migration selesai:

```sh
php artisan db:seed --class='Database\Seeders\AmadisDemoSeeder'
```

Sebelum command itu, parent harus membuktikan env/identity guard cocok. Sesudahnya, verifikasi read-only jumlah kategori, produk aktif, dan stok slug AM-10. Jika command belum dijalankan, status tetap NOT RUN.

## Urutan demo

1. Buat SQLite terisolasi dan identity file.
2. Jalankan migration yang disetujui pada DB tersebut.
3. Jalankan `AmadisDemoSeeder` eksplisit.
4. Verifikasi invariant: 76 produk, 4 kategori, semuanya active, 19 per kategori, `amadis-demo-stock-37` stock 37.
5. Jalankan smoke flow katalog dan PDP.
6. Jalankan flow cart/checkout dengan user sintetis.
7. Jalankan AM-10 dengan key unik dan concurrency yang ditentukan rubrik induk.
8. Ulangi seeder. Pastikan count tetap 76 dan stok AM-10 tidak otomatis kembali 37 setelah eksperimen.
9. Simpan hanya log/screenshot tanpa token atau data personal.
10. Isi matrix dan report dengan output aktual. Jangan mengubah NOT RUN menjadi PASS tanpa bukti.

## Cleanup

Hapus hanya root temp milik launcher setelah bukti disalin secara aman dan operator menyetujui cleanup. Jangan memakai `rm -rf` pada path yang tidak diverifikasi canonical. Jangan cleanup jika masih ada proses menggunakan DB.

## Troubleshooting

- `Isolation rejected`: hentikan. Periksa identity, realpath, driver, dan database path; jangan bypass guard.
- `non-demo catalog exists`: buat DB isolasi baru. Jangan hapus rows.
- `invariant failed`: hentikan presentasi dan catat FAIL; jangan memperbaiki dengan rerun berulang.
- `database is locked`: catat sebagai hasil AM-10 SQLite; jangan klaim engine produksi.

## Integrasi parent

Parent bertanggung jawab atas isolation launcher, migration invocation, test invocation, browser verification, dan pengisian evidence. File ini hanya instruksi; belum ada command yang dieksekusi oleh penyusun dokumen.
