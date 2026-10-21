# Laporan pengujian nyata ZAFIRA

Tanggal dibuat: 2026-10-12. **Pembaruan 13 Oktober 2026:** AM-01/02/06/10 dijalankan pada fixture SQLite terisolasi (`docs/execution/evidence/2026-10-13/acceptance-am-isolated/REPORT.md`, semua rc 0); tabel di bawah adalah snapshot 12 Oktober dan baris terkait kini superseded oleh bukti tersebut. NFR-003 isolated, DB operasional, dan audit HCI/WCAG tetap NOT RUN/BLOCKED. Nama dokumen menunjukkan tujuan laporan, bukan klaim telah diuji dunia nyata.

## Ringkasan

| Pemeriksaan | Status | Batas klaim |
|---|---|---|
| Fixture 76 produk aktif / empat kategori | NOT RUN | Source tersedia; insert dan rerun belum dieksekusi |
| AM-01–AM-17 | NOT RUN | Pemetaan usulan ada di matriks; rubrik asli belum tersedia |
| NFR-003 pada SQLite terisolasi | NOT RUN | FK didefinisikan migration; enforcement belum diuji |
| NFR-003 pada database operasional | BLOCKED | Tidak boleh mengakses atau mengubah DB operasional |
| AM-10 stok 37 / konkurensi | NOT RUN | Test rollback sekuensial tidak membuktikan eksperimen paralel |
| HCI, aksesibilitas, responsivitas AMADIS | NOT RUN | Tidak ada inspeksi browser baru |
| PHP syntax fixture | TBD | Isi dari hasil command aktual, jangan menyalin hasil sesi lain |

Tidak ada artisan atau test dijalankan sebelum launcher isolasi siap. Tidak ada `.env` dibaca atau diubah. Port 8000/8001 tidak digunakan.

## Bukti historis, bukan hasil AMADIS

`docs/execution/evidence/2026-10-12/final-acceptance/AUDIT_RECHECK_CURRENT.md` mencatat Node 73 passed / 0 failed / 0 skipped, 132.838 ms. Angka ini adalah hasil audit terdahulu, bukan durasi transaksi, benchmark storefront, atau hasil test baru.

Dokumen yang sama membatasi cakupan visual terdahulu ke 23/56. `docs/execution/evidence/2026-10-12/final-acceptance/visual-inspection-final2.md` menjelaskan keterbatasan piksel dan teks kecil. Ini tidak membuktikan UI transaksi AMADIS atau WCAG 2.2 AA.

Riwayat NFR-003 **FAIL** tetap dipertahankan pada traceability. Migration order/item baru memberi bukti desain schema, bukan bukti runtime atau kesiapan produksi.

## Protokol rekaman hasil

Setiap run harus mencatat: waktu beserta zona, commit/hash diff, identitas DB terisolasi, versi runtime, command, exit code, expected/actual, jumlah request dan status HTTP, log stderr, screenshot yang benar-benar dilihat, dan batas cakupan. Jangan menyimpan cookie, token, kata sandi, atau identitas pelanggan nyata.

| Run | Waktu | Command | Expected | Actual | Exit | Bukti yang sudah ada |
|---|---|---|---|---|---|---|
| Fixture + rerun | TBD | TBD | 76 aktif; 4 kategori; stok tidak diisi ulang | TBD | TBD | — |
| Suite katalog | TBD | TBD | assertion katalog lulus | TBD | TBD | — |
| Suite transaksi | TBD | TBD | atomicity/ownership/idempotence lulus | TBD | TBD | — |
| AM-10 paralel | TBD | TBD | conservation stok 37 | TBD | TBD | — |
| Browser desktop/mobile/keyboard | TBD | TBD | alur selesai; fokus/error jelas | TBD | TBD | — |

## AM-10: accounting wajib

Gunakan slug `amadis-demo-stock-37` pada DB baru. Ukur stok sebelum run: 37. Kirim request checkout paralel dengan key unik; ulangi sebagian key identik untuk menguji replay. Jumlah worker, request, dan unit/request: TBD sesuai rubrik induk. Catat berhasil, replay, ditolak stok, contention/retry, galat tak terduga, jumlah order/item, dan stok akhir.

Untuk run tanpa pembatalan: `stock_after + SUM(quantity pada order berhasil unik) = 37`. Replay tidak boleh menambah order/unit. Stok akhir ≥0 dan total unit ≤37. Jika ada cancel, gunakan unit bersih setelah pembatalan. SQL agregasi harus memakai ID produk aktual, bukan ID yang ditebak.

SQLite dapat menghasilkan contention; jangan menganggap lock error sebagai keberhasilan atau menjamin perilaku MySQL. Pisahkan bukti SQLite dari uji engine produksi. **Tidak ada eksperimen produksi di sesi ini.**

## Kriteria penutupan

PASS hanya setelah expected/actual dan bukti mentah terhubung untuk setiap ID. FAIL berarti run menghasilkan pelanggaran. BLOCKED berarti prasyarat tidak tersedia. NOT RUN berarti belum dieksekusi. TBD bukan hasil.

Produksi tidak memperoleh sign-off dari fixture, unit test, atau deck. Audit keamanan, migration/backup, engine produksi, deployment, dan observability tetap diperlukan.
