# Arsitektur Sistem Target (Target Architecture)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Fase:** Fase 5 — System Architecture Design  
**Framework:** Laravel ^13.0 (lock v13.36.0), PHP ^8.4.1  
**Tanggal:** 2026-10-10  

---

## 1. Arsitektur Berlapis (Layered Architecture Blueprint)

Aplikasi ZAFIRA menggunakan pola arsitektur terstruktur (*Clean Pragmatic Architecture*) yang memanfaatkan keunggulan ekosistem Laravel tanpa over-engineering:

```mermaid
graph TD
    subgraph Presentation Layer
        StorefrontViews[Storefront Views: Blade, Bodoni Moda, Tailwind v4]
        AdminViews[Admin Atelier Views: Unified Shell, Sidebar, KPI Cards]
        ClientJS[Vanilla JS app.js: Cart Drawer, Monogram, Quick View, Modals]
    end

    subgraph HTTP & Security Gateway
        WebRouting[routes/web.php with Throttle Rate Limiters]
        SecurityMW[SecurityHeaders Middleware: nosniff, SAMEORIGIN, CSP-ready]
        AuthGuards[Multi-Guard Auth: 'web' for Customers, 'admin' for Atelier Team]
    end

    subgraph Application & Business Logic
        StorefrontCtrl[StorefrontController: Catalog, Show, Concierge, Subscribe]
        AdminCtrls[Admin Controllers: Dashboard, Product, Category, Auth]
        AuthCtrls[AuthController & ProfileController]
        UserObs[UserObserver: Automatic UserInfoBackup snapshot on create]
    end

    subgraph Caching & Background Services
        StorefrontCache[Laravel Cache: storefront:products:v1 & categories:v1 (LRU 300s)]
        QueueService[Laravel Queue: Database driver for asynchronous Mails]
    end

    subgraph Domain & Persistence Layer
        EloquentModels[Eloquent Models: Product, Category, User, Admin, ConciergeRequest, UserInfoBackup]
        Database[MariaDB 10.4.28 / SQLite In-Memory for Testing]
    end

    Presentation Layer --> HTTP & Security Gateway
    HTTP & Security Gateway --> Application & Business Logic
    Application & Business Logic --> Caching & Background Services
    Application & Business Logic --> Domain & Persistence Layer
```

---

## 2. Batas Keamanan & Multi-Guard Isolation

1. **Guard `web` (Pelanggan):**
   - Menggunakan model `App\Models\User` dengan penyimpan kredensial di tabel `users`.
   - Melindungi rute `/akun` (profil dan riwayat pemesanan).
   - Registrasi dilindungi `UserObserver` yang menduplikasi profil secara aman ke `user_info_backups` untuk audit trail.
2. **Guard `admin` (Pengelola Atelier):**
   - Menggunakan model `App\Models\Admin` dengan penyimpan kredensial di tabel `admins`.
   - Melindungi seluruh rute `/admin/*` menggunakan middleware `auth:admin`.
   - Mengisolasi sesi sehingga tindakan logout pelanggan tidak memutuskan sesi kerja staf admin pada peramban yang sama.

---

## 3. Strategi Caching & Kinerja

- **Storefront Catalog Caching:**
  - Query katalog produk dan kategori dibungkus dalam `Cache::remember('storefront:products:v1', 300, ...)` dan `Cache::remember('storefront:categories:v1', 300, ...)`.
  - Halaman detail produk di-cache per-slug: `storefront:product:{slug}:v1`.
- **Cache Invalidation Terpusat:**
  - Setiap mutasi pada data produk (create/update/delete) atau kategori (create/update/delete) memanggil metode statis `AdminCategoryController::flushStorefrontCache()`.
  - Hal ini menjamin bahwa pelanggan langsung melihat perubahan harga, foto, atau kategori baru tanpa jeda delay atau *stale cache*.

---

## 4. Penanganan Kesalahan & Ketahanan Sistem

- **Slug Not Found:** Menghasilkan 404 terstruktur via `firstOrFail()` yang diarahkan ke halaman error elegan.
- **Integritas Referensial Kategori:** Pencegahan penghapusan kategori yang masih digunakan produk melalui validasi `Product::where('product_cat', $category->cat_id)->exists()` yang mengembalikan feedback informatif tanpa crash 500.
- **Fallback Stored Procedure:** Penggunaan metode Eloquent `Category::getById($cid)` menjamin aplikasi berjalan 100% mulus baik di database yang mendukung stored procedure MySQL maupun di database SQLite lokal.
