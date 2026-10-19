# Verifikasi Manual Native Artisan — 2026-10-14

**Hasil:** PASS untuk setup SQLite terisolasi, migrasi biasa, server Artisan native, fixture sintetis, alur customer/admin, PDF, dan restart server.

- Checkout: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-20261014-verify`
- Source fingerprint: `41c64bb84862267d8e23c08686030c2d4e129a86a83bcd9b737e2c757600c8b9` (240 file)
- Fingerprint dihitung dari manifest SHA-256 file checkout. `source-drift.txt` kosong saat setup.
- Setup menjalankan 24 migrasi biasa pada file SQLite baru. Tidak menjalankan `migrate:fresh`, `db:wipe`, atau operasi database destruktif.
- Fixture menyisipkan tepat satu produk dan kategori sintetis serta akun customer/admin sintetis; kredensial acak tersimpan private di checkout, tidak dicatat dalam evidence.
- Native server benar-benar berjalan melalui `php artisan serve --host=127.0.0.1 --port=8017` dalam terminal zsh baru. Restart berhasil dengan perintah yang sama.
- Uji HTTP: `/` 200; `/koleksi` 200; PDP sintetis 200; login customer/session 200; cart 200; checkout 201; order sebelumnya tetap terlihat setelah checkout; cancel 200; admin dashboard/order 200; PDF 200 dengan `application/pdf` dan magic `%PDF-`.
- Database setelah uji: 2 order sintetis berstatus cancelled; stok fixture tetap 20. Tidak ada data operasional dalam database itu.
- Port 8015/8016 tidak disentuh. Repo `.env`, `.env.manual`, database/runtime repo, dan source produk tidak diedit oleh prosedur ini.

Bukti source reproducibility: `source-manifest.sha256`, `source-fingerprint.txt`, `source-file-count.txt`. Setup script, preflight, fixture, acceptance runner, dan native startup command turut disalin ke folder ini.

## Batasan

- Verifikasi browser manual interaktif belum dilakukan. HTTP smoke runner membuktikan respons dan transaksi, bukan penampilan visual.
- Skenario payment eksternal, email delivery, concurrency, dan uji kegagalan dependency tidak tercakup.
- Setup berikutnya harus menggunakan direktori baru, bukan menimpa checkout/data sebelumnya.
