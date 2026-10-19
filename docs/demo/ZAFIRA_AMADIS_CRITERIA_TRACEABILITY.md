# Ketertelusuran demonstrasi AMADIS

Tanggal: 2026-10-13. Status: **terbatas; bukan sign-off produksi**. Bukti runtime terisolasi tersedia untuk AM-01/02/06/10; F1–F9 tetap NOT RETESTED.

## Sumber dan batas otoritas

Dibaca: `docs/README.md`, `docs/02-product/PRD.md`, `docs/02-product/REQUIREMENTS_TRACEABILITY.md`, dan `docs/execution/evidence/2026-10-12/final-acceptance/AUDIT_RECHECK_CURRENT.md`.

Rubrik AMADIS asli tidak tersedia dalam dokumen yang diperiksa. **Pemetaan AM-01–AM-17 berikut merupakan usulan kerja, bukan salinan rubrik resmi.** Integrator wajib menyelaraskan nomor dan ambang dengan instruksi induk sebelum presentasi. AM-10 dan stok awal 37 berasal dari instruksi tugas fixture. Status PASS pada baris selain AM-01/02/06/10 hanya berarti test fitur lulus pada gate 13 Oktober (`evidence/2026-10-13/final-gate-closeout/REPORT.md`; PHP 158/158, rc 0), bukan acceptance rubrik resmi. AM-01/02/06/10 dijalankan terisolasi pada SQLite (`evidence/2026-10-13/acceptance-am-isolated/REPORT.md`, rc 0); F1–F9 NOT RETESTED. Matriks mesin: `docs/demo/ZAFIRA_ACCEPTANCE_MATRIX.csv`.

| ID | Acceptance yang dapat diuji (usulan) | Requirement terkait | Source pemeriksaan | Status |
|---|---|---|---|---|
| AM-01 | Fixture memuat 76 produk aktif, empat kategori, masing-masing 19 produk | FR-001, US-001 | `database/seeders/AmadisDemoSeeder.php` | PASS (fixture SQLite terisolasi; `acceptance-am-isolated`) |
| AM-02 | Katalog delapan produk/halaman; halaman terakhir memuat empat dari 76 | FR-001, US-001 | `tests/Feature/AmadisCatalogTest.php` | PASS (browser Chromium headless; `acceptance-am-isolated`) |
| AM-03 | Pencarian dan filter warna/material/kategori bertahan saat pindah halaman | FR-002, US-001 | `tests/Feature/AmadisCatalogTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-04 | PDP valid menampilkan detail; slug tidak valid menghasilkan 404 | FR-003, US-002 | `app/Http/Controllers/StorefrontController.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-05 | Locale id/en/zh valid; locale tidak dikenal kembali ke id | NFR-004 | `tests/Feature/AmadisCatalogTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-06 | Quick view dapat dibuka/ditutup dengan keyboard; output produk di-escape | NFR-002, NFR-004 | `tests/Feature/AmadisCatalogTest.php` | PASS (Chromium headless + PHPUnit escaping 6 tes; `acceptance-am-isolated`) |
| AM-07 | Kuantitas positif dan stok cukup; produk nonaktif ditolak | FR-004 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-08 | Checkout menghitung harga server dan menyimpan snapshot alamat | FR-005, NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-09 | Retry key/payload identik menghasilkan order sama; payload berbeda ditolak 409 | NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-10 | Eksperimen paralel stok awal 37: jumlah unit berhasil ≤37; stok akhir tidak negatif; stok + unit bersih =37 | NFR-003 | `database/seeders/AmadisDemoSeeder.php` | PASS (SQLite saja; bukan bukti locking MariaDB) |
| AM-11 | Kegagalan stok membatalkan order, item, dan konsumsi kupon secara atomik | NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-12 | Kuota kupon global/per-user tidak terlampaui; diskon dihitung server | NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-13 | Alamat dan order pengguna lain tidak dapat dibaca/diubah | FR-008, NFR-002 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-14 | Pembatalan yang diizinkan mengembalikan stok tepat satu kali | NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-15 | Hanya admin dapat menjalankan transisi order yang diizinkan | FR-009, FR-013 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-16 | Hanya pembeli dengan order completed dapat mengulas sekali | NFR-002, NFR-003 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |
| AM-17 | Favorit idempoten dan terisolasi per pelanggan | NFR-002 | `tests/Feature/AmadisOrderTest.php` | PASS (feature test saja; gate 13 Oktober) |

## NFR-003: riwayat dipertahankan

- 2026-10-10: PRD mewajibkan FK produk, kategori, dan pesanan.
- 2026-10-11: traceability dan handoff mencatat **FAIL** karena belum ada tabel Order. Ini tetap benar sebagai catatan audit saat itu.
- 2026-10-12, audit sebelum perluasan: `AUDIT_RECHECK_CURRENT.md` kembali mencatat blocker Order. Jangan mengganti catatan historis menjadi PASS.
- 2026-10-12, pembaruan cakupan demo: order durable, order items, stok atomik, idempotensi, alamat, kupon, ulasan, dan favorit ditambahkan ke jalur pembuktian. Alasan: checkout concierge berupa lead tidak cukup membuktikan integritas pesanan yang diminta NFR-003.
- Bukti source baru: `database/migrations/2026_10_12_000003_create_orders_table.php` mendefinisikan FK user/voucher dan key idempotensi unik. `database/migrations/2026_10_12_000004_create_order_items_table.php` mendefinisikan FK order/product. Keberadaan migration **bukan bukti penerapan schema**.
- Status per 2026-10-13: **NOT RUN** untuk enforcement FK NFR-003 pada DB terisolasi; **BLOCKED** untuk klaim database operasional/produksi. AM-10 SQLite tidak membuktikan MariaDB.
- Scope update ini tidak membatalkan FR-005 concierge atau mengubah PRD diam-diam. Integrator harus mencatat hubungan checkout transaksional baru dengan alur concierge lama pada indeks requirement kanonis.

## Gerbang kualitas presentasi

Audit HCI memakai Norman (signifier/feedback), Nielsen (status/error recovery), Shneiderman (kontrol/undo), Cooper (tujuan), Engelbart (informasi operasional), Brown (tiga lensa), Krug (kejelasan). Evaluasi browser AM-02/AM-06 di Chromium headless PASS terisolasi (`acceptance-am-isolated/REPORT.md`); audit HCI/WCAG menyeluruh tetap NOT RUN. Pertahankan Bodoni Moda, Manrope, Sapphire, Linen, Gold, Ivory. Jangan mengklaim WCAG lulus tanpa pengukuran kontras, keyboard, fokus, dan target sentuh.
