# Rincian Backlog Task Implementasi (Implementation Tasks)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal:** 2026-10-10  

---

## Matriks Rincian Task

| Task ID | Requirement ID | Deskripsi Task | Modul / File Terkait | Prioritas | Dependensi | Acceptance Criteria | Metode Pengujian | Risiko | Bukti Penyelesaian |
|---|---|---|---|---|---|---|---|---|---|
| **TSK-001** | NFR-005 | Git initialization & snapshot baseline | `.git`, `.gitignore` | Kritis | Tidak ada | Riwayat git aktif dengan commit baseline aman | `git status`, `git log` | Rendah | Commit `2136f29` tercatat |
| **TSK-002** | NFR-005 | Perbaikan izin eksekusi binary Unix (`+x`) | `vendor/bin/*`, `node_modules/.bin/*` | Kritis | TSK-001 | Pint dan Vite dapat dieksekusi tanpa error code 126 | `vendor/bin/pint --test` | Rendah | Output pint passed terverifikasi |
| **TSK-003** | NFR-005 | Native platform binding darwin-arm64 | `node_modules`, `package.json` | Kritis | TSK-002 | `npm run build` sukses memproduksi bundle CSS & JS | `npm run build` | Rendah | Bundle 94.5 kB CSS / 16.8 kB JS terbit |
| **TSK-004** | NFR-003 | Inisialisasi MariaDB lokal & migrasi skema | `database/migrations/*`, MariaDB | Kritis | TSK-002 | 29 file migrasi terdeteksi; status eksekusi dan hasil seeder perlu diverifikasi ulang sebelum klaim lulus diterima; target data 12 produk & 8 kategori tetap dicatat sebagai konteks historis. | `php artisan migrate:status`, tinker | Rendah | Database `ecommerce` aktif dan terisi |
| **TSK-005** | FR-001, FR-002 | Verifikasi storefront publik & dynamic filter | `welcome.blade.php`, `StorefrontController.php` | Tinggi | TSK-004 | Rute `/` status 200, filter kategori bekerja instan | `php artisan test --filter=StorefrontTest` | Rendah | 7 feature tests passed |
| **TSK-006** | FR-003 | Verifikasi detail produk & atribut kriya | `products/show.blade.php`, `StorefrontController.php` | Tinggi | TSK-004 | Rute `/produk/{slug}` menampilkan narasi kriya & related items | `php artisan test --filter=BrandHeritageTest` | Rendah | 8 brand heritage tests passed |
| **TSK-007** | FR-004, FR-005 | Verifikasi tas belanja & alur checkout concierge | `resources/js/app.js`, `StorefrontController.php` | Tinggi | TSK-005 | Keranjang menyimpan monogram, modal beralih ke mode checkout | `php artisan test --filter=LegacyPatchTest` | Rendah | 22 legacy patch tests passed |
| **TSK-008** | FR-007, FR-008 | Verifikasi otentikasi pelanggan & halaman profil | `AuthController.php`, `ProfileController.php` | Tinggi | TSK-004 | Password lemah ditolak, registrasi memicu backup, profil update | `php artisan test --filter=AuthTest` | Rendah | 5 auth tests passed |
| **TSK-009** | FR-010, NFR-004 | Redesain sistem tata kelola Admin Atelier | `resources/views/admin/*`, layout terpadu | Tinggi | TSK-004 | Admin memiliki layout sidebar modular, KPI cards elegan, dan filter rapi | Pengujian Browser Subagent & HTTP 200 | Sedang | Selesai pada Fase 6 Prototype |
| **TSK-010** | FR-011, FR-012 | Manajemen produk & kategori terstruktur di admin | `AdminProductController.php`, `AdminCategoryController.php` | Tinggi | TSK-009 | CRUD produk & kategori bekerja dengan feedback toast dan auto flush cache | `php artisan test --filter=AdminManagementTest` | Rendah | 5 admin tests passed |
| **TSK-011** | FR-013 | Pembaruan status pesanan & sinkronisasi akun | `AdminDashboardController.php`, `ConciergeRequest.php` | Tinggi | TSK-009 | Update status di admin langsung terlihat di `/akun` pelanggan | Feature test & Browser testing | Rendah | Terverifikasi di backend & prototype |
| **TSK-012** | FR-001 s/d FR-014 | Pembangunan prototype interaktif & pengujian browser | `prototype/` / integrated views | Kritis | TSK-009 | Prototype dapat dijelajahi di browser nyata dengan seluruh flow utama | Browser Subagent interaction & video recording | Rendah | Fase 6 deliverable |
