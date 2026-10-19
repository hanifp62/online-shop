# Completion backend — 2026-10-11

## Perubahan milik lane ini

- `app/Http/Controllers/AdminAuthController.php`: redirect login admin langsung ke dashboard. Sebelumnya intended URL dari `/akun` mengarahkan admin ke login customer.
- `tests/Feature/RescueBackendAuditTest.php`: tambah `test_admin_login_ignores_customer_intended_route`. File sudah ada sebelum lane ini; bukan seluruh file hasil lane ini.
- Berkas baru hanya pada direktori evidence ini. Perubahan controller lain bukan milik lane ini.

## Bukti

- RED nyata: test baru gagal, expected `http://localhost/admin`, actual `http://localhost/akun` sebelum perbaikan. Output RED tersedia pada transkrip sesi, tidak tersimpan sebagai log terpisah.
- GREEN: `php-tests.log`, `php-tests.exitcode=0`: 45 tests, 323 assertions. Filter: AdminManagementTest, AuthTest, RescueBackendAuditTest, BackendPermissionAuditTest.
- Pint targeted controller dan test: passed pada transkrip.
- `e2e.log`, `e2e.exitcode=0`, `report.json`: 46 pemeriksaan PASS dan satu BLOCKED. Skrip menghitung BLOCKED sebagai `ok:true` sehingga total tertulis 47; itu bukan 47 PASS.
- Browser hanya `http://127.0.0.1:8015`. Bootstrap diperiksa sebelum eksekusi; SQLite, session, compiled views, logs, storage semuanya terisolasi di zafira-qa.

## Cakupan PASS

- Login customer/admin, logout, perubahan cookie sesi, protected page setelah logout. Bukti cookie berubah tidak membuktikan ID sesi plaintext berubah karena enkripsi cookie; controller memang memanggil regenerate/invalidate.
- Update profil melalui UI browser.
- Newsletter create/duplicate dan concierge create melalui HTTP Playwright dengan token CSRF browser, bukan klik form publik.
- Admin concierge UI: new → contacted → confirmed → completed; cabang new → cancelled; terminal tanpa kontrol; direct submit backward/bogus tidak mengubah status.
- Admin category create/update, product create/delete, proteksi kategori berisi produk. Konfirmasi destructive produk/kategori/subscriber dibuka dan dibatalkan melalui klik browser. Penghapusan yang disetujui dieksekusi HTTP, bukan klik Accept.
- Sitemap XML/header/public URL/private URL exclusion.
- Screenshot `concierge-new.png` dan `concierge-completed.png` dibuka dan dibaca visual: new punya select/Simpan; completed hanya badge terminal. Screenshot lain tersimpan tetapi belum dibaca visual.

## BLOCKED / cakupan belum lengkap

1. Browser throttle diblokir desain harness: router menetapkan `cache.default=array`, state limiter hilang setiap request. Empat request memberi `[201,201,201,201]`. Test Laravel in-process membuktikan limiter login/register/concierge, tetapi bukan bukti lintas-request deployment.
2. Belum lengkap: public form click newsletter/concierge, product update UI, semua edge transisi, dual-guard session rotation/replay, newsletter browser throttle, screenshot visual selain dua yang disebutkan.
3. Email verification tidak aktif: User tidak implements MustVerifyEmail, rute tanpa middleware verified, rute verifikasi tidak ada. Kebutuhan bisnis verifikasi belum dicocokkan dengan dokumen requirement. Jangan klaim implementasi verifikasi email.

## Review

Perbaikan hanya mengganti tujuan redirect login admin; tidak mengubah autentikasi/CSRF. Test regresi membuktikan pemisahan tujuan customer/admin. Konsekuensi: admin selalu masuk dashboard, tidak memulihkan intended URL lain. Dashboard merupakan satu-satunya GET admin terproteksi pada routes/web.php yang diperiksa.

Tidak mengubah frontend JS/CSS, .env, atau database produksi. Fixture browser tertinggal hanya di SQLite QA. Tidak commit/deploy.
