# Audit Integrasi Full-Stack (Integration Audit)

**Proyek:** ZAFIRA (`online-shop`)  
**Metodologi:** End-to-end tracing dari layer UI/Browser → Routing → Middleware → Request Validation → Controller → Business Logic → Model/Database → Response → Visual Result.  
**Tanggal:** 2026-10-10  

---

## 1. Alur 1: Penjelajahan Katalog & Filter Kategori Dinamis

```mermaid
sequenceDiagram
    participant User as Browser (Storefront)
    participant Route as routes/web.php
    participant MW as SecurityHeaders
    participant Ctrl as StorefrontController@index
    participant Cache as Cache (storefront:products:v1)
    participant DB as MySQL/SQLite (products, categories)

    User->>Route: GET /
    Route->>MW: Handle security headers
    MW->>Ctrl: Invoke index()
    Ctrl->>Cache: Check 'storefront:products:v1' & 'categories:v1'
    alt Cache Miss
        Cache->>DB: Query Product::with('category') & Category::all()
        DB-->>Cache: Result collection
    end
    Cache-->>Ctrl: Products & Categories DTO
    Ctrl-->>User: Render welcome.blade.php + JSON-LD Organization
    Note over User: JS app.js mengaktifkan dynamic category pills & instant filter
```

- **Titik Putus / Risiko:** Cache invalidation harus selalu terpanggil saat admin memodifikasi produk atau kategori (`AdminCategoryController::flushStorefrontCache()`). Saat ini sudah terhubung dengan baik pada `AdminProductController` dan `AdminCategoryController`.
- **Status:** **Terintegrasi Penuh (Verified).**

---

## 2. Alur 2: Detail Produk & Transparansi Kriya (Heritage & Provenance)

```mermaid
sequenceDiagram
    participant User as Browser
    participant Route as GET /produk/{slug}
    participant Ctrl as StorefrontController@show
    participant DB as Database

    User->>Route: Klik kartu produk (misal: /produk/elan-dress)
    Route->>Ctrl: show('elan-dress')
    Ctrl->>DB: Product::where('product_slug', slug)->firstOrFail()
    Ctrl->>DB: Query 3 related products dari kategori yang sama
    Ctrl-->>User: Render products/show.blade.php + JSON-LD Product
    Note over User: Menampilkan detail kriya, asal bahan, perawatan, & tombol Tambah ke Tas
```

- **Titik Putus / Risiko:** Jika slug tidak ditemukan di database, controller melempar `firstOrFail()` yang menghasilkan respon 404 standar.
- **Status:** **Terintegrasi Penuh (Verified).**

---

## 3. Alur 3: Tas Belanja & Checkout Pemesanan (Concierge Checkout)

```mermaid
sequenceDiagram
    participant User as Browser (Cart Drawer)
    participant JS as app.js (Client State)
    participant Route as POST /concierge
    participant MW as Throttle:concierge (3/jam)
    participant Ctrl as StorefrontController@bookConcierge
    participant DB as concierge_requests table
    participant Queue as Mail::queue (ConciergeRequested)

    User->>JS: Tambah produk ke tas, klik "Lanjut bersama Client Advisor"
    Note over JS: Modal beralih ke mode checkout (order summary: produk x qty + total)
    User->>JS: Isi nama, email, nomor HP, catatan
    JS->>Route: POST /concierge (disertai payload JSON order_items)
    Route->>MW: Verifikasi rate limit per-IP
    MW->>Ctrl: bookConcierge(Request)
    Ctrl->>Ctrl: Validasi input & rangkum order_items ke kolom notes
    Ctrl->>DB: ConciergeRequest::create(validated + user_id)
    Ctrl->>Queue: Dispatch email konfirmasi via queue
    Ctrl-->>User: JSON response / Flash redirect
    Note over JS: Tas belanja dikosongkan, modal ditutup, toast sukses muncul
```

- **Titik Putus / Kesenjangan Produk:**
  1. Data pesanan saat ini dikonversi menjadi teks sederhana di kolom `notes` pada tabel `concierge_requests` (`[Pesanan Checkout] - Robe Élan (1x)...`).
  2. Tidak ada entitas relasional `orders` dan `order_items` terpisah untuk perhitungan kuantitas stok atau faktur resmi.
  3. Pengiriman email bergantung pada adanya worker antrean (`php artisan queue:work`).
- **Status:** **Fungsional Parsial (Perlu peningkatan ke skema Order terstruktur).**

---

## 4. Alur 4: Otentikasi & Sinkronisasi Profil Pelanggan

```mermaid
sequenceDiagram
    participant User as Browser
    participant Route as POST /register | POST /login
    participant Ctrl as AuthController
    participant Obs as UserObserver
    participant DB as users & user_info_backups

    User->>Route: Form submit register / login
    Route->>Ctrl: register() / login()
    Ctrl->>Ctrl: Validasi password ketat (min 8, huruf besar/kecil, angka, simbol)
    Ctrl->>DB: User::create(data)
    Note over Obs: UserObserver mendeteksi created -> buat UserInfoBackup
    Obs->>DB: UserInfoBackup::create(...)
    Ctrl-->>User: Auto login & redirect / dengan flash sukses
```

- **Titik Putus / Risiko:** Isolasi multi-guard: saat pelanggan logout (`/logout`), controller secara cerdas mengecek `!Auth::guard('admin')->check()` agar sesi admin yang aktif tidak terputus di browser yang sama.
- **Status:** **Terintegrasi Penuh (Verified).**

---

## 5. Alur 5: Manajemen Admin & Pembaruan Status Transaksi

```mermaid
sequenceDiagram
    participant Admin as Admin Browser
    participant Route as PATCH /admin/concierge/{id}
    participant MW as auth:admin
    participant Ctrl as AdminDashboardController@updateConcierge
    participant DB as concierge_requests table
    participant Cust as Pelanggan (/akun)

    Admin->>Route: Ubah dropdown status (new -> confirmed) & Simpan
    Route->>MW: Verifikasi guard admin
    MW->>Ctrl: updateConcierge(Request)
    Ctrl->>DB: Update status concierge
    Ctrl-->>Admin: Redirect back dengan flash notifikasi
    Note over Cust: Pelanggan membuka /akun dan langsung melihat status terbaru
```

- **Titik Putus / Risiko:** Sinkronisasi status antara admin dan pelanggan terverifikasi bekerja dengan baik melalui relasi `user_id` atau pencocokan `email`.
- **Status:** **Terintegrasi Penuh (Verified).**
