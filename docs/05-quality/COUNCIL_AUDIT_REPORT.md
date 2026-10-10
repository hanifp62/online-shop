# Evaluasi Silang Dewan ZAFIRA

> **Catatan Bukti Sumber:** Dokumen ini merupakan analisis historis berbasis prinsip tanpa sitasi empiris langsung atau audit browser lintas-situs independen. Seluruh temuan usabilitas dan teknis di dalamnya tunduk pada verifikasi berbasis bukti nyata (lihat [`REMEDIATION_VERIFICATION_2026-10-10.md`](REMEDIATION_VERIFICATION_2026-10-10.md)).

Tanggal: 10 Oktober 2026. Status: audit dan rekomendasi; bukan persetujuan rilis atau bukti remediasi.

## Ringkasan eksekutif

**Rekomendasi: tunda klaim siap produksi.** Fondasi aplikasi memiliki pengujian yang berjalan, tetapi batas kepemilikan data, input concierge, kredensial contoh, dan kebenaran informasi UI perlu diperbaiki sebelum rilis publik.

Dewan ini adalah evaluasi menggunakan prinsip para tokoh, bukan konsultasi atau dukungan langsung dari mereka. Tidak ada skor kesiapan numerik karena belum ada rubrik berbobot yang dapat direproduksi.

### Baseline sesi

| Pemeriksaan | Hasil yang tercatat |
|---|---|
| `php artisan test` | 49 test, 168 assertion lulus |
| `npm run build` | Berhasil |
| `vendor/bin/pint --test` | Gagal pada `database/migrations/2026_09_14_000004_create_getcat_procedure.php` |
| Working tree sebelum penulisan laporan | 56 modified dan 59 untracked; perubahan lama tidak ditimpa |
| Browser, pembaca layar, beban produksi | Belum diverifikasi dalam audit ini |

Hasil baseline berasal dari eksekusi sebelumnya pada sesi audit yang sama. Pembacaan lanjutan bersifat statis dan tidak menjalankan migrasi atau mengubah data. Nomor baris mengacu pada working tree saat audit, bukan hanya HEAD.

## Metode dan batas bukti

- **Terverifikasi statis:** implementasi atau konfigurasi terlihat langsung dalam kode.
- **Inferensi:** dampak yang mengikuti kode, tetapi belum direproduksi dalam runtime.
- **Belum tersedia:** bukti lingkungan produksi, browser, atau benchmark tidak diperoleh.
- P0 berarti containment segera sebelum publikasi; P1 berarti penghalang rilis terkait; P2 berarti perbaikan terjadwal.
- Hash dalam dump bukan kata sandi plaintext. Keberadaan hash tetap memperluas paparan; penggunaan kredensial tersebut di produksi belum diketahui.

## 1. Dewan Executive/Product

Lensa Cagan dan Brown: validasi nilai pengguna sebelum memperluas fitur. Cooper dan Krug: alur yang jujur dan mudah dipahami lebih penting daripada label kemewahan tambahan.

1. Pertahankan storefront, font Bodoni Moda/Manrope, dan palet Sapphire/Linen/Gold/Ivory.
2. Jadikan alur katalog → personalisasi → tas → permintaan concierge → riwayat sebagai irisan penerimaan utama.
3. Jangan memperkenalkan Order/OrderItem, pembayaran, ledger, atau sistem manufaktur baru. Desain database menempatkan Order setelah MVP.
4. Pisahkan demo `prototype/` dari bukti penerimaan Laravel. localStorage dan HTTP 200 tidak membuktikan otorisasi, persistensi server, atau transaksi.
5. Hapus atau beri label simulasi pada angka dan sertifikat yang belum mempunyai sumber data nyata.

## 2. Dewan Security/Quality

Lensa Schneier, Shostack, Janca, Hunt: batas kepercayaan dan penyalahgunaan. Beck: uji regresi sebelum perbaikan. Lamport dan Kleppmann: identitas dan konsistensi data harus eksplisit.

