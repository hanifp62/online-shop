# Spesifikasi Remediasi Audit Dewan ZAFIRA

Tanggal: 10 Oktober 2026. Status: **draf menunggu review pengguna**. Sumber: [laporan dewan](../../05-quality/COUNCIL_AUDIT_REPORT.md).

## Tujuan

Menutup penghalang rilis yang terbukti dari audit dengan perubahan sekecil mungkin. Identitas visual storefront, perilaku yang sudah lulus test, dan arsitektur Laravel modular tetap dipertahankan.

## Di luar cakupan

- Order/OrderItem, pembayaran, ledger SHA-256, sistem manufaktur, atau dashboard telemetri baru.
- Upgrade major dependency, penggantian driver infrastruktur, atau redesign visual.
- Penghapusan dump, rotasi kredensial, dan rewrite histori Git tanpa persetujuan eksplisit terpisah.
- Klaim performa tanpa benchmark representatif.

## Keputusan yang diperlukan sebelum implementasi

| Keputusan | Opsi aman default |
|---|---|
| Dump SQL terlacak | Inventaris dan rekomendasi saja sampai pengguna memilih hapus dari HEAD dan/atau rewrite histori. |
| Kredensial admin | Hentikan password default pada provisioning baru; rotasi akun nyata dilakukan manual oleh pemilik. |
| Riwayat guest lama | Tidak ditampilkan hanya karena email sama. Mekanisme klaim aman dirancang terpisah bila dibutuhkan. |
| Link prototype | **Disetujui:** sembunyikan seluruh link dan pemicu navigasi prototype pada lingkungan produksi. Aset gambar/CSS yang dipakai storefront tetap dipertahankan. |
| Klaim telemetri/provenance | **Disetujui:** hapus seluruh klaim palsu, termasuk telemetri, ledger, sertifikat, ukuran tubuh, dan advisor fiktif. Jangan menggantinya dengan label simulasi pada UI aplikasi nyata. |
| Mata uang | **Disetujui:** hapus selector USD/EUR dan jalur konversi terkait; gunakan IDR secara konsisten. |

## Desain perubahan

### Slice 1 — Batas data dan kepercayaan

- Riwayat profil hanya membaca request milik `user_id` pengguna.
- `order_items` divalidasi sebagai array terbatas dengan identitas produk yang ada dan kuantitas valid.
- Data produk dan harga diturunkan dari database, bukan dipercaya dari browser.
- Newsletter memberikan respons publik yang sama untuk email baru dan terdaftar.
- Seeder tidak lagi membuat admin dengan password default lemah.

### Slice 2 — Cache dan throttle

- Ganti `Cache::flush()` dengan invalidasi key katalog yang diketahui.
- Test menunjukkan counter limiter tidak hilang setelah perubahan kategori.
- Service worker tidak menyajikan HTML dinamis dari cache-first; asset build tetap boleh dicache.

### Slice 3 — Personalisasi dan kejujuran UI

- Ukuran dan monogram ikut masuk ke item tas, localStorage, ringkasan, dan payload concierge.
- Varian dengan ukuran atau monogram berbeda menjadi item terpisah.
- Validasi server mengikuti kontrak antarmuka: monogram huruf A–Z, maksimal tiga karakter.
- Hapus klaim telemetri, ledger, sertifikat, 24k gold, angka kriya, profil ukuran tubuh/advisor fiktif, dan klaim backup audit yang tidak didukung backend/data nyata.
- Hapus selector USD/EUR; harga tetap IDR.
- Ganti status akun dengan status concierge yang benar-benar disimpan; jangan petakan status ke tahapan produksi fiktif.
- Tampilkan ukuran hanya bila relevan untuk tipe produk; ukuran/monogram harus ikut payload cart.
- Koreksi aset kebaya yang salah; hapus shortcut admin berlabel MSI.
- Sembunyikan seluruh link ke `/prototype/` di produksi; pertahankan aset prototype yang dipakai storefront untuk gambar.
- Demo rendezvous harus mengirim ke backend atau menyatakan simulasi dengan jujur; tidak boleh memberi toast seolah permintaan tersimpan.
- Hapus tautan kebijakan privasi yang tidak punya halaman, atau sediakan halaman kebijakan.
- Tinjau parameter prototype `?p=` agar cocok dengan identitas produk.
- Tandai dua dokumen riset lama sebagai analisis historis yang tidak tersitasi; semua klaim UX hanya diterima setelah diberi sumber atau diverifikasi lewat browser/ukur.

### Slice 4 — Aksesibilitas yang terfokus

- Search, filter, ukuran, monogram, bantuan ukuran, sertifikat, dan selector bahasa/tema memiliki nama dan status yang dapat dibaca.
- Drawer/modal yang dibuka memenuhi pola fokus: fokus masuk, Tab tertahan, Escape menutup, fokus kembali ke pemicu.
- `alert()` diganti dengan tombol native dan pola bantuan/dialog yang sudah ada.
- Tidak ada perubahan token warna atau font kecuali diperlukan untuk kontras terukur.

### Slice 5 — Bukti operasi dan dokumentasi

- Pint diperbaiki pada file migration yang gagal.
- Jalur procedure kompatibilitas dibuat eksplisit sesuai keputusan apakah procedure wajib.
- Smoke staging/deployment mencatat driver, proxy, worker, health check, backup/restore, dan rollback.
- Matriks traceability dan klaim kualitas lama direkonsiliasi dengan bukti nyata.

## Strategi verifikasi

| Lapisan | Bukti wajib |
|---|---|
| Unit/feature | Test gagal terlebih dahulu, lalu lulus, untuk ownership, validasi concierge, newsletter, seeder, cache invalidation, dan personalisasi. |
| Regression | Seluruh 49 test baseline tetap lulus. |
| Static/build | `vendor/bin/pint --test` dan `npm run build` lulus. |
| Browser | Keyboard, fokus, Escape, mobile, zoom, logout/cache, dan alur tas/concierge diverifikasi di browser nyata. |
| Deployment | Smoke terisolasi menggunakan driver cache/session/queue/database yang mewakili target, tanpa data pengguna nyata. |

## Kriteria selesai

- Semua item P0/P1 yang disetujui ditutup atau dicatat sebagai risiko yang diterima pemilik.
- Tidak ada rahasia yang ditampilkan dalam log, chat, test fixture, atau dokumentasi.
- Tidak ada operasi database destruktif tanpa persetujuan eksplisit.
- Setiap klaim selesai menunjuk ke test, output perintah, atau catatan browser.

## Review mandiri

- Placeholder: tidak ada.
- Konsistensi: cakupan mengikuti backlog B01–B12 dan mempertahankan Order sebagai post-MVP.
- Ambiguitas utama: tindakan atas dump/kredensial nyata, riwayat guest lama, prototype, dan klaim visual memerlukan keputusan pengguna.
- Ukuran: lima slice independen sehingga dapat dieksekusi dan diverifikasi bertahap.
