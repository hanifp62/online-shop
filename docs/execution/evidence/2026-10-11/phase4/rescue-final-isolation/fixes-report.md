# Verifikasi perbaikan shortcuts dan segel lilin

## Lingkup

Perubahan produk pada sesi ini hanya:
- `resources/js/app.js`: tambahkan/hapus kelas `open` pada anak `.modal-window` ketika backdrop shortcuts dibuka/ditutup. Lifecycle aria, inert, fokus, Escape, dan backdrop tetap memakai fungsi bersama.
- `resources/views/welcome.blade.php`: ubah segel lilin menjadi elemen `button type="button"`, pertahankan nama aksesibel, kelas CSS, dan handler klik.

Diff working tree mengandung pekerjaan sebelumnya/agen lain. Laporan ini tidak mengklaim kepemilikan seluruh diff tersebut.

## Bukti red → green

| Pemeriksaan | Red | Green |
|---|---|---|
| Overlay desktop 1440×900 dan mobile 390×844 | `../rescue-red-valid/results.json`: 158/164, 6 gagal, exit 1 | `../rescue-green/results.json`: 174/174, exit 0 |
| Shortcuts | 5/8 per viewport | 13/13 per viewport; 2 target fokus |
| Wax click, Enter, Space | `wax-red2.log`: 0/3 skenario, exit 1 | `wax-green3.log`: 3/3 skenario, exit 0 |
| Build | — | `build-green.log`, sukses |

Overlay green: 0 console/page errors, 0 failed requests. Terdapat 14 screenshot dengan metadata route, viewport, dan auth dalam results.json. Screenshot shortcuts desktop dan mobile diperiksa langsung: konten terlihat, panel tidak terpotong, kedua tombol tutup terlihat.

Wax green memverifikasi native BUTTON, type=button, nama aksesibel, toast muncul pada ketiga aksi, tidak ada submit, URL tetap, scroll delta 0, dimensi layout 56×56. Warna tetap rgb(136, 19, 55), radius 50%.

Jumlah assertion overlay meningkat karena modal yang kini terlihat memungkinkan cabang pengujian fokus dan tombol tutup berjalan.

## Kendala harness dan runtime

- `../rescue-red/` bukan baseline valid: router temp menolak symlink `public/prototype`, menyebabkan stylesheet tidak termuat. Bukti dipertahankan. Router runtime diperbaiki agar static passthrough mendukung symlink publik; tidak ada konfigurasi produk diubah.
- `wax-green.log` gagal pada scroll klik akibat auto-scroll Playwright. `wax-green2.log` gagal karena pengukuran bounding box terkena hover scale. Harness menunggu scroll stabil dan memakai offsetWidth/offsetHeight; seluruh bukti tetap disimpan.
- `audit-nav.mjs` mengganti jeda 700ms dengan kondisi status/animasi saat membuka dan kondisi aria/inert/fokus saat Escape. Syntax check sukses; audit navigasi lengkap belum dijalankan ulang karena mencakup autentikasi di luar verifikasi sempit ini.

## Batasan dan sisa isu

Tidak ada klaim PASS seluruh aplikasi. Pengujian guest, bukan screen reader atau hardware mobile. Backdrop yang tidak menyediakan hit target tetap tidak diuji. Tombol X shortcuts terlihat kecil; ukuran target sentuh 44×44 belum diaudit/diperbaiki dalam scope ini. Screenshot wax khusus belum dibuat; preservasi visualnya diperiksa lewat properti DOM/CSS.

Server PID 76656 tetap hidup pada port 8015 dengan router temp terisolasi. Tidak ada perubahan .env, akses port 8000/8001, commit, push, atau deploy dalam pekerjaan perbaikan ini.