| ID | Prioritas/status | Bukti | Dampak dan perbaikan minimum |
|---|---|---|---|
| SEC-01 | P0; statis | Tiga `database/zafira_ecommerce_20260921*.sql` terlacak Git; masing-masing memiliki satu INSERT users, dua pola hash bcrypt, dan dua alamat email unik | Konten sensitif ikut didistribusikan bersama repo. Tinjau asal data dan paparan; gunakan fixture sintetis. Penghapusan dump, rotasi kredensial, dan perubahan histori memerlukan persetujuan terpisah. Jangan menampilkan nilainya. |
| SEC-02 | P1; statis | `database/seeders/AdminSeeder.php`; dipanggil `DatabaseSeeder.php:18` | Kredensial admin lemah ditanam dalam source. Hindari akun admin berpassword default; provisioning harus eksplisit dan tidak mereset akun yang sudah ada. Penggunaan di produksi belum diketahui. |
| SEC-03 | P1; statis, eksploitasi belum diuji | `app/Http/Controllers/ProfileController.php:19-20` | Riwayat guest dicocokkan lewat email tanpa pembuktian kepemilikan email. Batasi riwayat ke `user_id`; klaim guest hanya melalui verifikasi yang dirancang terpisah. |
| SEC-04 | P1; statis | `app/Http/Controllers/StorefrontController.php:141-158` | `order_items` tidak memiliki kontrak validasi memadai dan harga berasal dari klien. Validasi bentuk/jumlah/produk; ambil harga dan identitas produk dari server. Ini integritas permintaan concierge, bukan bukti pembayaran tereksploitasi. |
| SEC-05 | P1; inferensi berbasis kode | `AdminCategoryController.php:55`, `config/cache.php:18`, framework `CacheServiceProvider.php:34-36` | `Cache::flush()` menghapus store luas. Tidak ada `cache.limiter` khusus di config; limiter framework memakai default. Invalidasi kategori berpotensi mereset penghitung throttle pada store yang sama. Gunakan invalidasi key katalog; buktikan penghitung tetap utuh dengan test. |
| SEC-06 | P2; statis | `StorefrontController.php:183-194` | Respons newsletter 201 versus 200 membedakan email baru dan terdaftar. Gunakan respons publik seragam; uji keduanya. |
| SEC-07 | P1 verifikasi deployment; dampak bersyarat | `bootstrap/app.php:21` | Semua proxy dipercaya. Risiko spoofing forwarded header bergantung pada akses langsung ke origin dan sanitasi ingress. Dokumentasikan topologi lalu batasi proxy bila diperlukan; jangan mengklaim bypass tanpa test. |

## 3. Dewan HCI/UX dan aksesibilitas

Lensa Norman: signifier dan model mental; Nielsen: status, konsistensi, pemulihan; Shneiderman: kendali dan reversal; Cooper: tujuan pengguna; Engelbart: informasi berguna; Brown: prototype yang diuji; Krug: navigasi jelas; Watson: semantik dan interaksi keyboard.

| ID | Prioritas/status | Bukti | Perbaikan minimum |
|---|---|---|---|
| UX-01 | P1; statis | `resources/views/products/show.blade.php:76-127`, `resources/js/app.js:531-543` | Ukuran dan monogram tampil interaktif tetapi tidak masuk payload penambahan tas. Bawa pilihan melalui identitas item, penyimpanan, ringkasan, dan concierge. Varian berbeda tidak boleh tergabung tanpa sengaja. |
| UX-02 | P1; statis | `resources/views/admin/dashboard.blade.php:88-110`, `products/show.blade.php:104-110` | Angka takt/yield/buffer dan klaim ledger/provenance ditulis literal. Hapus klaim atau labeli simulasi secara terlihat. Jangan membangun ledger untuk membenarkan dekorasi. |
| UX-03 | P1; statis, browser tertunda | `resources/views/welcome.blade.php:314-327`; `products/show.blade.php:83-99` | Filter dan ukuran tidak mengumumkan pilihan; pencarian tidak memiliki label eksplisit; label monogram belum terhubung. Tambahkan nama aksesibel, status pilihan, hasil dinamis, dan fokus terlihat. |
| UX-04 | P1 verifikasi; cakupan statis | `resources/js/app.js:731-746`, `welcome.blade.php:623` | Daftar focus trap bersama tidak mencakup profile drawer. Audit juga modal pintasan dan script inline sebelum menyimpulkan perilaku akhir. Uji fokus awal, Tab/Shift+Tab, Escape, penutupan, dan pengembalian fokus. |
| UX-05 | P2; statis | `welcome.blade.php:142-158,218-224` | Radiogroup berisi tombol tanpa role radio/checked. Gunakan pola tombol pilihan atau radio yang lengkap dan konsisten. |
| UX-06 | P2; statis | `products/show.blade.php:80,110` | Panduan ukuran memakai span klik dan bantuan memakai `alert()`. Pakai tombol native dan pola bantuan/dialog yang sudah tersedia. |
| UX-07 | P1 keputusan produk; statis | `welcome.blade.php:213` | Navigasi publik menuju `/prototype/` mencampurkan demo dan produk. Putuskan pembatasan preview atau label demo; jangan mengklaim URL rusak tanpa pemeriksaan server. |

