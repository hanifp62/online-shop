# Inventaris Nama Brand Lama "Amadis"

**Tanggal:** 2026-10-20
**Metode:** `grep -i amadis` atas seluruh pohon kerja (di luar `.git`, `vendor`, `node_modules`, `storage`), lalu `git ls-files` untuk memisahkan berkas terlacak dari artefak lokal.
**Hasil:** 508 berkas, ±4.800 kemunculan. Nol kemunculan di permukaan yang dilihat pelanggan.

## Klasifikasi

| Kelas | Lokasi | Dilihat pelanggan/admin? | Tindakan |
| --- | --- | --- | --- |
| A. Permukaan produk | `resources/views`, `routes`, `app/Http`, `app/Models`, `app/Mail`, `app/Notifications`, `public`, `.env.example` (`APP_NAME=ZAFIRA`) | Ya | Nol temuan. Dijaga oleh tes (task berikutnya). |
| B. Harness isolasi internal | `config/amadis.php`, `app/Support/ComposerHooks.php`, `scripts/amadis/*`, env `AMADIS_*`, `.gitignore` (`.amadis-pdf-dependency.lock`), `scripts/manual/*` | Tidak | Nama teknis stabil. Diganti hanya lewat migrasi terkoordinasi (lihat risiko). |
| C. Fixture demo | `database/seeders/AmadisDemoSeeder.php`, slug `amadis-demo-*`, `resources/fonts/NotoSansSC-Regular.provenance.json` | Hanya di DB demo | Teks deskripsi produk fixture menyebut "AMADIS"; dikaji pada task penyeragaman. |
| D. Tes | `tests/Feature/Amadis*Test.php`, `tests/Unit/ComposerHooksTest.php`, `tests/python/test_amadis_*`, `tests/amadis_guard_negative_test.py` | Tidak | Mengikuti nama harness (kelas B). |
| E. Bukti historis | `docs/execution/**` (423 berkas), `docs/demo/**` (16), `docs/04-implementation/*` (5), `docs/superpowers/*`, `docs/archive/*` | Tidak | Tidak diubah. Bukti bertanggal; menulis ulang akan memalsukan riwayat (Prinsip 1). |
| F. Artefak lokal tak terlacak | `.phpunit.result.cache`, `.playwright-cli/*`, `tests/__pycache__`, `scripts/amadis/__pycache__` | Tidak | Abaikan; dapat dihapus kapan saja. |

## Fakta terverifikasi
- `grep -ri amadis resources/views routes app/Http app/Models` kosong.
- `.env.example` tidak memuat kunci `AMADIS_*`; hanya `APP_NAME=ZAFIRA`.
- Satu-satunya jalur kode terlacak di luar harness yang menyebut Amadis ke pengguna adalah `product_description` pada seeder demo ("Fixture sintetis AMADIS...").

## Risiko penggantian nama harness (kelas B/D)
- `AMADIS_ROOT`, `AMADIS_DATABASE`, `AMADIS_EVIDENCE_PATH` dibaca oleh `config/amadis.php`, `ComposerHooks`, dan `scripts/amadis/guard.php`. Mengganti sebagian memutus guard isolasi dan *fail closed* pada Composer hooks.
- `tests/Unit/ComposerHooksTest.php` dan 24 tes Python mengunci nama-nama itu.
- Bukti penerimaan (`docs/execution/**`) merujuk path `scripts/amadis/*`; mengganti nama memutus jejak.

## Rekomendasi (terbuka, bukan keputusan)
1. Pertahankan nama harness internal; dokumentasikan bahwa "Amadis" = nama kode harness isolasi, bukan merek.
2. Ganti hanya teks fixture yang bisa tampil di UI demo menjadi "ZAFIRA".
3. Tambahkan tes regresi yang memastikan kelas A bebas "amadis".

## Pembaruan 2026-10-20 (Keputusan #035)
- Teks `product_description` seeder demo kini "Fixture sintetis ZAFIRA...".
- `tests/Feature/BrandNameConsistencyTest.php` mengunci kelas A bebas "amadis".
- Nama harness (kelas B/D) tetap; bukti historis (kelas E) tidak diubah.
- Keputusan #036: judul `docs/demo/*` kini "ZAFIRA"; CI menjalankan `BrandNameConsistencyTest`.
