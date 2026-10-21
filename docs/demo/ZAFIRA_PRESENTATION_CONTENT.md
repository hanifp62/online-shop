# ZAFIRA — Isi Presentasi

Tanggal dibuat: 2026-10-12; diperbarui 2026-10-13. Semua status mengikuti `docs/demo/ZAFIRA_ACCEPTANCE_MATRIX.csv`.

**Pembaruan 13 Oktober 2026:** AM-01, AM-02, AM-06, dan AM-10 kini PASS pada fixture SQLite terisolasi (`docs/execution/evidence/2026-10-13/acceptance-am-isolated/REPORT.md`, semua rc 0). AM-03–AM-05 dan AM-07–AM-17 berstatus PASS hanya sebagai feature test pada gate `docs/execution/evidence/2026-10-13/final-gate-closeout/REPORT.md`. NFR-003 pada DB terisolasi tetap NOT RUN dan DB operasional tetap BLOCKED.

## Slide 1 — ZAFIRA: dari etalase ke transaksi yang dapat diaudit

- La Maison de Lumière untuk klien couture dan tim atelier.
- Fokus demo: katalog, cart, checkout, stok, order, dan bukti.
- Status: demo lokal bersyarat; AM-01/02/06/10 PASS terisolasi, bukan sign-off produksi.

Speaker note: Tegaskan bahwa ini demo terisolasi, bukan sign-off produksi.

## Slide 2 — Masalah yang diuji

- Klien perlu menemukan kreasi tanpa kehilangan konteks filter.
- Atelier perlu order yang tidak ganda dan stok yang tidak negatif.
- Tim perlu bukti, bukan klaim visual.

## Slide 3 — Fixture demonstrasi

- 76 produk aktif sintetis.
- 4 kategori: Busana Couture, Maroquinerie, Aksesori Sutra, Warisan Nusantara.
- Seluruh kategori, produk, brand, dan badge diberi label demo.
- Produk AM-10 memakai slug `amadis-demo-stock-37`, stok awal 37.
- Status eksekusi fixture: PASS pada SQLite terisolasi (AM-01, 13 Oktober; `acceptance-am-isolated`).

## Slide 4 — Pengaman data

- Seeder hanya berjalan pada SQLite terisolasi yang lolos guard.
- Database dengan data non-demo ditolak.
- Rerun tidak menghapus rows dan tidak mengisi ulang stok.
- `DatabaseSeeder.php` tidak diubah.

## Slide 5 — Peta AM-01 sampai AM-17

| Kelompok | ID | Status |
|---|---|---|
| Fixture dan katalog | AM-01–AM-06 | AM-01/02/06 PASS terisolasi; AM-03–05 PASS feature test saja |
| Cart dan checkout | AM-07–AM-12 | AM-10 PASS terisolasi (SQLite saja); AM-07–09, 11–12 PASS feature test saja |
| Kepemilikan dan lifecycle | AM-13–AM-17 | PASS feature test saja |

Speaker note: Pemetaan ini usulan karena rubrik AMADIS resmi tidak ditemukan.

## Slide 6 — Eksperimen AM-10

- Stok awal: 37.
- Invariant: stok akhir + unit bersih terjual = 37.
- Tidak boleh ada stok negatif atau unit melebihi 37.
- Hasil 13 Oktober (SQLite): 3 request paralel 20/20/17 → 201, 422, 201; stok akhir 0; unit terjual 37. Bukan bukti locking MariaDB. Durasi: TBD.

## Slide 7 — NFR-003: riwayat jujur

- 2026-10-11: FAIL karena tabel Order belum ada.
- 2026-10-12: scope diperbarui ke order/item relasional, idempotensi, dan stok atomik.
- Migration FK tersedia di source.
- Isolated enforcement: NOT RUN. Operational DB: BLOCKED.

## Slide 8 — Audit pengalaman

- Norman: tombol, input, dan error harus menunjukkan aksi serta hasilnya.
- Nielsen dan Shneiderman: status, exit, recovery, dan closure terlihat.
- Cooper dan Krug: checkout mengurangi kerja tanpa nilai.
- Engelbart dan Brown: admin melihat status operasi; demo menimbang desirability, feasibility, viability.
- Status browser: AM-02/AM-06 PASS terisolasi (Chromium headless). Audit HCI/WCAG menyeluruh: NOT RUN.

## Slide 9 — Bukti yang tersedia

- Source: seeder, migrations, feature tests.
- Historis: audit Node 73 lulus, bukan hasil AMADIS.
- Historis: visual 23/56 terlihat, bukan validasi AMADIS.
- Bukti run 13 Oktober: `acceptance-am-isolated/` (AM-01/02/06/10), `final-gate-closeout/` (PHP 158, Node 86, Python 133, Pint PASS).

## Slide 10 — Keputusan berikutnya

1. Siapkan launcher isolasi.
2. Jalankan fixture, test, AM-10, dan browser flow.
3. Isi matrix dengan actual result.
4. Tinjau gap produksi: CSP, CI, data authority, engine produksi.

Closing line: Tidak ada klaim siap produksi sebelum bukti lengkap.
