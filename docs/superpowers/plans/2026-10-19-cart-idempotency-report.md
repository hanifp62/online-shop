# Laporan: Keranjang — tambah/ubah/hapus jumlah yang idempoten

**Tanggal:** 2026-10-19
**Task:** `baseline-codebase/tambah-ubah-jumlah/keranjang-tambah-ubah-hapus-jumlah-yang-idempoten`
**Status perubahan:** belum di-commit (`routes/web.php` modified, `tests/Feature/CartIdempotencyTest.php` untracked)

## 1. Requirement yang relevan (PRD)

| ID | Ringkasan | Cakupan task ini |
| --- | --- | --- |
| R-28 | Feedback langsung saat ubah cart (`prd.md` baris 199) | Ya — kontrak endpoint cart dijaga tetap |
| R-05 / R-06 | Harga dari server, operasi idempoten | Ya |
| R-11 | Tabel `carts` persisten | Di luar cakupan (cart tetap berbasis session) |

## 2. Pemetaan requirement → test (`tests/Feature/CartIdempotencyTest.php`)

| Perilaku | Requirement | Test |
| --- | --- | --- |
| Set/hapus/clear berulang menghasilkan state identik | R-06 | test idempotensi set/delete/clear |
| Kuantitas invalid tidak mengubah cart | R-06, R-28 | test validasi kuantitas |
| Harga dari DB, bukan dari klien | R-05 | test harga server-side |
| Produk stok kurang/nonaktif ditolak | R-05 | test stok |
| Item produk lain di cart tetap utuh | R-06 | tercakup di test set/delete |
| Semua route cart (termasuk GET) memakai session lock yang sama dengan `checkout.store` | R-06 | `every cart route shares checkout session lock duration` |

## 3. Audit pemanggil

- `resources/js/app.js` (baris 363, 411-412, 600-605, 865) — fetch ke `/cart`, `/cart/items`, `DELETE /cart/items/{id}`, `DELETE /cart`.
- `resources/views/shop/index.blade.php` (baris 68, 138), `resources/views/catalog/index.blade.php` — form POST ke `/cart/items`.
- `OrderController::store` — menghapus cart setelah checkout.
- `CheckoutService::cart()` / `priced()` / `validateCart()` (`MAX_QUANTITY=99`).

Kesimpulan audit: `CartController::store` sudah bersemantik *set* (bukan increment), sudah memvalidasi input, menolak produk nonaktif/stok kurang, dan menghitung harga dari DB. Satu-satunya celah: route cart tidak memakai session blocking, sehingga request paralel dapat saling menimpa data session (lost update).

## 4. Perubahan

`routes/web.php` baris 99-102: tambah `->block(10, 10)` pada `cart.show`, `cart.items.store`, `cart.items.destroy`, `cart.clear` — sama dengan `checkout.store`. Tidak ada perubahan controller, API, maupun UI.

## 5. Bukti

Perintah: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test <target> > /tmp/o.txt 2>&1; echo EXIT=$?`

| Tahap | Target | Hasil |
| --- | --- | --- |
| Baseline (full suite terakhir di `/tmp/totp-state.md`) | semua | 337 passed, 4 skipped, 2539 assertions |
| RED | `tests/Feature/CartIdempotencyTest.php` | EXIT=1 — 1 failed, 3 passed (39 assertions). Gagal: `every cart route shares checkout session lock duration` → `cart.show Failed asserting that null is identical to 10.` |
| GREEN | `tests/Feature/CartIdempotencyTest.php` | EXIT=0 — 4 passed (46 assertions) |
| Regresi | `UnifiedPurchaseCartTest`, `AmadisOrderTest`, `AmadisShopTest` | EXIT=0 — 31 passed (225 assertions) |
| Full suite | semua | EXIT=0 — 341 passed, 4 skipped, 2585 assertions, 28.47s |

Tiga test yang lulus saat RED menandakan perilaku itu memang sudah benar; tidak dipaksa gagal.

## 6. Keterbatasan

- Test lock hanya memeriksa metadata route (`locksFor()` / `waitsFor()`). **Ini bukan bukti keamanan konkurensi runtime**; belum ada test request paralel nyata.
- Test memakai `SESSION_DRIVER=array` dan `CACHE_STORE=array` (`phpunit.xml`); produksi default `database` session driver — perilaku lock di sana belum diverifikasi.
- `block()` butuh cache store yang mendukung atomic lock; jika store produksi tidak mendukung, lock tidak efektif.
- Request ke-2 yang menunggu >10 detik akan gagal (`LockTimeoutException`) — trade-off yang disengaja, sama dengan checkout.
- Tidak ada verifikasi browser; tidak ada tool coverage yang tersedia.
- Cart masih berbasis session (R-11 di luar cakupan).

## 7. Addendum audit keamanan (2026-10-19)

Perubahan pengguna: `routes/web.php` menambahkan limiter bernama `cart` sebesar 60 request/menit per IP dan `throttle:cart` pada keempat endpoint cart. `AuthController::logout` menghapus key `cart` ketika sesi admin harus dipertahankan. Cabang logout tanpa admin memakai `invalidate()`, yang juga menghapus seluruh isi sesi.

| Verifikasi | Hasil |
| --- | --- |
| RED (hanya tiga tambahan pengguna dilepas sementara; byte file dipulihkan persis di blok `finally`) | `RED_EXIT=1`; 2 failed, 4 passed (112 assertions). Batas cart: `Failed asserting that 200 is identical to 429.` Logout: `Session has unexpected key [cart].` Log `/tmp/cart-red.txt`. |
| GREEN (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/CartIdempotencyTest.php > /tmp/cart-green.txt 2>&1; echo EXIT=$?`) | `EXIT=0`; 6 passed (124 assertions). |
| Full (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test > /tmp/o.txt 2>&1; echo EXIT=$?`) | `EXIT=0`; 343 passed, 4 skipped (2663 assertions), 28.05s. |

Test baru memeriksa 15 putaran GET/POST/DELETE item/DELETE cart (total 60 request) pada IP yang sama, lalu semua metode mendapat 429; IP lain mendapat 200 dan IP semula pulih setelah 61 detik. Test logout mengisi cart dengan guard web dan admin aktif, kemudian memastikan cart hilang, guard web keluar, dan guard admin tetap masuk.

**Review lokal:** Keempat route memakai nama limiter sama sehingga berbagi bucket IP. `StartSession` memperoleh session lock sebelum middleware route `throttle:cart` berjalan; 429 pun masih menunggu lock. `CheckoutService::cartKey()` mengembalikan `cart`, cocok dengan key yang dihapus saat logout. IP dihitung dari `Request::ip()`; konfigurasi trusted proxy dipasang dari `config('trustedproxy.proxies', [])` pada `bootstrap/app.php`. Konfigurasi deployment proxy belum diverifikasi. Dua panggilan subagent (`code-reviewer` dan `security-reviewer`) gagal dengan `Subagent depth limit reached (1)`; **bukan** review independen yang lulus.

**Batas tambahan:** Belum ada uji dua klien dengan sesi persisten terpisah atau request paralel nyata; test PHPUnit memakai session driver `array`. 60 request/menit per IP berbagi kuota untuk pengguna di balik NAT, termasuk GET; beban UI dan kelayakan batas produksi belum diuji di browser. Tidak ada klaim browser lulus, throughput produksi, atau keamanan konkurensi runtime.
