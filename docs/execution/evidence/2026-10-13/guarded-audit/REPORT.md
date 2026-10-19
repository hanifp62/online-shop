# Guarded Composer audit — 2026-10-13

## Scope dan acceptance

**NFR-AUDIT-001:** Audit Composer harus melalui launcher terjaga, dengan argumen tetap, validasi root/identity/cwd, serta bukti stdout JSON, stderr, dan return code asli. Tidak boleh melakukan upgrade, reinstall, mengubah hook, atau melonggarkan jalur `run`.

Perubahan hanya pada cabang `composer-audit` di `scripts/amadis/launch.py`, pengujian `tests/python/test_amadis_composer_audit.py`, dan folder bukti ini. Tidak ada commit atau staging.

## Implementasi

- CLI menerima tepat `composer-audit --locked --format=json`; argumen lain ditolak sebelum bootstrap.
- Memakai validasi root, ownership, symlink, lock, serta identitas database launcher yang sudah ada.
- Memeriksa identitas repository dan cwd untuk audit sebelum bootstrap.
- Bootstrap konfigurasi memakai `scripts/amadis/artisan.php`; entry point ini menjalankan `ComposerHooks::guard()` dan memeriksa konteks PHP sebenarnya.
- Memakai validator hook/plugin dan kecocokan vendor terhadap lock yang sudah ada.
- Menjalankan argv tetap: `composer audit --locked --format=json --no-interaction --no-ansi`.
- Tidak memakai `--no-scripts`, `--no-plugins`, atau opsi bypass audit. Hook PHP dan konfigurasi Composer tidak diubah.
- `capture()` mempertahankan return code proses Composer dan menyimpan log sanitasi sesuai pola launcher. Bukti luar menangkap stdout/stderr launcher; pada run ini stderr kosong.
- Fingerprint sebelum/sesudah memeriksa manifest dan installed metadata. Tidak mengklaim sandbox filesystem penuh untuk kode plugin.

## Eksekusi nyata

Perintah: `./scripts/amadis/launch.sh composer-audit --locked --format=json` dengan `AMADIS_ROOT` hasil `launch.sh init`.

Lihat `run-metadata.json` untuk root, argv, dan SHA-256 sebelum/sesudah. Semua hash sama untuk `composer.json`, `composer.lock`, `vendor/composer/installed.json`, `ComposerHooks.php`, dan `guard.php`.

- `audit.stdout.json`: JSON Composer valid; dua advisory `league/commonmark`.
- `audit.stderr.txt`: kosong, 0 byte.
- `audit.rc.txt`: **1**, bukan kegagalan launcher; Composer melaporkan advisory.
- Tidak ada paket abandoned dalam JSON.
- Proposal upgrade terpisah: `commonmark-upgrade-proposal.md`. Proposal tidak dijalankan.

## Verifikasi dan review

- Targeted unittest: **9 lulus**, exit **0** (`targeted.txt`, `targeted.rc.txt`). Meliputi argv tetap, environment, raw return code, penolakan argumen, root, identitas, cwd, perubahan fingerprint, dan tetap diblokirnya raw `run composer`.
- Python suite terakhir: **123 tests, OK, skipped=6**, exit **0** (`python-suite.txt`, `python-suite.rc.txt`). Enam skip bukan bukti lulus integrasi yang dilewati.
- Run suite sebelumnya menemukan kegagalan pada `test_amadis_export`, di luar scope ini; run terakhir lulus setelah perubahan paralel pemilik lane lain. Tidak ada perbaikan exporter oleh lane ini.
- Self-review sumber: tidak ada jalur update/install baru, argumen pengguna tidak diteruskan ke Composer, hook tidak berubah, tidak ada modifikasi guard PHP. Launcher dan beberapa file terkait belum tracked sejak sebelum tugas; `git diff` kosong untuk launcher bukan bukti tidak berubah.
- `pytest` tidak tersedia; memakai `unittest` standar repository.

## Batasan

Audit bukan bukti aplikasi aman dari seluruh kerentanan. Dua advisory CommonMark tetap terbuka. Upgrade dan uji kompatibilitas versi pengganti belum dijalankan. Workspace memiliki perubahan paralel; hash membuktikan hanya file yang dicatat dan interval run tersebut.
