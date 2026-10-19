# Laporan follow-up ZAFIRA — 2026-10-14

Laporan ini mengoreksi kesimpulan laporan v1 tanpa menghapus bukti historis. HEAD dasar: `2f355e1624306d7277f7828ee319321b21bee0ea`; perubahan working tree belum di-commit.

## Keputusan dan acceptance

| ID | Acceptance | Status | Bukti dan batas |
|---|---|---|---|
| GAP-003 | Status paid membutuhkan bukti pembayaran tepercaya | PARTIAL | OBSERVED_SOURCE: `Order::MANUAL_BLOCKED` dan `CheckoutService::transition` menolak target paid; dropdown menyembunyikannya. PHP tests menguji 422. Pembayaran nyata NOT_RUN/NOT_IMPLEMENTED. PASS di laporan v1 keliru. |
| GAP-001 | Ketersediaan PDP mengikuti stok/status aktif | PARTIAL | OBSERVED_SOURCE: PDP membaca produk langsung setiap request. StorefrontAvailabilityTest: 6 tests, 28 assertions. Ini snapshot saat query, bukan jaminan atomik hingga pembelian. Cache beranda/related tetap 300 detik. |
| GAP-005, GAP-020 | Checkout tidak menggandakan order/stok/voucher | PARTIAL | OBSERVED_RUNTIME pada artefak SQLite: 11/11 skenario lulus, 10 barrier checkout. MySQL/MariaDB NOT_RUN. Status harness adalah pemetaan return/exception service, bukan HTTP. |
| GAP-006 | Pembatalan tidak memulihkan stok dua kali | PARTIAL | SQLite double-cancel: status terurut 200/409, stok akhir 10. Mesin target NOT_RUN. |
| GAP-009 | Semua bootstrap terisolasi | FAIL | Riwayat bootstrap non-terisolasi tetap gagal; dampak database operasional UNKNOWN. Bukti 8018 yang aman tidak menghapus riwayat. |
| GAP-015 | Suite otomatis lulus | PASS | OBSERVED_RUNTIME rerun koordinator: PHP 175 passed, 1 skipped, 1362 assertions; Node 83/83. Build sebelumnya lulus (REPORTED dari sesi terdahulu). |
| GAP-019 | Validasi UX/HCI | PARTIAL | Browser reflow 320/390, cart, undo, refresh, checkout diuji. Screen reader, axe/contrast, perangkat nyata, user study dan WCAG lengkap NOT_RUN. |
| GAP-021 | Unduh laporan admin | PARTIAL | Browser PDF 200, application/pdf, signature PDF, 7857 bytes. Bukan sertifikasi seluruh visual/font atau aksesibilitas PDF. |
| GAP-002 | Kebijakan ongkir | PARTIAL | Keputusan bisnis belum tersedia; shipping_fee=0 bukan bukti kebijakan gratis ongkir. |

## Konkurensi

Sumber otoritatif: `../remediation/concurrency/followup/CONCURRENCY_REPORT.md` dan `green-v3.json`.

| Skenario | Status terurut | Orders | Stok akhir | Voucher used |
|---|---|---:|---:|---:|
| same-key | 200,201 | 1 | 9 | 0 |
| changed-address | 201,409 | 1 | 9 | 0 |
| distinct-voucher | 201,422 | 1 | 9 | 1 |
| distinct-key | 201,201 | 2 | 8 | 0 |
| exact-one-stock | 201,422 | 1 | 0 | 0 |
| same-key-stock-one | 200,201 | 1 | 0 | 0 |
| voucher-last-per-user-one | 201,422 | 1 | 9 | 1 |
| same-key-voucher-last | 200,201 | 1 | 9 | 1 |
| combined-same-key | 200,201 | 1 | 0 | 1 |
| combined-distinct-key | 201,422 | 1 | 0 | 1 |
| double-cancel | 200,409 | 1 | 10 | 0 |

