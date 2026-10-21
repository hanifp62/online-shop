# Teks UI Membimbing, Konsisten, dan Terlokalisasi (R-54)

**Tanggal:** 2026-10-21  
**Task:** `baseline-codebase/teks-yang-membimbing/teks-ui-membimbing-konsisten-terlokalisasi` (`12220d08-1e3a-45d9-b741-ce478a3f448a`)  
**Keputusan:** `COUNCIL_DECISIONS.md` #053  
**Status:** Perbaikan lanjutan teruji lokal; memerlukan review independen BARU. Review sebelumnya tidak mencakup perubahan ini. Bukti penerimaan lengkap masih tertunda.

## 1. Kriteria (AC)
- **AC-R54-01:** Gambar produk punya `alt` bermakna; tidak kosong dan bukan nama berkas, meski `product_alt` kosong.
- **AC-R54-02:** Pesan galat pemulihan menyebut penyebab dan satu langkah perbaikan konkret di 5 locale.
- **AC-R54-03:** Tidak ada label tombol/tautan samar ("OK", "Kirim", "Submit", "Click here", "klik di sini") di katalog `lang/*`.
- **AC-R54-04:** Katalog 5 locale tetap identik kuncinya dan placeholder-nya.

## 2. Temuan audit
- `product_alt` dirender mentah di beranda (payload `StorefrontController`), katalog, dan PDP. Produk tanpa deskripsi alt menghasilkan `alt=""` pada gambar yang bermakna (gagal WCAG 1.1.1).
- Tiga pesan pemulihan hanya menyatakan kegagalan, tanpa langkah berikutnya: `auth.reset_invalid`, `customer_totp.invalid_recovery`, `customer_totp.invalid`.
- Pencarian label samar di `lang/*/*.php` dan `lang/*.json`: nol temuan.
- Placeholder `img alt=""` pada dialog katalog dan quick-view (`welcome.blade.php`, `partials/product-footer.blade.php`) merupakan placeholder runtime; pengisian alt sekarang memakai kanal alt kartu. Belum diverifikasi dalam browser.
- Label formulir dan `aria-describedby` sudah dikunci `StorefrontA11yMarkupTest`; tidak diubah.

## 3. Perubahan
| Berkas | Perubahan | Alasan |
|---|---|---|
| `app/Http/Controllers/StorefrontController.php` | `'alt' => $product->imageAlt()` | Fallback nama produk di beranda |
| `resources/views/catalog/index.blade.php` | `alt` dan `data-alt` kartu memakai fallback yang sama | Kartu + dialog katalog |
| `resources/views/products/show.blade.php` | Gambar utama dan galeri memakai fallback | PDP |
| `lang/{id,en,fr,it,zh}/customer_messages.php` | Tiga kunci di atas ditulis ulang: penyebab + langkah | Nielsen #9, WCAG 3.3.3 |
| `tests/Feature/GuidingMicrocopyTest.php` | Tes regresi baru | Mengunci AC-R54-01/02 |

Fallback memakai metode presentasi `Product::imageAlt()`, bukan accessor: pilih alt nonblank, lalu judul nonblank, lalu label produk lokal `customer_messages.storefront.product_image`. Nilai nonblank dipertahankan; metode tidak menulis data. Home dan kartu terkait PDP meneruskan `data-alt` ke quickview melalui `readProduct().alt`. Tidak ada deskripsi visual yang dikarang.

Teks baru (Indonesia, sebagai acuan; locale lain setara):
- `auth.reset_invalid`: "Tautan tidak berlaku atau sudah kedaluwarsa. Minta tautan pemulihan baru dari halaman lupa kata sandi."
- `customer_totp.invalid_recovery`: "Kode cadangan tidak valid atau sudah dipakai. Gunakan kode cadangan lain yang belum pernah dipakai."
- `customer_totp.invalid`: "Kode tidak cocok. Masukkan kode terbaru dari aplikasi autentikator Anda."

Teks lama tidak dikutip: `lang/` belum dilacak git sehingga tidak ada versi dasar yang dapat dirujuk.

## 4. Bukti pengujian aktual

