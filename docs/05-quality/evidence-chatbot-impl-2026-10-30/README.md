# Bukti implementasi Asisten Bantuan & Concierge (2026-10-30, v2)

**Cakupan:** FR-401, FR-402.1–FR-402.10, NFR-401, NFR-402, dan NFR-403 menurut [spesifikasi](../../02-product/CHATBOT_ASSISTANT_SPEC.md).

**Riwayat:** v1 (pagi 2026-10-30) adalah launcher, chip topik, dan handoff. v2 menambahkan pencarian katalog, kartu produk, tambah ke tas dengan konfirmasi dan urungkan, 4 topik baru, status concierge live, umpan balik, pintasan H, dan nomor referensi di admin. Berkas v1 diganti oleh hasil v2.

## Lingkungan

- `php artisan serve` pada `127.0.0.1:8023` dengan build produksi `npm run build`.
- Database SQLite terpisah di folder sementara (migrate + seed). Stok 4 produk disetel ke 5 agar alur tambah ke tas bisa diuji. Database lokal pengembang tidak disentuh.
- `MAIL_MAILER=array` dan `QUEUE_CONNECTION=sync`, sehingga tidak ada email terkirim.
- Chromium Playwright 1.64.1, zona waktu Asia/Jakarta.
- Viewport 1280×900 dan 320×640, tema terang dan gelap.

## Hasil

| Jenis | Hasil | Catatan |
|---|---|---|
| Browser ([browser-check2.mjs](browser-check2.mjs), [results.json](results.json)) | 28/28 PASS | Alur A–D: menu, pencarian, keranjang dan undo, umpan balik 204, hadiah→personalisasi→janji temu→referensi, 320 px dua tema, persistensi |
| Vitest seluruh suite | 271/271 PASS | Termasuk `storefront-assistant.spec.jsx` 16/16 |
| PHPUnit `ConciergeAssistantTest` | 9/9 PASS | Referensi, `source`, pencarian (sinonim, aksen, salah ketik, parsial, nonaktif, stok live, bentuk respons), umpan balik, admin |
| PHPUnit seluruh suite | 1373 PASS, 1 FAIL, 4 skip | FAIL di `SecurityScanIntegrityTest` (`repository-root-error`). Gagal identik pada worktree bersih commit `426285e`, jadi bukan regresi |
| `node --test` | 667/667 PASS | |

## Kontras terukur

Warna diambil dari `getComputedStyle` dengan latar efektif ([terang](colors-light.json), [gelap](colors-dark.json)).
- **Terang:** 4,69–16,08:1. Nilai terendah ada pada teks redup dan baris umpan balik.
- **Gelap:** 8,27–15,54:1.

## Belum diverifikasi

- Screen reader nyata (VoiceOver/NVDA), teks 200%, dan uji pengguna.
- Launcher di `/koleksi` dan `/belanja`.
- Tampilan admin untuk data umpan balik. Untuk sementara pakai kueri di spesifikasi §9.

## Screenshot

| Berkas | Isi |
|---|---|
| [01](shots/01-welcome-desktop.jpg) | Sapaan, status live, menu 8 topik berikon |
| [02](shots/02-search-cards.jpg) | Hasil "tas sutra" sebagai kartu produk |
| [03](shots/03-added-undo.jpg) | Konfirmasi, ditambahkan, lalu Urungkan / Buka tas |
| [04](shots/04-handoff-preview.jpg) | Pratinjau catatan sebelum handoff |
| [05](shots/05-sent-reference.jpg) | Kembali ke chat dengan nomor referensi |
| [06](shots/06-pdp-320-light-welcome.jpg) / [gelap](shots/06-pdp-320-dark-welcome.jpg) | PDP 320 px, sapaan |
| [07](shots/07-pdp-320-light-product.jpg) / [gelap](shots/07-pdp-320-dark-product.jpg) | Detail produk dengan langkah lanjut |
| [08](shots/08-pdp-320-light-cards.jpg) / [gelap](shots/08-pdp-320-dark-cards.jpg) | "gaun biru" → Robe Élan, "Pilih ukuran" |
| [09](shots/09-carried-pdp.jpg) | Percakapan terbawa ke halaman produk lain |
