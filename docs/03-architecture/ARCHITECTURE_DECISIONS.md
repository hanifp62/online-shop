# Catatan Keputusan Arsitektur (Architecture Decision Records — ADR)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Status:** Approved & Implemented  
**Tanggal:** 2026-10-10 (ADR-006–009 ditambahkan 2026-10-14)

---

## ADR-001: Pemisahan Guard Otentikasi Multi-Guard (`web` vs `admin`)

- **Status:** Diterima (Accepted).
- **Konteks:** Aplikasi melayani dua tipe pengguna dengan batasan hak akses yang sangat berbeda: pelanggan butik dan pengelola atelier.
- **Keputusan:** Menggunakan konfigurasi multi-guard bawaan Laravel di `config/auth.php` dengan guard `web` (model `User`) dan guard `admin` (model `Admin`). Sesi login admin dilindungi rute middleware `auth:admin`. Logout pelanggan secara cerdas mengecek status guard admin agar tidak memutuskan sesi kerja staf pada peramban yang sama.
- **Konsekuensi:** Keamanan terisolasi penuh; kredensial staf tidak bercampur dengan tabel pengguna umum.

---

## ADR-002: Strategi Caching LRU Storefront dengan Auto-Flush Tersentralisasi

- **Status:** Diterima (Accepted).
- **Konteks:** Storefront ZAFIRA menerima volume kunjungan tinggi dengan katalog produk yang tidak berubah setiap detik, namun ketika pengelola butik mengubah harga atau kategori, perubahan harus langsung terlihat oleh pelanggan.
- **Keputusan:** Membungkus pembacaan produk dan kategori dalam `Cache::remember` berdurasi 300 detik. Setiap operasi penambahan, pengubahan, atau penghapusan produk maupun kategori memicu metode statis `AdminCategoryController::flushStorefrontCache()`.
- **Konsekuensi:** Waktu respon beranda cepat di cache hit.
- **Koreksi 2026-10-14:** Klaim *zero stale cache* hanya berlaku untuk mutasi admin produk/kategori. Perubahan stok dari checkout dan pembatalan tidak memanggil `flushStorefrontCache()`, sehingga snapshot katalog dan JSON-LD dapat tertinggal sampai TTL 300 detik berakhir. Lihat ADR-008. Angka "< 50ms" adalah klaim lama tanpa bukti pengukuran terlampir (NOT_MEASURED).

---

## ADR-003: Penanganan Stored Procedure Portabel via Eloquent Model

- **Status:** Diterima (Accepted).
- **Konteks:** Proyek warisan memiliki stored procedure `getcat(cid)` dari dump MySQL Windows, namun pengujian lokal dan lingkungan pengembang menggunakan SQLite atau server MariaDB yang tabel prosedurnya belum di-upgrade.
- **Keputusan:** Mengimplementasikan method statis `Category::getById(int $cid)` menggunakan Eloquent query berparameter murni, dan membungkus migrasi DDL procedure dalam blok `try...catch` yang graceful.
- **Konsekuensi:** Query aplikasi tidak bergantung pada stored procedure, sehingga kompatibel secara desain dengan MySQL, MariaDB, dan SQLite. Klaim runtime 100% lintas-engine belum dijamin: verifikasi MySQL/MariaDB **NOT_RUN**; bukti saat ini hanya mencakup lingkungan SQLite.

---

## ADR-004: Alur Checkout Terintegrasi Concierge untuk Produk Kemewahan Tinggi

- **Status:** Diterima (Accepted).
- **Konteks:** Produk ZAFIRA adalah busana adibusana bernilai jutaan hingga puluhan juta rupiah yang memerlukan konsultasi ukuran dan penanganan privat oleh penasihat butik (*Client Advisor*).
- **Keputusan:** Memanfaatkan modal concierge yang adaptif: dapat berfungsi sebagai booking janji temu tatap muka sekaligus mode pemesanan langsung dengan ringkasan daftar belanjaan. Status pesanan dikelola secara transparan oleh admin atelier dan dapat dipantau oleh pelanggan di `/akun`.
- **Konsekuensi:** Memberikan pengalaman sentuhan manusiawi kelas atas (*bespoke luxury experience*) yang sesuai dengan karakter produk, sebelum payment gateway otomatis diaktifkan di masa depan.

