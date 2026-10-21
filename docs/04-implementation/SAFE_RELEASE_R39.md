# Rilis Aman (R-39) — Feature Flag, Canary/Cohort, Rollback

> Status: **TIDAK TERBUKTI DI PRODUKSI**. Bukti lokal terukur; deploy produksi belum
> dijalankan dan diasumsikan tidak ada akses produksi.

## Ringkasan

Menambahkan pemilih fitur deterministik berbasis konfigurasi (bukan library pihak
ketiga) untuk menahan risiko rilis: perilaku dapat dinyalakan bertahap (cohort
persentase), dimatikan seketika (kill switch), tanpa migrasi database dan tanpa
deploy ulang.

## Requirement terkait

| ID | Requirement | Status bukti |
| --- | --- | --- |
| FR-R39-01 | Sistem menyediakan saklar fitur (feature flag) berbasis konfigurasi/env dengan kill switch. | Terverifikasi (uji unit + drill lokal) |
| FR-R39-02 | Rilis bertahap mendukung cohort persentase deterministik (canary). | Terverifikasi (uji distribusi) |
| NFR-R39-01 | Waktu rollback (mematikan fitur) < 5 menit, tanpa migrasi data. | Terverifikasi lokal (≈0.2 s + deploy) |
| NFR-R39-02 | Fitur yang sudah ada tetap aktif saat konfigurasi flag absen. | Terverifikasi (default `enabled=true`, `rollout=100`) |
| NFR-R39-03 | Migrasi harus terkendali (expand/contract), tanpa perintah destruktif. | Terverifikasi (runbook + uji kontrak) |
| NFR-R39-04 | Konfigurasi rusak gagal tertutup (fitur mati), tidak menggugurkan aplikasi. | Terverifikasi (uji batas) |

## Acceptance Criteria (dapat diuji)

- **AC1** `FeatureFlags::enabled('missing', ...)` → `false` (flag tak dikenal mati).
- **AC2** `enabled=true`, `rollout=100` → aktif; `rollout=0` → mati; `enabled=false`
  → mati apa pun `rollout` (kill switch menang).
- **AC3** `rollout` di luar `0..100` atau bukan integer (`-1`, `101`, `"abc"`) → mati.
- **AC4** Bucket stabil: hasil sama untuk subjek sama (deterministik); rollout naik
  tidak menendang pengguna yang sudah masuk (nested).
- **AC5** Middleware `feature:<flag>` mengembalikan `404` (bukan `500`) saat mati.
- **AC6** Route `admin.reports.sales-summary` memakai flag DAN tetap menuntut
  `auth:admin` + `admin.can:view-reports` (otorisasi tidak dikesampingkan).

## Desain

- `config/features.php` — definisi flag dari env:
  `ZAFIRA_FEATURE_<NAMA>_ENABLED` (bool) dan `ZAFIRA_FEATURE_<NAMA>_ROLLOUT` (0–100).
- `app/Support/FeatureFlags.php` — bucket deterministik `crc32(flag:subject) % 100`
  dibanding `rollout`. **Bukan** mekanisme keamanan/otorisasi.
- `app/Http/Middleware/FeatureFlag.php` — alias `feature:<flag>`; subjek = id admin
  terautentikasi (fallback nama flag).
- Pemakaian nyata: `routes/web.php` → `admin.reports.sales-summary`.

### Mengapa `crc32`, bukan hashing aman

Cukup untuk membelah lalu lintas ke bucket stabil; dipakai untuk *sampling*, bukan
untuk kriptografi/otorisasi. Butuh acak yang tak dapat diprediksi (mis. anti-abuse)?
Ganti ke HMAC atas nilai rahasia — di luar cakupan R-39.

## Runbook rollback (< 5 menit)

1. Set env `ZAFIRA_FEATURE_ADMIN_SALES_SUMMARY_ENABLED=false` (atau `..._ROLLOUT=0`).
2. `php artisan config:clear` lalu `php artisan config:cache` (produksi).
3. Verifikasi: sebagai admin dengan izin `view-reports`, `GET
   /admin/reports/sales-summary` mengembalikan 404 bagi cohort yang dimatikan.

Tidak ada langkah database. Worker yang berjalan lama (queue, Octane, PHP-FPM dengan
opcache yang tidak memvalidasi timestamp) perlu di-reload agar membaca cache baru —
**TIDAK TERBUKTI** pada lingkungan produksi.

## Migrasi terkendali

- Pola **expand/contract**: tambah kolom baru (nullable/default) tanpa menghapus
  kolom lama pada langkah yang sama; hapus hanya setelah konsumen lama berhenti
  memakainya.
- Selalu `php artisan migrate --pretend` sebelum `migrate` untuk meninjau SQL.
- Dilarang `migrate:fresh`/`db:wipe`/`DROP TABLE` pada data produksi.

## Evaluasi (lokal, terukur)

| Perintah | EXIT | Hasil | Durasi |
| --- | --- | --- | --- |
| `php artisan test tests/Feature/FeatureFlagsTest.php tests/Feature/AdminSalesSummaryTest.php` | 0 | 6 passed (1263 assertions) | 0.22 s |
| `php artisan test` (seluruh suite) | 0 | 933 passed, 4 skipped (25849 assertions) | 40.38 s |
| `vendor/bin/pint --test` | 0 | passed | 0.35 s |
| Drill rollback `config:cache` + baca state (env `false`/`true`) | 0 | state `disabled`/`enabled` | 0.184–0.196 s |

### RED → GREEN (TDD)

- RED: `FeatureFlagsTest` 4 failed, EXIT=2 (kelas/servis belum ada).
- Setelah implementasi: 6 passed, EXIT=0.

## Batasan / Tidak diperiksa

- **Tidak ada deploy produksi** — rollback < 5 menit dibuktikan secara lokal saja;
  waktu nyata bergantung pipeline deploy, bukan perubahan kode ini.
- Tidak menambah perintah artisan `feature:status` (opsional; bisa menyusul).
- Tidak ada papan admin untuk mengubah flag saat runtime (perubahan lewat env →
  `config:cache`).
- `/health` tidak diubah (tidak mengekspos versi/state flag).
- Bucket memakai `crc32`, bukan HMAC; lihat catatan "Mengapa crc32".
