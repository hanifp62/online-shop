# Remediasi dan kesiapan real-world testing — 21 Oktober 2026

## Mandat dan baseline

- **ID:** REM-20261021; mandat langsung pengguna untuk menindaklanjuti AUD-20261021, bukan pengambilan task NgodingPakeAI.
- **Baseline:** `041ed083a1be330d97d4f335cda8555670045cdd`; mulai `2026-10-21T11:53:56Z`. Perubahan dokumentasi/evidence audit sebelumnya dipertahankan.
- **Sumber masalah:** [audit komprehensif](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md).
- **Target:** menutup cacat yang dapat diperbaiki dan diverifikasi lokal, menyediakan kontrak TPS/MSI dan gerbang uji lapangan yang jujur. Bukan janji nol cacat atau izin transaksi produksi tanpa provider, ingress, engine, dan operasi tervalidasi.
- **Batas:** tidak membaca secret operasional, tidak menjalankan database destruktif/charge/refund nyata, tidak commit/push/upgrade dependency, dan tidak mengubah status plan server. Identitas brand dipertahankan. Test menggunakan fixture sintetis terisolasi.

## Requirement dan acceptance

| ID | Sasaran / audit terkait | Acceptance yang dapat diuji |
|---|---|---|
| FR-REM-001 | Keamanan identitas: SEC-01–07 | URL sensitif memakai origin tepercaya; input malformed tervalidasi; recovery owner-bound; revokasi sesi dan limiter TOTP aman; enrolment pelanggan atomik; email belum terverifikasi tidak menguasai subscriber. |
| FR-REM-002 | Integritas transaksi: TXN-01–08 | Deduplikasi mempertahankan progres pembayaran tanpa paid ganda; cancel berbayar tidak menghilangkan kewajiban finansial; refund memakai nilai bersih; retry/expiry menggunakan batas transaksi benar; label shipment terminal benar. |
| FR-REM-003 | Pemulihan: OPS-01/02, DATA-05/06 | Backup memakai target efektif, parameter transport aman, retensi invalid gagal sebelum penghapusan; artefak atomik/streaming; verifikasi snapshot tidak membandingkan dua waktu. |
| FR-REM-004 | Kontrak data: DATA-01–04/07–09 | Lock/recheck penghapusan akun; nominal/harga/URL konsisten; rollback menolak data yang tak representabel sebelum DDL; akses publik dibatasi; kontrak PII/retensi eksplisit. |
| FR-REM-005 | HCI/UI: UI-01–10 | State favorit/search/locale konsisten; mutasi terurut; timeout tidak membuang idempotency key; error dapat ditindaklanjuti; fokus dan status admin akurat; CSS prototype tidak mengatur viewport canonical. |
| FR-REM-006 | Gerbang operasi: OPS-03–09 | Test fixture valid tanpa melemahkan guard; failure substep membuat gate gagal; cleanup hanya proses milik runner; mail tidak sukses palsu; runtime minimum benar; review protection masuk akal. |
| FR-REM-007 | TPS dan MSI | Inventaris transaksi, rumus baris baru/update per kejadian dan per satuan waktu, asumsi eksplisit, perbedaan proyeksi vs throughput terukur, ringkasan operasional admin yang dapat ditelusuri ke data. |
| NFR-REM-001 | Performance | Ukuran workload, query/timing, environment dan batas tercatat; tidak mengklaim kapasitas berdasarkan perkiraan. |
| NFR-REM-002 | Continuous availability | Kegagalan/retry/backup/restore dan worker/scheduler memiliki gate; deployment nyata tetap perlu bukti staging. |
| NFR-REM-003 | Data integrity | Regression negatif untuk ownership, uang, stok, idempotensi dan migrasi; tidak menurunkan assertion untuk hijau. |
| NFR-REM-004 | Ease of use / accessibility | Tujuh lensa HCI + aksesibilitas; browser/keyboard/viewport diverifikasi bila runtime tersedia, screen-reader tak dijalankan ditandai belum terbukti. |
| NFR-REM-005 | Modular growth | Perubahan terarah, kontrak domain jelas, reuse dependency/pola yang ada; tanpa rewrite spekulatif. |
| NFR-REM-006 | Controlled processing | Status/otorisasi/ledger/history/observability konsisten, gate tidak false-positive, review independen. |

## Urutan kerja dan gate

1. **RED:** author menyiapkan regression test dan kontrak, tanpa mengubah aplikasi. Integrator menjalankan tes database secara serial.
2. **GREEN:** implementasi dalam batas file terpisah, diikuti tes terarah dan perbaikan temuan.
3. **Crosscheck:** reviewer berbeda menantang invariant keamanan/uang, data/operasi, dan UI; bukan rubber stamp.
4. **Integrasi:** suite PHP/Node/Python, Pint, build, pemeriksaan scope/diff; tidak menjalankan resource bersama paralel.
5. **Uji lapangan:** browser terisolasi dan percobaan operasi yang aman; kendala platform/akses eksternal tidak ditutupi. Status siap bersyarat hanya jika gate relevan terbukti.

## Lensa profesional dan skill

Nama tokoh menjadi lensa metodologis, bukan orang yang benar-benar mengaudit atau menyetujui proyek. Semua nama dan enam dimensi permintaan dicakup melalui pemetaan bukti, bukan kutipan tanpa sumber. Keputusan keamanan/data memakai kode/framework dan sumber resmi yang dapat diperiksa; riset primer dan batas akses dicatat pada dokumen TPS/MSI.