Target sentuh minimal 44×44px adalah aturan proyek. Pengukuran computed layout, kontras, zoom 200%, mobile, reduced motion, dan pembaca layar belum dilakukan. Ini tidak sama dengan menyatakan semua target di bawah 44px melanggar WCAG 2.2 AA; kriteria WCAG mempunyai batas dan pengecualian sendiri.

`aria-hidden="true"` awal pada menu mobile bukan cacat dengan sendirinya: `openElement()` mengubahnya menjadi false dan `closeElement()` mengembalikannya menjadi true. Temuan awal tersebut tidak dipromosikan sebagai bug.

## 4. Dewan Ops/Architecture dan standar web

Lensa Fowler/Evans: batas modul/domain sederhana; Stonebraker/Kleppmann: data benar; Vogels/Hightower/Forsgren/Sloss/Majors/Gregg: operabilitas terukur; Berners-Lee/Cerf: interoperabilitas; Knuth: optimasi berdasarkan pengukuran; Hudson/Purbo: praktik implementasi dan operasi sesuai platform. Prinsip Swift tidak diterapkan pada Laravel tanpa relevansi konkret.

| ID | Prioritas/status | Bukti | Perbaikan minimum |
|---|---|---|---|
| OPS-01 | P1; statis, dampak browser belum diuji | `public/sw.js:1-6,29-38` | Homepage dicache cache-first bersama asset. HTML berstate autentikasi dapat basi dan berisiko tertinggal setelah logout. Keluarkan HTML dinamis dari cache, bersihkan cache versi lama, uji login/logout/offline lintas akun. |
| OPS-02 | P1 gate rilis; statis | `phpunit.xml:25-31` | Test memakai SQLite/array/sync. Tambahkan smoke/integration terisolasi pada driver deployment; test cepat tetap dipertahankan. Tidak ada bukti worker produksi mati atau database bersama pasti buruk. |
| OPS-03 | P2; statis | `database/migrations/2026_09_14_000004_create_getcat_procedure.php:21-31,42-46` | Kegagalan procedure ditelan; SQLite melewati jalur ini. Putuskan apakah procedure kompatibilitas wajib. Kegagalan yang diperlukan harus terlihat; jangan menjalankan ulang migrasi destruktif di data pengguna. |
| OPS-04 | P1 bukti operasi | Bukti supervisi worker, restore backup, alert, rollback dan uji `/up` belum tersedia dalam audit | Sediakan runbook dan hasil smoke pada staging. Ketiadaan bukti bukan bukti ketiadaan implementasi. |
| OPS-05 | P2; perlu benchmark | Controller katalog/cache dan query produk perlu pengukuran representatif | Ukur p50/p95, error rate, ukuran data, jumlah query, dan profil cache sebelum optimasi. Jangan memakai satu curl atau asumsi 1.000 baris sebagai kapasitas teruji. |

Race pada slug/upsert merupakan kandidat pengujian konkurensi, bukan insiden terkonfirmasi. CSP/HSTS merupakan usulan hardening sesuai HTTPS dan script aktual, bukan bukti kerentanan hanya karena header tidak ada.

## Koreksi evaluasi delapan fase sebelumnya

| Fase | Yang dapat dipertahankan | Koreksi penilaian |
|---|---|---|
| 0 — baseline | Riwayat Git dan kondisi runtime tercatat | Commit tidak membuktikan baseline bebas data sensitif. SEC-01 harus ditinjau. |
| 1 — audit | Dokumen audit tersedia | Perbarui dengan bukti lintas controller, UI, framework, dan batas runtime. |
| 2 — konteks | Identitas brand dan persona berguna | Pisahkan aspirasi atelier dari fitur yang benar-benar tersambung. |
| 3 — stabilisasi | Test/build berjalan | Pint belum bersih; jalur database procedure belum membuktikan keberhasilan. |
| 4 — produk | PRD dan requirement tersedia | FR-008 tidak boleh memperlakukan kesamaan email sebagai bukti kepemilikan. |
| 5 — arsitektur | Modular Laravel dan Order post-MVP layak dipertahankan | Cache adalah TTL 300 detik, bukan LRU. Shared DB tidak otomatis perlu diganti. |
| 6 — prototype | Demo interaktif berguna untuk eksplorasi | Demo localStorage dan HTTP 200 bukan acceptance end-to-end Laravel. |
| 7 — kualitas | 49 test merupakan baseline nyata | Label Passed/Selesai harus dikaitkan dengan test tertentu; belum berarti siap produksi. |

