# Inventarisasi Teks Seluruh Permukaan Toko

**Brand:** ZAFIRA — La Maison de Lumière  
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/inventarisasi-seluruh-teks-permukaan-toko`  
**Keputusan Arsitektur:** `COUNCIL_DECISIONS.md` #042  
**Tanggal Inventarisasi:** 2026-10-20  
**Pembaruan:** Lihat `STOREFRONT_TEXT_REWRITE_LOG.md` untuk perubahan E-01–E-13 pada tanggal yang sama.  
**Total Berkas Tampilan:** 42 berkas Blade (`resources/views/**/*.blade.php`), diperbarui FE-MIG-20261022 dengan root fallback React dan lifecycle halaman privat. Inventaris JSX migrasi tercatat pada `REACT_INERTIA_MIGRATION.md`; teks native memakai kamus yang sama, bukan copy baru.

---

## Tambahan UI-LOCAL-20261025

Total setelah penambahan: **44 berkas Blade**. Inventaris di bawah tetap merekam baseline42berkas, bukan verbatim versi baru.

| Berkas | Peran/copy |
|---|---|
| `resources/views/admin/profile.blade.php` | Profil admin: identitas read-only, keamanan dan ubah kata sandi melalui endpoint existing. Copy `profile_ui` tersedia di5locale. |
| `resources/views/components/theme-preference.blade.php` | Initializer preferensi tema lintas19head, tidak menambah copy pengguna. |

Navigasi profil beranda kini memakai label `shop_ui.header.auth_profile` bagi pengguna masuk dan `login_aria` bagi tamu. Riwayat diberi label concierge/janji temu, bukan pesanan. Halaman akun memakai section kontak/keamanan/privasi/riwayat. Lihat [kontrak dan bukti UI-LOCAL](ADMIN_PROFILE_THEME_STOCK_2026-10-25.md).

## 1. Peta Klaster Permukaan Teks

| Klaster | Jumlah Berkas | Nada Bicara Sesuai Matriks | Persona Target |
|---|---|---|---|
| **1. Storefront Publik** | 5 | Editorial, puitis terukur, anggun, inspiratif | Madame Vivienne (Klien VIP) |
| **2. Akun & Autentikasi Pelanggan** | 10 | Jelas, aman, adem, meyakinkan | Pelanggan terdaftar |
| **3. Konsol Admin / Atelier** | 9 | Fungsional, efisien, padat data, netral | Artisan & Operator Atelier |
| **4. Surel Transaksional** | 4 | Sopan-formal, hangat, bernomor referensi | Pembeli menunggu kabar |
| **5. Halaman Galat (HTTP Errors)** | 7 | Membantu, tidak menyalahkan, solutif | Pengguna yang tersesat |
| **6. Parsial, Komponen & Sitemap** | 7 | Modular, konsisten, terstandarisasi | Lintas permukaan |

---

## 2. Inventaris Detail Berkas & Teks Kunci

Semua teks kunci di bawah dikutip verbatim dari berkas (title, h1/h2, tombol, subjek surel) per 2026-10-20.

### Klaster 1: Storefront Publik (5 Berkas)

| Berkas | Peran | Teks kunci (verbatim) |
|---|---|---|
| `resources/views/welcome.blade.php` | Beranda | Title "ZAFIRA — La Maison de Lumière · Haute Couture"; h1 "Lahir dari Cahaya, Dibentuk oleh Keanggunan"; h2 "Temukan dunia Anda", "Objek-objek penuh pesona"; tombol "Buka Ruang Klien", pemilih pita "Pita Emas (<span lang=\"fr\">Or</span>)"; pemilih bahasa ID/EN/FR |
| `resources/views/catalog/index.blade.php` | Katalog | Title/h1 dari kamus `$t['title']`; tombol `$t['apply']`, label stok habis `$t['sold…']` (teks tertranslasi di controller) |
| `resources/views/products/show.blade.php` | PDP | Title = nama produk; h2 "Panduan Ukuran", "Lengkapkan kisah Anda"; tombol "Panduan ukuran"; catatan "Ukuran dan monogram hanya melalui concierge; keduanya tidak bisa dipesan dari Tas Belanja." |
| `resources/views/shop/index.blade.php` | Belanja/checkout | Title "Belanja — ZAFIRA La Maison de Lumière"; h1 "Belanja di Maison"; h2 "Tas Belanja", "Alamat pengiriman"; tombol "Kosongkan tas", "Tambah alamat", "Konfirmasi pesanan"; formulir "Kode pelacakan pesanan", tombol "Hapus kode dari formulir" |
| `resources/views/shop/request.blade.php` | Helper JS `shopRequest` | Pesan galat jaringan/sesi (mis. "Sesi berakhir") |

### Klaster 2: Akun & Autentikasi Pelanggan (10 Berkas)

| Berkas | Teks kunci (verbatim) |
|---|---|
| `resources/views/auth/login.blade.php` | Title "Masuk — ZAFIRA"; h1 "Selamat datang kembali."; tombol "Masuk ke akun", "Lihat" |
| `resources/views/auth/register.blade.php` | Title "Buat Akun — ZAFIRA"; h1 "Bergabung dengan Maison."; tombol "Buat akun pribadi" |
| `resources/views/auth/totp.blade.php` | Title "Verifikasi Dua Langkah — ZAFIRA"; tombol "Verifikasi & masuk", "Pakai kode cadangan" |
| `resources/views/auth/password-email.blade.php` | Title "Lupa Kata Sandi — ZAFIRA"; tombol "Kirim tautan pemulihan" |
| `resources/views/auth/password-reset.blade.php` | Title "Atur Ulang Kata Sandi — ZAFIRA"; tombol "Simpan kata sandi baru" |
| `resources/views/auth/_social.blade.php` | Parsial tombol masuk Google/Apple |
| `resources/views/account/show.blade.php` | Title "Profil Saya — ZAFIRA"; h1 "Bonjour, {nama}."; tombol "Simpan profil", "Kirim ulang tautan", "Keluar" |
| `resources/views/account/totp-enrol.blade.php` | h1 "Aktifkan verifikasi dua langkah."; tombol "Aktifkan" |
| `resources/views/account/totp-recovery.blade.php` | Title "Kode Cadangan — ZAFIRA"; h1 "Simpan kode cadangan Anda." |
| `resources/views/account/cart-reminder-unsubscribed.blade.php` | h1 "Pengingat keranjang dihentikan"; tombol "Hentikan pengingat" |

### Klaster 3: Konsol Admin Atelier (9 Berkas)

| Berkas | Teks kunci (verbatim) |
|---|---|
| `resources/views/admin/layouts/app.blade.php` | Title "{judul} — ZAFIRA"; tombol "Keluar" |
| `resources/views/admin/dashboard.blade.php` | h2 "Kategori — tampil sebagai pill filter di beranda", "Produk — kelola katalog…"; tombol "Cari", "Simpan", "Hapus" |
| `resources/views/admin/login.blade.php` | Title "Masuk Admin — ZAFIRA"; h1 "Masuk admin."; tombol "Masuk ke dashboard" |
| `resources/views/admin/totp.blade.php` | h1 "Verifikasi dua langkah."; tombol "Verifikasi", "Pakai kode cadangan" |
| `resources/views/admin/totp-enrol.blade.php` | h1 "Aktifkan verifikasi dua langkah."; tombol "Aktifkan" |
| `resources/views/admin/totp-enrol-recovery.blade.php` | h1 "Simpan Kode Cadangan" |
| `resources/views/admin/auth-events.blade.php` | Log peristiwa autentikasi; tombol "Filter" |
| `resources/views/admin/components/table-controls.blade.php` | Kontrol tabel; tombol "Baris ringkas" |
| `resources/views/admin/operations-summary.blade.php` | Tambahan 2026-10-21, FR-REM-007: "Ringkasan operasional", "Periode kejadian", "Terapkan / Segarkan", "Backlog saat ini — seluruh umur", "Belum diperiksa". Bahasa Indonesia eksplisit melalui `lang="id"`; status parsial/unknown bukan kesehatan menyeluruh. JS `public/js/admin-operations-summary.js` mempertahankan snapshot dan mengumumkan "Data lama" saat refresh gagal. Flag mati secara default; lokalisasi panel lima bahasa dan pembaca layar belum terbukti. |

### Klaster 4: Surel Transaksional (4 Berkas)

| Berkas | Subjek (verbatim, dari `app/Mail/*`) |
|---|---|
| `resources/views/mail/newsletter-welcome.blade.php` | "La Lettre ZAFIRA · Selamat datang" |
| `resources/views/mail/concierge-requested.blade.php` | "Konfirmasi Rendez-vous · ZAFIRA Concierge" |
| `resources/views/mail/cart-reminder.blade.php` | "Pilihan Anda masih menunggu di La Maison ZAFIRA" |
| `resources/views/mail/order-status-changed.blade.php` | "{status} · #{id pesanan}" (dari `OrderStatusChanged::MESSAGES`) |

### Klaster 5: Halaman Galat HTTP (7 Berkas)

| Berkas | Judul (verbatim) |
|---|---|
| `resources/views/errors/layout.blade.php` | Kerangka: title/h1 dari `@section('title')` |
| `resources/views/errors/403.blade.php` | "Akses tidak diizinkan" |
| `resources/views/errors/404.blade.php` | "Halaman tidak ditemukan" |
| `resources/views/errors/419.blade.php` | "Sesi berakhir" |
| `resources/views/errors/429.blade.php` | "Terlalu banyak permintaan" |
| `resources/views/errors/500.blade.php` | "Ada kendala di sisi kami" |
| `resources/views/errors/503.blade.php` | "Sedang dalam perawatan" |

### Klaster 6: Parsial, Komponen & Sitemap (7 Berkas)

| Berkas | Teks kunci (verbatim) |
|---|---|
| `resources/views/components/brand-mark.blade.php` | Logo tipografis ZAFIRA |
| `resources/views/components/react-root.blade.php` | Boundary fallback Blade → native React; tidak menambah copy pengguna. Teks kontrol password mengikuti data kamus; input/interaksi aktif tidak ditimpa. |
| `resources/views/components/private-page-lifecycle.blade.php` | Tanpa copy pengguna. Menyembunyikan dokumen privat saat ditinggalkan dan meminta pemeriksaan server saat BFCache dipulihkan. |
| `resources/views/partials/product-header.blade.php` | Tombol "Rendez-vous privat" |
| `resources/views/partials/product-footer.blade.php` | h2 "Apa yang Anda cari?", "Sebuah waktu, khusus untuk Anda."; tombol "Rendez-vous", "Hapus" |
| `resources/views/partials/purchase-cart.blade.php` | Eyebrow "Pembelian katalog", h2 "Tas Belanja (0)"; tombol "Jelajahi koleksi", "Konsultasikan isi tas lama" (ringkasan "Tas belanja versi lama di peramban ini") |
| `resources/views/sitemap.blade.php` | XML untuk mesin pencari (tanpa teks pengguna) |

---

## 3. Catatan Audit Teks Awal

- **Konsistensi Nama Brand:** Seluruh 39 berkas Blade telah menggunakan nama tunggal **ZAFIRA** atau **ZAFIRA: La Maison de Lumière** pada judul dokumen dan elemen visual. Tidak ada kemunculan nama brand lama.
- **Bebas Istilah Murahan:** Tidak ditemukan satupun dari daftar frasa terlarang (`diskon gila`, `banting harga`, `promo heboh`, `klik di sini`).
- **Temuan untuk task perbaikan berikutnya:**
  - ~~Title beranda memuat label versi internal "Flagship v3.9"~~ — diperbaiki (E-01, `STOREFRONT_TEXT_REWRITE_LOG.md`).
  - ~~Tombol "Buat pesanan pending" di `shop/index`~~ — diperbaiki menjadi "Konfirmasi pesanan" (E-02).
  - ~~Istilah "token", "pending", "dihitung server", "tas pembelian standar", "(/akun)"~~ — diperbaiki (E-02–E-08).
  - Footer "Indonesia / ID" masih ada (E-10, belum diubah).
  - Campuran bahasa: "Rendez-vous", "Bonjour", "La Lettre" (Prancis, disengaja sebagai suara brand) berdampingan dengan istilah Indonesia; perlu keputusan konsisten di task berikutnya.
  - Tombol "Lihat" (tampilkan kata sandi) dan "Hapus" generik; label aksesibel perlu dicek.