Skill integrator: `incremental-implementation`, `laravel-tdd`, `laravel-verification`, `git-workflow-and-versioning`, `doubt-driven-development`, `code-review-and-quality`, `documentation-and-adrs`. Skill domain dipilih dan dicatat per lane. Skill media/video/mobile/framework lain tidak diterapkan karena tidak relevan dan berisiko memperluas scope. Aturan proyek untuk tidak commit tanpa izin mengungguli saran commit generik skill. Tidak ada angka “1000% kemampuan” yang dapat dibuktikan; bukti berupa test, review, dan batas yang transparan.

## Handoff backup R1 — FR-REM-003 / OPS-01/02 / DATA-05/06

- **Temuan reviewer valid:** checksum dan gzip yang valid belum membuktikan snapshot SQLite dapat dipulihkan. `prune()` kini memakai `verify()` untuk kandidat SQLite sebelum ranking retensi: header SQLite, `integrity_check`, `foreign_key_check`, dan row counts snapshot, tanpa DB hidup. Kandidat invalid dibiarkan untuk investigasi; tidak menggusur backup baik terakhir.
- **Regresi baru:** `BackupRemediationTest::test_ops02_invalid_sqlite_snapshot_cannot_displace_last_restorable_backup`, dataset `non-database` dan `foreign-key-invalid`. Keduanya gzip valid dan hash cocok; fixture FK secara eksplisit lolos `integrity_check` tetapi gagal `foreign_key_check`.
- **Fixture Process:** `php-green-first.log` menunjukkan matcher `assertRanTimes(1)` keliru serta recorded calls lama bertahan setelah `fake()` ulang. Factory Process baru dipasang untuk setiap pergantian fake; assertion tetap menuntut tepat satu proses atau nol proses sesuai skenario. Tidak ada assertion dihapus/dilonggarkan. Skill lane: `laravel-tdd`, `laravel-patterns`.
- **Kontrak OPS:** scheduler menentukan driver efektif melalui `Illuminate\Database\ConfigurationUrlParser::parseConfiguration()` terhadap konfigurasi koneksi yang sama; jangan menggunakan driver fallback mentah. `backup.keep` tetap nilai asli dan diteruskan ke `prune()` tanpa cast. Routes/console tetap scope OPS; tidak ada helper/API baru.
- **Kompatibilitas:** legacy `.sqlite.gz` + `.sha256` tetap diterima tanpa migrasi. Retensi SQLite sekarang dapat mempertahankan lebih banyak file bila kandidat invalid ditemukan; biaya I/O/integrity-check bertambah dan perlu diukur.
- **Release gate MySQL/MariaDB — BELUM TERTUTUP:** `verify()` tetap menolak restore MySQL/MariaDB. Validasi hash/gzip pada retensi MySQL hanya integritas transport, bukan validitas SQL, kelengkapan dump, atau keberhasilan restore. Jangan menampilkan backup MySQL sebagai restore-verified atau menyatakan siap produksi berdasarkan tes fake. Wajib drill restore engine/version produksi pada database disposable, verifikasi skema/data/constraint, kompatibilitas binary dump dan transport, serta bukti RPO/RTO sebelum persetujuan rilis. Opsi PDO/TLS yang belum didukung tetap fail-closed, bukan dinonaktifkan agar backup berjalan.
- **Batas publikasi:** gzip-last tidak menjadikan dua file transaksi atomik atau durable terhadap power loss; crash dapat meninggalkan staging/sidecar yatim. Hash bukan autentikasi. Tidak ada cleanup/destruksi data operasional dalam lane ini.
- **Status bukti R1:** suite PHP/DB/server tidak dijalankan oleh lane ini; hasil GREEN runtime dan persetujuan reviewer setelah revisi **TIDAK TERBUKTI**, menunggu main menjalankan suite serial.

## Status

**Remediasi lokal terverifikasi; real-world end-to-end/produksi belum disetujui.** Implementasi, keputusan perilaku, migrasi, hasil pengujian, review independen dan gate tersisa ada di [laporan hasil](../05-quality/REMEDIATION_REPORT_2026-10-21.md). R7 penuh: PHP 1.150 passed/3 skipped/29.856 assertions, Node 465 passed, Python 165 dilaporkan/6 skipped (159 dieksekusi), Pint 376 file dan build EXIT=0. Tambahan inquiry pasca-R7: 12 passed/51 assertions, membuktikan tidak membuat order atau mengubah cart/stok. Rerun penuh final sesudah tambahan ini: **1.150 passed/3 skipped/29.864 assertions, EXIT=0**, terikat manifest source. Slice DATA-08 export kemudian ditutup dengan `lazyById(1000)`/iterator, test privasi/bounded-fetch dan review DATA independen; rerun paling akhir **1.154 passed/3 skipped/156.806 assertions, EXIT=0**, Node 465 pass dan Pint 377 file pass (`php-full-export-final.*`, `source-manifest-export-final.json`). Bukan coverage persen atau bukti browser/InnoDB/provider. Catatan R1 di atas adalah histori; persetujuan source dan bukti terkini menggantikan status pending lama tanpa menghapus batas restore/engine.

**Gate eksternal tetap terbuka:** izin proses untuk cleanup/browser, concurrency/DDL/restore engine target, provider pembayaran/refund/kurir/SMTP, uji beban, screen reader/viewport dan operasi staging. Tidak ada commit/push/sync atau migrasi database operasional.