---

## ADR-005: Preservasi Identitas Visual Storefront dan Penyelarasan Sistem Admin Atelier

- **Status:** Diterima (Accepted).
- **Konteks:** Tampilan sisi pengguna ZAFIRA telah memiliki kualitas desain dan tipografi yang sangat matang (skor estetika tinggi), sementara halaman admin sebelumnya monolitik dan belum seimbang.
- **Keputusan:** Mempertahankan 100% aset visual, warna, dan font storefront, serta membangun sistem antarmuka admin atelier modern yang mengadopsi prinsip desain yang sama (palet Safir, Emas, Linen pekat, sidebar ramping, dan KPI terstruktur).
- **Konsekuensi:** Tercipta keharmonisan visual antara sisi depan toko dan ruang kerja pengelola atelier.

---

## ADR-006: Kontrak Cart Pembelian Terpadu

- **Status:** Diterima (Accepted).
- **Konteks:** Beranda, katalog, PDP, drawer, dan `/belanja` sebelumnya berisiko memiliki representasi cart berbeda.
- **Keputusan (koreksi 2026-10-19):** Cart pembelian standar kini disimpan di tabel `carts` dan `cart_items` melalui `CartStore`, dengan transaksi dan lock baris cart. Session `cart` adalah sumber migrasi legacy yang dibaca lalu dihapus, bukan penyimpanan utama. `POST /cart/items` tetap menyediakan semantik SET untuk kompatibilitas; UI memakai `POST /cart/items/{productId}/delta` untuk perubahan relatif. Ukuran dan monogram tetap alur concierge, bukan atribut pembelian standar. Data localStorage legacy `zafira-cart` dan `zafira-cart:v1` dipertahankan untuk rekonsiliasi terbaca, tanpa merge otomatis atau duplikasi.
- **Mutasi UI:** `cartRequest()` di `resources/js/app.js` memasukkan setiap aksi ke antrean promise. Fungsi *intent* membaca ulang `/cart` saat giliran aksi tiba; penambahan dan perubahan jumlah mengirim delta yang diterapkan server di bawah lock cart, bukan SET berdasarkan jumlah lokal. Request gagal tidak memblokir antrean. Undo mengirim delta sebesar `removed_quantity` dari respons penghapusan. Batas: antrean tidak dibatasi panjangnya; teks status dapat sesaat menampilkan "diperbarui" saat aksi berikutnya masih berjalan.
- **Konsekuensi:** Semua titik pembelian membaca sumber yang sama. Aksi bertumpuk dieksekusi berurutan dengan state terbaru. Halaman katalog memakai pesan inline `#catalog-feedback`, bukan toast dan badge header (celah UX terbuka).

## ADR-007: Idempotensi Checkout dan Model Stok

- **Status:** Diterima (Accepted untuk SQLite dan kontrak aplikasi; validasi engine produksi NOT_RUN).
- **Konteks:** Retry jaringan dan dua request bersamaan dapat membuat order ganda, mengurangi stok ganda, atau memakai voucher lebih dari batas.
- **Keputusan:** Kunci idempotensi diperiksa ulang di dalam transaksi/lock sebelum mutasi. Payload berbeda dengan kunci sama menghasilkan 409; payload sama replay order lama. Retry hanya untuk error SQLite busy/locked, maksimal tiga kali, dengan 503 setelah habis. Model stok yang dipilih adalah **Model A: stok tersimpan sebagai integer pada produk dan dikurangi atomik secara kondisional saat checkout**. Cancel memakai compare-and-set dan mengembalikan stok sekali.
- **Konsekuensi:** Harness dua proses membuktikan overlap dan kasus stok tepat satu pada SQLite. MariaDB/MySQL tetap perlu verifikasi terpisah sebelum klaim lintas-engine.

## ADR-008: Freshness JSON-LD Ketersediaan Produk

