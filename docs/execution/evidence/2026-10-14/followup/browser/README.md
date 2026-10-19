# Persiapan bukti browser dan runtime — 2026-10-14

Status: **DIEKSEKUSI** — lihat [`RESULT.md`](RESULT.md) (browser 13 PASS, 1 FAIL `catalog-toast-count`). Teks di bawah adalah rencana awal.

## Batas kepemilikan

Agen runtime hanya mengubah bukti baru dalam `followup/` dan `docs/MANUAL_TESTING.md`. Source produk, skrip operasional, bukti lama, dan paket lama dibaca tanpa perubahan. Tidak mengakses `.env` operasional atau MySQL; port 8015/8016/8017 tidak dipakai.

## Gap dan acceptance

- **NFR-FOLLOWUP-RT-01**: checkout baru berasal dari working tree stabil; Vite dibangun dari salinan; fingerprint + drift dibandingkan kembali sesudah pengujian. Bukti source lama tidak dipromosikan menjadi bukti source baru.
- **NFR-FOLLOWUP-RT-02**: fixture dijalankan dua kali; hitungan tabel dicatat pada dua berkas berbeda sebelum acceptance; keduanya harus identik.
- **FR-FOLLOWUP-RT-03**: acceptance HTTP pertama, snapshot order/stock, restart PID milik checkout sendiri, snapshot sesudah restart, acceptance kedua. Persistence dibuktikan oleh snapshot identik sebelum request kedua, bukan sekadar keberhasilan acceptance kedua.
- **FR-FOLLOWUP-BR-01**: customer login, home add, toast menyebut produk dan aksi, badge terlihat dan bernilai 1, JSON cart quantity 1; rapid +3, remove/undo, katalog, checkout reference.
- **FR-FOLLOWUP-BR-02**: admin login melalui browser, form laporan dashboard, unduhan PDF HTTP 200 + application/pdf + `%PDF-`.
- **NFR-FOLLOWUP-BR-03**: page errors, request gagal, respons HTTP >=400, dan request eksternal yang diblokir dicatat tanpa body/header/credential. Kegagalan assertion menghasilkan exit nonzero; screenshot hanya setelah kolom password dikosongkan.

## Diagnosis bukti historis

`remediation/final/browser.cjs` menunggu badge `textContent === '1'` sebelum membaca toast. `resources/js/app.js` memperbarui badge dalam `cartRequest`, kemudian memanggil `showToast` dari `.then()` milik `addToCart`. Kondisi badge saja bukan sinyal bahwa toast telah selesai diperbarui. CSS `.action-count` juga menyembunyikan badge sampai `.has-items` aktif; `innerText()` dapat kosong saat elemen belum terlihat.

Perbaikan assertion yang dibenarkan: tunggu **toast yang benar dan badge terlihat bernilai benar**, kemudian tetap periksa JSON server. Tidak mengubah jumlah yang diharapkan, tidak menerima toast kosong, tidak mengabaikan kegagalan.

Namun ini **belum membuktikan akar kegagalan lama**. `browser-results.json` lama menyebut regexp `/ditambahkan/`, sementara `browser.cjs` tersimpan memakai `assert.ok(...)`; provenance skrip dan hasil tidak identik. `home-diagnostic.json` lama menunjukkan toast benar dan count 1, tetapi bukan bukti runtime source kini. Jika suite baru gagal dengan semua kondisi tersebut, laporkan kebutuhan fix produk; jangan melonggarkan assertion.

Assertion admin lama mencari tautan PDF pada `/admin/orders`; followup lama berhasil melalui tombol `Unduh PDF` dashboard. Suite baru mengikuti journey UI dashboard yang sebenarnya.

## Guard yang telah dibaca sebelum bootstrap

- `scripts/manual/setup.sh`: root baru saja; salin source tanpa `.env`, database, storage, dependencies lama atau build lama; materialisasi symlink publik; vendor terpisah; Vite dari salinan; SQLite baru; migrate biasa setelah preflight.
- `scripts/manual/preflight.py`: menolak env warisan, sink luar root, symlink nested, identitas tidak cocok, cache executable berubah; port hanya 8017/8018. Untuk pekerjaan ini hanya 8018 diizinkan.
- `scripts/manual/fixture.sh`: preflight sebelum Artisan, kredensial privat, sukses memerlukan marker persis.
- `scripts/manual/start-server.sh`: preflight sebelum native `php artisan serve`.
- `scripts/manual/acceptance.py`: identitas HTTP sebelum credential, redirect satu origin, HTTP customer/admin/PDF; dua order dibuat setiap run.

## Urutan eksekusi setelah source stabil

1. Pastikan 8018 kosong; jangan hentikan listener yang bukan milik agen.
2. Pilih root baru `.../T/opencode/zafira-manual-followup-<timestamp>-8018`; `MANUAL_PORT=8018`.
3. Jalankan `bash scripts/manual/setup.sh`; catat exit dan fingerprint/drift.
4. Dari checkout, jalankan `bash scripts/manual/fixture.sh` dua kali; snapshot SQLite read-only setelah masing-masing run.
5. Jalankan native server checkout melalui `start-server.sh` dengan background timeout 0.
6. Jalankan acceptance HTTP pertama; catat snapshot persistence.
7. Hentikan hanya PID server milik checkout; mulai lagi; bandingkan snapshot sebelum acceptance kedua.
8. Jalankan acceptance kedua, kemudian `MANUAL_ROOT=... MANUAL_PORT=8018 node .../followup/browser/browser.cjs`.
9. Catat source drift setelah run. Source berubah berarti perlu checkout baru, bukan menyalin perubahan ke runtime yang sudah diuji.

Belum diuji: seluruh runtime pada source terbaru, payment eksternal, email, MySQL, dan concurrency. Tidak ada klaim PASS sebelum eksekusi.
