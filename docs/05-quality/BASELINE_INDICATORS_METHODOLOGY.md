# Metodologi & Indikator Baseline ZAFIRA

**Tujuan:** Menetapkan metrik acuan dan prosedur pengambilan yang dapat direproduksi sebelum/sesudah perbaikan kode atau refaktor arsitektur.  
**Skrip Eksekusi:** `bash scripts/benchmarks/capture_baseline.sh`  
**Laporan Terkini:** `scripts/benchmarks/baseline_report.txt`  
**Arsip Bertanggal:** `docs/05-quality/baselines/baseline_<tanggal>.txt` (satu berkas per eksekusi, dipertahankan untuk perbandingan sebelum/sesudah)  
**Status Bukti:** Terverifikasi per 2026-10-20.

---

## 1. Taksonomi Indikator

| Kategori | Indikator | Definisi & Satuan | Metode Pengambilan | Nilai Acuan (2026-10-20) | Ambang Batas Regresi |
|---|---|---|---|---|---|
| **Kuantitas Kode** | `routes` | Jumlah total route HTTP yang terdaftar | `php artisan route:list --json` | 87 | Tidak boleh turun tanpa ADR |
| | `migrations` | Jumlah file migrasi terdaftar | `ls database/migrations \| wc -l` | 43 | Monoton naik |
| | `php_files` | Jumlah file PHP aplikasi | `find app -name '*.php'` | 83 | — |
| | `views` | Jumlah file template Blade | `find resources/views -name '*.blade.php'` | 35 | — |
| | `test_files` | Jumlah file test PHPUnit | `find tests -name '*Test.php'` | 97 | Monoton naik |
| **Kualitas & Mutu** | `phpunit_tests` | Jumlah test PHPUnit lulus | `vendor/bin/phpunit` | 791 passing, 4 skipped | Wajib 0 failure |
| | `phpunit_assertions` | Jumlah asersi terverifikasi | `vendor/bin/phpunit` | 6.338 asersi | Monoton naik |
| | `node_tests` | Jumlah unit test JS/MJS lulus | `node --test tests/*.test.mjs` | 290 passing | Wajib 0 failure |
| **Aset & Bundle** | `build_assets_bytes` | Ukuran total aset produksi di `public/build` | `find public/build -type f` | ~135 KB (uncompressed) | Maks +20% per PR tanpa izin |
| **Kesehatan Runtime** | `health_endpoint` | Respons HTTP `/health` | `curl -f http://127.0.0.1:8000/health` | HTTP 200, status `ok` | Wajib 200 |
| **Keamanan Routing** | `admin_guarded_routes` | Route admin dengan auth+TOTP+Gate | `AuthorizationInventoryTest` | 100% dari 18 route admin terlindungi | Wajib 100% |

---

## 2. Prosedur Pengambilan Data (Reproducible)

### Langkah 1: Pengambilan Lengkap Otomatis
```bash
bash scripts/benchmarks/capture_baseline.sh
```
Skrip ini membaca routing table, menghitung file struktural, mengukur ukuran build aset Vite, mengekstrak status PHPUnit dari log JUnit, dan memeriksa node test suite.

### Langkah 2: Verifikasi Uji Regresi
```bash
# Backend suite
php -d memory_limit=1G vendor/bin/phpunit

# Frontend JS invariant suite
node --test tests/*.test.mjs

# Linter diff
git diff --check

# Produksi aset
npm run build
```

---

## 3. Aturan Evaluasi Sebelum/Sesudah Perubahan
1. **Aturan Nol Regresi:** Setiap perubahan fitur tidak boleh menurunkan jumlah test lulus atau memunculkan kegagalan baru pada PHPUnit maupun Node runner.
2. **Aturan Pagar Otorisasi:** Penambahan route baru pada `admin/*` harus mempertahankan rasio 100% ber-Gate dan ber-TOTP (`AuthorizationInventoryTest`).
3. **Penyimpanan Laporan:** Setiap perbaikan berskala besar mencatat cuplikan `baseline_report.txt` pada log delivery tracker.
