# Rescue Final Isolation — Server 8015 (2026-10-11)

Tujuan: menjamin server QA di port 8015 terisolasi penuh (session file, cache, SQLite, views, log)
di direktori temp sebelum pengujian autentikasi, tanpa mengubah `.env` atau `config/*.php`.

## Status

ISOLASI TERVERIFIKASI. Server aktif dan dibiarkan berjalan untuk agen lain.

- Perintah start: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-qa/run/start-8015.sh`
- PID: lihat `server.pid` (76656 pada saat penulisan)
- Listener: `127.0.0.1:8015`

## Konfigurasi efektif (dibaca via bootstrap runtime, bukan asumsi)

| Aspek | Nilai |
|---|---|
| `database.default` | `sqlite` |
| `database.connections.sqlite.database` | `…/T/opencode/zafira-qa/db.sqlite` |
| `database.connections.sqlite.url` | `null` (dipaksa, agar `DB_URL` tidak menimpa) |
| `session.driver` | `file` |
| `session.files` | `…/T/opencode/zafira-qa/storage/framework/sessions` |
| `cache.default` | `array` |
| `storagePath()` | `…/T/opencode/zafira-qa/storage` |
| `view.compiled` | `…/T/opencode/zafira-qa/storage/framework/views` |
| `logging.channels.single.path` | `…/T/opencode/zafira-qa/storage/logs/laravel.log` |

Bukti: `effective-config-before-start.json`, `effective-config-after-start.json`.

## Mengapa bootstrap runtime, bukan hanya env

`config/session.php` baris 63 memakai `storage_path('framework/sessions')` yang tidak membaca
`SESSION_FILES`. Laravel 12 `Application::storagePath()` menghormati `LARAVEL_STORAGE_PATH`,
tetapi router `server.php` bawaan tidak memberi titik intervensi untuk `view.compiled`,
`logging`, maupun netralisasi `DB_URL`. Karena itu dipakai router temp
`run/router-isolated.php` yang memanggil `useStoragePath()` dan `afterBootstrapping(LoadConfiguration)`
untuk menetapkan nilai final sebelum service provider boot. Tidak ada file produk yang diubah.

## Verifikasi guest request

Satu `GET http://127.0.0.1:8015/` sebagai guest (tanpa cookie), HTTP 200, curl exit 0.

- Session temp: 1 → 2 berkas (file sesi baru dibuat di temp).
- Session proyek `storage/framework/sessions`: 669 → 669 berkas, hash
  `filename+mtime` identik → tidak ada tulisan ke storage proyek.

Perbandingan memakai nama berkas dan mtime saja; isi berkas sesi tidak dibaca.
Bukti: `filename-mtime-proof.json`, `guest-request-proof.txt`,
`guest-response-headers.txt` (header `Set-Cookie` dihapus), `temp-session-files-metadata.txt`.

## Tindakan terhadap server lama

PID 685 diverifikasi lebih dulu (`ps`, `lsof`, cwd `public/` repo ini) lalu dihentikan dengan
`kill -TERM`. Hanya proses itu yang dihentikan. Bukti: `old-server-ps-before.txt`.

## Batasan

- Hanya satu guest GET `/` yang diuji; rute lain belum diverifikasi pada sesi ini.
- `.env` tetap utuh; isolasi bergantung pada skrip start temp. Server yang dijalankan
  dengan `php artisan serve` biasa TIDAK terisolasi.
- Tidak ada migrasi, seeding, atau penulisan DB yang dijalankan.
