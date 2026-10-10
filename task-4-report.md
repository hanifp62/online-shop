# Task 4 — Aksesibilitas Dasar Storefront & PDP

**Status:** DONE_WITH_CONCERNS  
**Tanggal:** 2026-10-10  
**Branch:** `fix/audit-benchmark-remediation`

## Perubahan

- Menyatukan siklus overlay storefront melalui `openElement`, `closeElement`, `syncOverlay`, dan `closeAll`.
- Menambahkan fokus masuk, focus trap Tab, Escape close untuk semua drawer/modal, dan pengembalian fokus ke pemicu.
- Mempertahankan pemicu luar saat perpindahan Quick View ke Concierge agar fokus tidak kembali ke elemen dialog yang sudah ditutup.
- Menghapus handler Escape shortcut duplikat; shortcut kini memakai handler overlay global.
- Menambahkan nama aksesibel dan status pada search katalog, filter kategori, hasil jumlah produk, search PDP, dan tombol bantuan shortcut.
- Menambahkan dialog semantik Panduan Ukuran pada PDP sebagai pengganti pola bantuan non-dialog.
- Menambahkan `aria-label`, `aria-labelledby`, `aria-modal`, dan `aria-hidden` pada drawer/modal terkait.
- Tidak ditemukan `alert()` native pada JS storefront atau Blade PDP.

## File

- `resources/js/app.js`
- `resources/views/welcome.blade.php`
- `resources/views/products/show.blade.php`
- `resources/views/partials/product-footer.blade.php`
- `tests/task4.test.mjs`
- `tests/task4-focus.test.mjs`

## Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `node --test tests/*.mjs` | 16 passed, 0 failed |
| `npm run build` | Passed, Vite 8.2.2 |
| `php artisan test` | 62 passed, 243 assertions |
| `vendor/bin/pint --test resources/views/welcome.blade.php resources/views/products/show.blade.php resources/views/partials/product-footer.blade.php` | Passed |

## Risiko / batasan

- Browser nyata belum dijalankan dalam subtask ini; verifikasi keyboard memakai test Node berbasis DOM minimal.
- Fokus trap bergantung pada elemen interaktif yang terlihat di layer aktif.
- Kontras warna tidak diubah karena tidak ada pengukuran browser baru yang membuktikan kebutuhan perubahan token.
- Perubahan sebelumnya di working tree sengaja tidak disentuh dan tidak dikomit.