Dokumen lama tetap menjadi catatan historis. Klaim pada `REQUIREMENTS_TRACEABILITY.md`, `QUALITY_REVIEW.md`, `TARGET_ARCHITECTURE.md`, `ARCHITECTURE_DECISIONS.md`, dan tabel progres indeks memerlukan rekonsiliasi. Laporan ini adalah koreksi terkini, bukan perubahan diam-diam terhadap riwayat.

## Backlog prioritas dan requirement remediasi

ID berikut merupakan requirement usulan baru dengan namespace AUD agar tidak bertabrakan dengan PRD lama. Persetujuan spesifikasi diperlukan sebelum implementasi.

| Urutan/task | Requirement | Temuan | Acceptance criteria minimum |
|---|---|---|---|
| B01 containment | NFR-AUD-001 | SEC-01/02 | Keputusan asal/paparan data tercatat; tidak ada kredensial nyata dalam fixture baru; provisioning tanpa password default; penghapusan/rotasi/histori hanya sesudah persetujuan eksplisit. |
| B02 kepemilikan | FR-AUD-001 | SEC-03; koreksi FR-008 | Akun A tidak melihat guest request atau data akun B hanya melalui email yang sama; request yang dimiliki A tetap terlihat. |
| B03 kontrak concierge | FR-AUD-002 | SEC-04 | Tolak bentuk item/kuantitas/produk tidak valid; harga klien tidak menjadi sumber kebenaran; produk sah tetap diterima. |
| B04 invalidasi | NFR-AUD-002 | SEC-05 | CRUD kategori memperbarui katalog tanpa mereset counter limiter; test meliputi store yang mewakili deployment. |
| B05 personalisasi | FR-AUD-003 | UX-01 | Ukuran/monogram bertahan hingga reload dan payload concierge; varian berbeda terpisah; monogram validasi server maksimal tiga huruf sesuai kontrak produk. |
| B06 kejujuran UI | FR-AUD-004 | UX-02/07 | Tidak ada metrik/sertifikat simulasi yang disajikan sebagai fakta; navigasi demo jelas dan tidak disamakan dengan sistem live. |
| B07 aksesibilitas | NFR-AUD-003 | UX-03–06 | Nama, status pilihan, fokus, Escape, focus return, keyboard dan target 44px diverifikasi pada halaman terkait; visual brand tetap. |
| B08 cache browser | NFR-AUD-004 | OPS-01 | HTML autentikasi tidak disajikan dari cache lintas akun; cache lama dibersihkan; asset statis tetap bekerja. |
| B09 operasi | NFR-AUD-005 | SEC-07, OPS-02–04 | Topologi proxy, worker, backup/restore, rollback dan health check memiliki bukti staging; jalur migration kompatibilitas diuji tanpa data pengguna. |
| B10 privasi respons | NFR-AUD-006 | SEC-06 | Respons publik email baru/terdaftar seragam, data tersimpan benar, throttle tetap aktif. |
| B11 rekonsiliasi | NFR-AUD-007 | Koreksi delapan fase | Setiap Passed ditautkan ke bukti test; untested ditandai; TTL tidak disebut LRU; Pint dan build lulus. |
| B12 performa | NFR-AUD-008 | OPS-05 | Baseline representatif dan ambang yang disetujui tersedia sebelum perubahan performa. |

## Gate penerimaan remediasi

1. Persetujuan spesifikasi dan keputusan data/kredensial berisiko.
2. Rencana thin-slice dengan test gagal terlebih dahulu untuk tiap perubahan perilaku.
3. Seluruh baseline test tetap lulus, test baru lulus, build berhasil, Pint bersih.
4. Verifikasi browser dan driver deployment untuk klaim yang tidak tercakup unit test.
5. Review diff terfokus tanpa menimpa 115 perubahan sebelumnya.
6. Tidak ada commit, push, rotasi, penghapusan dump, atau rewrite histori otomatis.

Spesifikasi usulan: [remediasi dewan](../superpowers/specs/2026-10-10-council-audit-remediation-design.md).
