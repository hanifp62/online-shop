# Sold-Out UI Controls — 2026-10-15

## Masalah (fakta terverifikasi)

Tombol tambah-ke-tas di katalog (`/koleksi`), quick view katalog, dan PDP (`products.show`) tetap aktif untuk produk dengan `stock = 0`. Server sudah menolak dengan 422 (`app/Http/Controllers/CartController.php:27`), tetapi pengguna baru tahu setelah mencoba.

## Perubahan (hanya `resources/views/` dan `tests/`)

| File | Perubahan |
|------|-----------|
| `resources/views/catalog/labels.php` | Kunci baru `sold_out` untuk 5 locale: id "Stok habis", en "Sold out", zh "售罄", fr "Épuisé", it "Esaurito". |
| `resources/views/catalog/index.blade.php` | Tombol kartu `disabled` + label `sold_out` jika `stock <= 0`; tombol quick view membawa `data-stock`; JS quick view menghitung ulang `disabled` dan label tombol `#dialog-add-button` serta input jumlah setiap kali dibuka; CSS `.catalog-btn:disabled`. |
| `resources/views/products/show.blade.php` | Tombol `[data-product-add]` dirender `disabled` di server dengan label "Stok habis", `aria-describedby="pdp-sold-out-note"`, dan catatan arahan ke advisor; `cursor: not-allowed`. |
| `tests/sold-out-controls.test.mjs` | Tes kontrak sumber baru (merah dulu, lalu hijau). |

Tidak diubah: predikat stok backend, endpoint POST, `app/`, `config/`, tes PHP. Server tetap otoritatif; UI hanya petunjuk.

Catatan: diff `products/show.blade.php` juga memuat perubahan lain (monogram/size-guide/dock) dari agen lain — bukan bagian pekerjaan ini.

## Batas lingkup

Beranda (`StorefrontController::index`) dan produk terkait tidak memilih kolom `stock`. Stok yang tidak dimuat diperlakukan sebagai "tidak diketahui", bukan nol, sehingga kontrol di sana tidak diubah. Server tetap menolak 422. Perlu perubahan controller (di luar kepemilikan ini) untuk menutup celah tersebut.

## Verifikasi

- `node --test tests/sold-out-controls.test.mjs` — gagal sebelum perbaikan, lulus setelahnya.
- `node --test tests/*.test.mjs` — 88/88 lulus (termasuk regresi `tests/catalog-replacement.test.mjs`), dijalankan ulang setelah penataan ulang `@php($soldOut)`.
- `npm run build` — lulus.
- Browser headless pada server utama 8000 (data asli, 8 produk stok 0): semua tombol kartu `disabled` "Stok habis", tombol quick view `disabled`, tombol PDP `disabled` "Stok habis"; aktivasi menghasilkan 0 POST `/cart/items`; 0 error konsol. Tangkapan layar disimpan di direktori temp sesi (di luar repo).
- Server sintetis 8031 tidak disinkronkan (agen lain sedang mengedit).
- Review independen (agen read-only): PASS, tanpa temuan; mencatat batas lingkup beranda/produk terkait.

## HCI

- **Norman — affordance & signifier:** tombol `disabled` kehilangan affordance klik (opasitas redup, `cursor: not-allowed`), dan label berubah menjadi "Stok habis" sebagai signifier eksplisit. Jurang evaluasi tertutup sebelum aksi.
- **Nielsen — error prevention:** kesalahan 422 dicegah di UI alih-alih dilaporkan setelah gagal. PDP menawarkan jalan keluar (konsultasi advisor) — membantu pemulihan.
- **Nielsen — match real world / konsistensi:** label dilokalkan di 5 bahasa katalog; pola sama di kartu, quick view, PDP.
- **Target sentuh 44px:** tidak ada perubahan ukuran; tombol memakai kelas `.catalog-btn` / `.button` yang ada. Status disabled tidak mengubah dimensi.
- **Aksesibilitas:** atribut `disabled` native diumumkan pembaca layar; PDP menautkan penjelasan via `aria-describedby`.

## Verifikasi round2 final (koreksi batas lingkup lama)

Status bagian “Batas lingkup” di atas sudah kedaluwarsa setelah agen lain memperbarui `StorefrontController` dan kartu beranda/produk terkait. Produk dan related kini memuat stok melalui cache key `v3`. `StorefrontCacheTest` memeriksa invalidasi key v3, sedangkan `StorefrontCacheSerializationTest` memeriksa cache database cold/warm dan payload model beserta kategori.

- Tes Node akhir: `node --test tests/*.test.mjs`, 92/92 lulus; tes kontrol sold-out katalog memastikan tombol yang sama dinonaktifkan pada stok nol dan memakai label `sold_out` versus `set_one` pada stok tersedia.
- Browser main `:8000`: 12 tombol home, 8 tombol katalog, quick-view add + quantity, dan tombol PDP disabled; label dan JSON-LD `OutOfStock` sesuai. Klik paksa: 0 POST. Page/console/network error: 0. Bukti `final-main-results.json` di root bukti privat pada laporan upgrade.
- Synthetic `:8031` menerima stok 5 dan alur klik checkout/cancel berjalan; bukan hasil pada database utama.
- Review independen terakhir statis membaca file tes untracked dan menyampaikan dua temuan. Keduanya dipatahkan oleh run guarded MySQL dan Node; lihat bagian “Handoff round2 final” pada laporan upgrade.
