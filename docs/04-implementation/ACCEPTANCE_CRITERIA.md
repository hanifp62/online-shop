# Kriteria Penerimaan Spesifik (Acceptance Criteria)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal:** 2026-10-10  

---

## 1. Kriteria Penerimaan Storefront Pelanggan

### AC-001 (Beranda & Hero Brand ZAFIRA)
- [x] Beranda merender judul editorial "ZAFIRA: La Maison de Lumière".
- [x] Hero image beresolusi tinggi dimuat dengan prioritas `fetchpriority="high"`.
- [x] Kartu produk memuat foto, harga terformat rupiah (`Rp...`), nama kreasi, dan badge (misal: 'Eksklusif', 'Warisan Nusantara').
- [x] Respon server beranda dalam cache hit < 50ms.

### AC-002 (Filter Kategori Dinamis)
- [x] Pill kategori dirender berdasarkan data tabel `categories`.
- [x] Mengklik pill kategori memfilter kartu produk di halaman secara instan tanpa reload browser.
- [x] Pill kategori aktif memiliki aksen visual kontras yang jelas.

### AC-003 (Halaman Detail Produk & Kriya)
- [x] Akses ke `/produk/{slug}` menampilkan halaman detail produk dengan layout split elegan (foto besar di kiri, narasi dan tombol aksi di kanan).
- [x] Menampilkan data kriya lengkap: Material, Komposisi, Asal/Provenance, Keahlian/Craft, dan Petunjuk Perawatan.
- [x] Menampilkan 3 produk terkait (*related creations*) dari kategori yang sama.
- [x] Mengakses slug yang tidak terdaftar menghasilkan halaman 404 yang terstruktur.

### AC-004 (Tas Belanja & Personalisasi Monogram)
- [x] Mengklik tombol "Tambah ke Tas" membuka Cart Drawer dari sisi kanan layar.
- [x] Jumlah item tas belanja (*cart count badge*) ter-update secara otomatis di header.
- [x] Pengguna dapat menambahkan 2–3 huruf inisial monogram pada item di tas belanja; karakter non-alfabet otomatis dibersihkan.
- [x] Nilai subtotal dihitung secara real-time berdasarkan kuantitas masing-masing item.

### AC-005 (Alur Checkout Concierge)
- [x] Menekan tombol "Lanjut bersama Client Advisor" di tas belanja mengubah modal ke mode ringkasan pesanan.
- [x] Formulir checkout menampilkan ringkasan produk (nama kreasi × kuantitas + total harga).
- [x] Pengiriman formulir menyimpan pesanan ke `orders`/`order_items` dan mengosongkan isi tas belanja. *(Koreksi 2026-10-19: klaim lama `concierge_requests` sudah basi.)*
- [x] Menampilkan toast konfirmasi sukses berwarna emas safir.

---

## 2. Kriteria Penerimaan Panel Administrasi (Admin Atelier)

### AC-006 (Workspace Terpadu & Metrik Real-Time)
- [x] Panel admin memiliki sistem navigasi terpadu (Sidebar navigasi, top bar breadcrumbs, profil staf).
- [x] Kartu metrik KPI menampilkan angka riil untuk Total Pengguna, Arsip Audit, Pesanan Concierge, Subscriber Buletin, Total Produk, dan Kategori Koleksi.
- [x] Tampilan responsif pada perangkat desktop maupun tablet.

### AC-007 (Manajemen Produk Komprehensif)
- [x] Formulir tambah produk memungkinkan pengisian judul, kategori, harga, URL foto, badge, warna, deskripsi, dan seluruh narasi kriya.
- [ ] Jika slug produk dikosongkan, sistem secara otomatis menghasilkan slug unik yang ramah SEO. *(Belum terbukti: race condition dan update slug nullable.)*
- [x] Pengubahan atau penghapusan produk memicu pembersihan cache storefront secara otomatis.

### AC-008 (Manajemen Pesanan & Alur Status)
- [x] Admin dapat memfilter daftar permintaan concierge/pesanan berdasarkan status (`new`, `contacted`, `confirmed`, `completed`, `cancelled`) atau pencarian nama/email/nomor HP.
- [x] Admin dapat mengubah status pesanan melalui dropdown status dan menyimpan perubahannya.
- [x] Perubahan status langsung tercermin pada halaman `/akun` milik pelanggan yang bersangkutan.