| Klaim | Status | Bukti dan batas |
|---|---|---|
| Alt null, judul normal | DIUJI | Render home/katalog/PDP × 5 locale mempertahankan `Robe Lumière`. |
| Alt dan judul kosong atau spasi/tab | DIUJI | Dua produk `empty` dan `whitespace`, home/katalog/kedua PDP × 5 locale; gambar utama, terkait, dan payload data-alt memakai label lokal. |
| Alt eksplisit | DIUJI | `Robe brodée bleue` tetap utuh pada tiga permukaan. |
| Kanal alt quickview | STATIS | `tests/image-alt-fallback.test.mjs` mengunci data-alt → readProduct.alt → atribut alt; bukan eksekusi browser. |
| Gambar dekoratif | INSPEKSI TERBATAS | Tiga alt kosong ditemukan pada placeholder bersrc kosong, bukan gambar dekoratif. Tes tambahan memastikan gambar bersrc nyata tidak blank pada fixture tiga halaman; bukan aturan universal bagi gambar dekoratif. |
| Label formulir dan nama kontrol | DIUJI STRUKTURAL | Suite penuh menjalankan `StorefrontA11yMarkupTest`; asosiasi label/nama diperiksa. Bukan bukti pemahaman label atau accessible-name computation browser. |
| Tombol/tautan konkret | PARSIAL | Daftar tolak lima label samar pada halaman terender; belum audit positif semua kata kerja dan konteks tautan. |
| Pesan pemulihan | PARSIAL | Substring langkah berikutnya di lima locale; belum bukti pemahaman pengguna. |

## 5. Gate dan Continuous Evaluation Record

Eksekusi ulang lokal mulai **2026-10-21 14:26** (waktu mesin):

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=GuidingMicrocopyTest` | 5 passed, 1170 assertions; EXIT=0 pada run fokus sebelumnya, lalu diulang sebelum suite penuh |
| `php artisan test` | 959 passed, 4 skipped, 27212 assertions; EXIT=0 |
| `node --test tests/*.test.mjs` | 312 passed; EXIT=0 |
| `npm run build` | EXIT=0 |
| `vendor/bin/pint --dirty --test` | EXIT=0 |
| `git diff --check` | EXIT=0 |

- **Requirement:** R-54, AC-R54-01–04; cakupan bukti mengikuti §4, bukan klaim penerimaan keseluruhan.
- **Baseline:** Test rendering kosong/spasi gagal setelah label lokal tersedia: `php artisan test --filter=GuidingMicrocopyTest`, EXIT=1, 1 failed/3 passed (1013 assertions). Log `$TMPDIR/r54-red2.log`.
- **Perubahan terkontrol:** Metode presentasi bersama, pemanggil controller/Blade, kanal alt quickview, satu kunci tambahan pada lima locale, tes fokus, dokumen ini.
- **Perbandingan:** Kasus kosong/spasi beralih RED→GREEN; alt eksplisit dan judul normal tetap lolos. Tes Node statis ditambahkan setelah implementasi; kegagalan awal Node berasal dari regex tes yang salah, bukan RED perilaku produk.
- **Basis:** `7fff6fe5b40230a852c7c9b79590f14b74b880dd`; working tree sudah kotor. Statistik diff keseluruhan tidak dapat dianggap sebagai ukuran pekerjaan R54.
- **Log lokal:** `$TMPDIR/r54-{red2,green,php,node,build,pint,diff}.log`; artefak sementara, tidak dilacak git.
- **Skill:** accessibility dan tdd-workflow telah dimuat pada sesi sebelum checkpoint.
- **Crosscheck:** Pemanggil home/katalog/PDP/quickview ditelusuri. Permintaan code-reviewer ditolak harness karena batas kedalaman subagent; tidak ada review independen baru.
- **Coverage:** Persentase coverage tidak diukur; jumlah test bukan bukti ambang 80%.
- **Keputusan:** Perbaikan lokal terverifikasi, penerimaan penuh masih tertunda. Tidak ada commit, reset task, atau sync NgodingPakeAI.

## 6. Batas dan koreksi

Constraint `NOT NULL` tidak mencegah string kosong/spasi; pengaman sekunder kini diuji untuk keduanya. `trim()` mencakup whitespace ASCII yang diuji; Unicode whitespace belum diuji. Label generik produk hanya menjamin nama nonblank, bukan deskripsi visual atau identitas unik produk.

Tidak ada pengujian browser, screen reader, atau penutur asli fr/it/zh. Tooling proyek yang diperiksa tidak menyediakan Playwright; tidak menambah dependency untuk audit ini. Pemeriksaan statis dialog dan quickview tidak membuktikan interaksi modal berjalan. Label formulir dan kontrol yang lolos pemeriksaan struktural belum tentu mudah dipahami.

## 7. Bukti penerimaan tertunda

1. Review independen baru atas seluruh perubahan lanjutan ini.
2. Eksekusi browser saat membuka dialog katalog dan quickview home/PDP, termasuk alt eksplisit dan data kosong.
3. Audit semantik label, kata kerja tombol, konteks tautan, dan klasifikasi gambar dekoratif; screen-reader testing nyata.
4. Verifikasi pemahaman pesan pemulihan lintas locale serta tinjauan penutur asli.
5. Pengukuran coverage bila ambang 80% dipakai sebagai gate penerimaan.