SHA-256 JSON: `34a4c51259eed35524ddbc9a6638b5d0bbf334219d9c703c0840dda8a8cdf13f`.
PHP 8.5.11, Laravel 12.69.1, SQLite 3.53.4, WAL, busy timeout 50 ms, transaksi DEFERRED. Tidak membuktikan beban tinggi, MySQL, atau exhaustion retry 503. Batas classifier dan kemungkinan extended SQLite codes dijelaskan dalam laporan konkurensi; belum direproduksi sebagai kegagalan.

## Native dan browser

Sumber otoritatif: `browser/RESULT.md`, `browser/browser-results.json`, snapshot fixture/persistence, dan source manifest.

- OBSERVED_RUNTIME (artefak): fixture dua kali identik; snapshot order/stok identik sebelum/sesudah restart; acceptance pertama dan kedua lulus.
- Browser: **13 PASS / 1 FAIL**, exit 1. Tidak boleh disebut suite hijau.
- `catalog-toast-count` tetap FAIL. Katalog hanya mempunyai feedback inline yang lulus. Requirement wajib toast/badge belum terbukti; tidak menghapus assertion atau menambah fitur tanpa keputusan produk.
- Home run kedua mencatat teks toast, badge 1 dan quantity server 1. Run pertama memiliki teks bukti kosong; penyebab kegagalan historis tidak terbukti.
- Checkout pending, history/cancel, rapid +3, undo, refresh, admin tanpa opsi paid, dan unduh PDF lulus.
- Page errors 0, HTTP >=400 0. Ada 16 kegagalan font eksternal yang sengaja diblokir dan satu ERR_ABORTED navigasi unduhan PDF yang berhasil.
- Exit setup tidak direkam eksplisit. Percobaan awal dengan cwd/env salah disimpan. Penghentian server 8018 dilaporkan subagen; port operasional tidak disentuh.

## Ruling dan ADR

- ADR-010: pending-only; paid ditolak sampai kontrak bukti pembayaran tepercaya tersedia. Status historis paid bukan bukti settlement. Audit trail status belum diimplementasikan.
- ADR-008 direvisi: PDP tanpa cache produk; tidak menjanjikan stok tetap tersedia setelah render.
- ADR-006 tetap: legacy localStorage hanya dibaca, tidak digabung. Pengguna menambah ulang dari katalog/concierge. Import otomatis tidak dibuat; dukungan rekonsiliasi otomatis belum tersedia. Risiko: pekerjaan ulang pengguna.

## Paket dan provenance

Paket v2 memuat laporan ini, laporan v1 sebagai riwayat, bukti browser/native, bukti barrier v3, ADR, serta source yang diizinkan. Tiga file test PHP dikecualikan karena pemindai mendeteksi literal password fixture; hash aslinya ada di `test-source-hashes.txt`. Pemindai tidak dilonggarkan.

Paket v1 harus tetap berhash `48abe87e34b447554cdeb63845f2d33cee671b94bba7fea121683815660567a0` (koreksi teks lama yang kehilangan digit akhir). AUDIT_PACKET asli: `6cdf4e7d99147a65230a094a8809544eacf54d0fb5b438eedc6ca5dbf856cc6b`.

Build v2 awal lolos CRC/manifest tetapi tidak memuat subdirektori bukti konkurensi; belum layak diserahkan. Builder diperbaiki agar rekursif. Hash final dan hasil verifikasi dicatat di luar arsip untuk menghindari referensi hash siklik.

## Kesimpulan

- **Manual sintetis:** dapat digunakan dengan batasan dan satu assertion browser gagal yang tetap terbuka.
- **UX/HCI:** hanya cakupan teruji; bukan kelulusan tujuh kerangka HCI atau WCAG penuh.
- **Produksi:** belum siap dinyatakan lulus. Pembayaran, ongkir, mesin database target, rotasi kredensial historis, serta dampak bootstrap terdahulu masih terbuka.
