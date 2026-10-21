# Log Penulisan Ulang Teks Toko (Sebelum → Sesudah)

**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/tulis-ulang-teks-dan-simpan-daftar-sebelum-sesudah`
**Sumber temuan:** `STOREFRONT_TEXT_EVALUATION.md` (E-01–E-13)
**Tanggal:** 2026-10-20

| ID | Berkas | Sebelum | Sesudah | Alasan |
|---|---|---|---|---|
| E-01 | `welcome.blade.php` (title) | ZAFIRA: La Maison de Lumière - Haute Couture Flagship v3.9 | ZAFIRA — La Maison de Lumière · Haute Couture | Label versi internal tidak boleh tampil di tab peramban atau mesin pencari. |
| E-02 | `shop/index.blade.php` (tombol, notice, JS) | Buat pesanan pending; "pending / belum dibayar" | Konfirmasi pesanan; "menunggu pembayaran. Pembayaran online belum tersedia. Tidak ada konfirmasi pembayaran otomatis." | Status database bocor ke label. Batas layanan tetap dinyatakan jujur; tidak ada janji bahwa atelier akan menghubungi. |
| E-02 | `shop/index.blade.php` (JS `statusLabel`) | Status mentah (pending, paid, …) | Pemetaan ke label Indonesia; status tak dikenal → "Status belum tersedia" | Pelanggan membaca bahasa sendiri; nilai internal API tidak berubah dan tidak bocor ke layar. |
| E-03 | `shop/index.blade.php`, `GuestCheckoutController` | Token pelacakan; Hapus token dari formulir | Kode pelacakan; Hapus kode dari formulir | Jargon teknis diganti istilah awam. Nama field API tetap `token`. |
| E-04 | `shop/index.blade.php`, `purchase-cart.blade.php`, `products/show.blade.php` | Tas pembelian standar; Pembelian standar | Tas Belanja; Pembelian katalog | Satu istilah di semua halaman. Kata "standar" ambigu. |
| E-04 | `resources/js/app.js` (toast personalisasi) | … bukan pembelian standar. | … bukan pembelian katalog. | Istilah sama dengan halaman produk dan tas. |
| E-05 | `welcome.blade.php` | Buka Portal Klien Lengkap (/akun) | Buka Ruang Klien | Rute teknis tidak tampil di tombol. |
| E-05 | `public/js/zafira-core.js` (`profile_full_portal`, 4 bahasa) | Buka Portal Akun Lengkap → / Open Full VIP Account Portal → / Accéder à l'Espace Client Complet → / Apri Portale Clienti Completo → | Buka Ruang Klien → / Open Client Room → / Ouvrir l'Espace Client → / Apri Area Clienti → | Tanpa ini kamus i18n menimpa label baru saat halaman dimuat. |
| E-06 | `purchase-cart.blade.php` | Tas lama tersimpan di peramban; Konsultasikan tas lama | Tas belanja versi lama di peramban ini; Konsultasikan isi tas lama | Konteks migrasi lebih jelas. Perilaku data tidak berubah. |
| E-07 | `shop/index.blade.php` | Diskon voucher dihitung server … | Potongan voucher dan total akhir dipastikan saat pesanan dibuat. | Arsitektur backend tidak perlu disebut. |
| E-08 | `shop/index.blade.php` | Kelola pesanan nyata dari koleksi ZAFIRA. | Pantau dan kelola pesanan koleksi ZAFIRA Anda. | Kata "nyata" memicu keraguan; buang kata tak perlu. |
| E-09 | `welcome.blade.php` | Pita Emas (Or); Pita Safir (Saphir) | Sama, dengan `<span lang="fr">` | Aksen Prancis dipertahankan; pembaca layar melafalkan benar. Terjemahan i18n tetap berjalan pada span luar. |
| E-10 | footer | Indonesia / ID | Tidak diubah | Skor 2; menunggu keputusan peran elemen. |
| E-11–E-13 | — | — | Tidak diubah | Skor 1, teladan. |

## Batas
- Penilaian dan penulisan ulang oleh tim internal; belum diuji dengan pengguna.
- Kamus `public/js/zafira-core.js` hanya diubah untuk kunci `profile_full_portal`; kunci lain dan `catalog/labels.php` tidak diubah.
- Review independen (sesi `ses_ea6c3c89affetUTDvf7eO9KgVl`) menemukan 1 HIGH + 2 MEDIUM; ketiganya diperbaiki di atas.