- **Status:** Diterima (Accepted).
- **Konteks:** JSON-LD harus tidak mengklaim `InStock` untuk produk tidak aktif atau stok nol, tetapi pembacaan katalog memakai cache.
- **Keputusan:** `InStock` hanya bila cache memuat `is_active=true` dan `stock>0`; selain itu `OutOfStock`. TTL 300 detik dipertahankan, dan cache lama tanpa field stok tidak boleh menghasilkan klaim `InStock`. Mutasi admin membersihkan cache. Perubahan checkout non-admin terlihat setelah TTL atau invalidasi berikutnya.
- **Konsekuensi:** Klaim freshness dibatasi oleh TTL 300 detik. Verifikasi mencakup stok nol, stok positif, nonaktif, cache lama, dan refresh setelah TTL.
- **Revisi 2026-10-14 (lanjutan):** Halaman produk (PDP) tidak lagi memakai cache `storefront:product:{slug}:v1`; status aktif dan stok dibaca langsung dari database pada setiap request, sehingga JSON-LD mengikuti hasil checkout, cancel, dan perubahan admin pada request berikutnya. Test regresi: `StorefrontAvailabilityTest` (6 test). Harga: satu query tambahan per kunjungan PDP. Cache beranda/katalog dan produk terkait tetap 300 detik; kartu di sana tidak menampilkan stok, dan server tetap memvalidasi stok saat tambah ke tas serta checkout. Target freshness numerik belum disetujui pemilik produk.

## ADR-009: Profil Manual Native Terisolasi

- **Status:** Diterima (Accepted untuk uji lokal).
- **Konteks:** Uji manual harus memakai `php artisan serve` native tanpa menyentuh MySQL operasional, email nyata, pembayaran, atau secrets produksi.
- **Keputusan:** Gunakan root/runtime/profile sintetis yang namespaced, SQLite terpisah, session dan cache `file` di root dummy, queue `sync`, mail `log`, log `single`, dan port 8017. Preflight memeriksa fallback `.env`, cached config, external env, symlink, dan sink runtime sebelum migrasi/fixture.
- **Konsekuensi:** Data fixture dapat bertahan saat restart dan dapat diulang. MariaDB/MySQL, screen reader, dan user study tetap NOT_RUN.

## ADR-010: Status Pembayaran `paid` Dinonaktifkan pada Lingkup Pending-Only

- **Status:** Diterima (Accepted untuk lingkup uji lokal); integrasi pembayaran NOT_IMPLEMENTED.
- **Konteks:** Sebelumnya admin dapat mengubah pesanan `pending` menjadi `paid` lewat form dasbor atau `PATCH /admin/orders/{id}` tanpa bukti pembayaran apa pun. Tidak ada gateway, webhook, atau rekonsiliasi. Pembayaran punya tahap berbeda (authorization, capture, processing, settlement) yang belum dimodelkan.
- **Keputusan:** `CheckoutService::transition` menolak target `paid` dengan 422 (`Order::MANUAL_BLOCKED`); opsi `paid` disembunyikan dari form admin. `cancelled` tetap tersedia dari `pending`. Status `paid` baru boleh dibuka kembali bila ada sumber bukti pembayaran tepercaya dan definisi paid per metode pembayaran yang disetujui pemilik.
- **Konsekuensi:** Pesanan uji tetap `pending / belum dibayar`. Alur `paid → processing → shipped → completed` tidak dapat dicapai lewat antarmuka; test ulasan memakai fixture `completed` langsung di database. Tidak ada audit trail perubahan status (belum diimplementasikan). Shipping fee 0 bukan kebijakan gratis ongkir; keputusan produk masih terbuka.

## ADR-011: TOTP 2FA Admin Bertahap dengan Anti-Replay Compare-and-Swap

- **Status:** Diterima (Accepted untuk uji lokal SQLite); MySQL live NOT_RUN.
- **Konteks:** Konsol admin hanya dilindungi kata sandi. Admin lama belum punya secret TOTP, jadi wajib 2FA serentak berisiko mengunci staf.
- **Keputusan:** Admin bertanda `totp_required_at` wajib lolos challenge TOTP sebelum rute konsol (`EnsureAdminTotp`). Secret disimpan dengan cast `encrypted`, kode pemulihan disimpan sebagai hash bcrypt di tabel terpisah. Anti-replay memakai `UPDATE ... WHERE totp_last_used_at = <nilai lama>` atomik. Rincian dan bukti: [ADMIN_TOTP_2FA.md](ADMIN_TOTP_2FA.md).
- **Konsekuensi:** Admin legacy tanpa tanda tetap masuk (anti-lockout). Rotasi `APP_KEY` mengharuskan provisioning ulang. Belum ada reissue kode pemulihan.
