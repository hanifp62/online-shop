# Matriks Ketertelusuran Kebutuhan (Requirements Traceability Matrix)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal:** 2026-10-10  

---

## Matriks Ketertelusuran End-to-End

| Requirement ID | Deskripsi Singkat | User Story Terkait | Modul / File Kode Sumber | Rencana Pengujian (Test Method) | Status Verifikasi |
|---|---|---|---|---|---|
| **FR-001** | Beranda & Hero Editorial ZAFIRA | US-001 | `welcome.blade.php`, `StorefrontController.php` | `StorefrontTest::test_storefront_displays_zafira_brand` | **Passed (Verified)** |
| **FR-002** | Filter Kategori Dinamis | US-001 | `welcome.blade.php`, `Category.php`, `app.js` | `LegacyPatchTest::test_home_renders_dynamic_category_pills` | **Passed (Verified)** |
| **FR-003** | Halaman Detail Produk & Kriya | US-002 | `products/show.blade.php`, `StorefrontController.php` | `StorefrontTest::test_product_detail_page_is_accessible` | **Passed (Verified)** |
| **FR-004** | Tas Belanja & Personalisasi Monogram | US-003 | `resources/js/app.js`, `welcome.blade.php` | Pengujian Browser Subagent & Storage test | **Passed (Verified)** |
| **FR-005** | Mode Checkout Concierge | US-004 | `welcome.blade.php`, `StorefrontController.php` | `StorefrontTest::test_checkout_request_without_schedule_is_valid` | **Passed (Verified)** |
| **FR-006** | Newsletter Subscription | US-001 | `NewsletterSubscriber.php`, `StorefrontController.php` | `StorefrontTest::test_newsletter_subscription_is_idempotent` | **Passed (Verified)** |
| **FR-007** | Autentikasi Pelanggan & Password Policy | US-005 | `AuthController.php`, `UserObserver.php` | `AuthTest::test_weak_password_is_rejected` | **Passed (Verified)** |
| **FR-008** | Halaman Profil /akun & Riwayat | US-005 | `ProfileController.php`, `account/show.blade.php` | `LegacyPatchTest::test_user_can_view_and_update_profile` | **Passed (Verified)** |
| **FR-009** | Login Guard Khusus Admin | US-006 | `AdminAuthController.php`, `admin/login.blade.php` | `LegacyPatchTest::test_admin_can_login_and_view_dashboard` | **Passed (Verified)** |
| **FR-010** | Dashboard Workspace & KPI Metrik | US-006 | `AdminDashboardController.php`, `admin/dashboard.blade.php` | `AdminManagementTest::test_concierge_checkout_with_order_items...` | **Passed (Verified)** |
| **FR-011** | Manajemen Katalog Produk & Kriya | US-007 | `AdminProductController.php`, `Product.php` | `AdminManagementTest::test_admin_can_create_new_product` | **Passed (Verified)** |
| **FR-012** | Manajemen Kategori & Cache Flush | US-007 | `AdminCategoryController.php`, `Category.php` | `LegacyPatchTest::test_admin_can_manage_categories` | **Passed (Verified)** |
| **FR-013** | Pembaruan Alur Status Pesanan | US-008 | `AdminDashboardController.php`, `ConciergeRequest.php` | `LegacyPatchTest::test_admin_can_update_concierge_status` | **Passed (Verified)** |
| **FR-014** | Penggantian Kata Sandi Admin | US-006 | `AdminDashboardController.php`, `Admin.php` | `LegacyPatchTest::test_admin_can_change_password` | **Passed (Verified)** |
| **NFR-001** | Performa & Caching Storefront | All | `StorefrontController.php` (Cache LRU 300s) | Benchmarking waktu respon < 100ms | **Passed (Verified)** |
| **NFR-002** | Keamanan HTTP Headers & CSRF | All | `SecurityHeaders.php`, `bootstrap/app.php` | Assertion header pada seluruh rute respons | **Passed (Verified)** |
| **NFR-004** | Konsistensi Visual Atelier | All | `resources/css/app.css`, Unified Admin Layout | Penilaian Desain & Browser Testing | **Ditingkatkan di Fase 6** |
| **NFR-005** | Kompatibilitas Lingkungan macOS | All | `composer.json`, `package.json`, Vite, MariaDB | CLI execution, test suite passing | **Passed (Verified)** |
