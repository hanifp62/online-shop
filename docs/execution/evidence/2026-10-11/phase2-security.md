# Phase 2 — Audit lanjutan keamanan dan kebutuhan getcat

Tanggal: 11 Oktober 2026. HEAD yang diperiksa: `2f355e1`, working tree berisi perubahan sebelumnya.

## Lingkup dan batas bukti

Audit statis lanjutan terhadap raw HTML, inline JavaScript, encoding JSON, atribut gambar/warna, dan aliran data tidak tepercaya. Audit ini melengkapi `security-final.md`, bukan sertifikasi keamanan aplikasi secara menyeluruh.

Tidak membaca `.env`, menjalankan query database, migrasi, `mysql_upgrade`, commit, atau mengubah kode aplikasi. Laporan ini tidak mengklaim reproduksi eksploit melalui browser. Hasil tes pada `security-final.md` merupakan bukti terdahulu, bukan eksekusi ulang dalam audit ini.

## Status

| Area | Status | Bukti dan kesimpulan |
|---|---|---|
| Dua raw HTML JSON-LD | Mitigasi terdahulu terkonfirmasi secara statis | Hanya dua `{!! $jsonLd !!}` pada view storefront/detail produk. `StorefrontController` menggunakan `JSON_HEX_TAG` bersama flag Unicode/slash. Lihat uji terdahulu di `security-final.md`. |
| Tiga konfirmasi hapus admin | Mitigasi terdahulu terkonfirmasi secara statis | `resources/views/admin/dashboard.blade.php:122,186,312` menggunakan `@js(...)`; form menyertakan `@csrf`. |
| Sink HTML storefront Laravel | Tidak ditemukan celah tambahan pada aliran yang diperiksa | `resources/js/app.js` menggunakan `escapeHtml` pada template keranjang dan hasil pencarian. Flash toast menggunakan `textContent`. Assignment gambar quick view menggunakan properti `src`, bukan HTML mentah. |
| Atribut swatch | Kandidat ditutup | `Product::swatchHex()` hanya mengembalikan lima konstanta hex. Input warna produk tidak diteruskan sebagai CSS mentah. `StorefrontController` memakai metode tersebut untuk nilai `swatch`. |
| URL gambar | Hardening terbuka; XSS/SSRF tidak terbukti | `Product::imageWidth()` hanya mengganti parameter lebar. Validasi admin menerima string, bukan allowlist URL. Blade meng-escape atribut dan JS meng-escape interpolasi HTML. Tidak ditemukan fetch server terhadap URL tersebut dalam controller/model yang diperiksa. URL eksternal tetap dapat memicu permintaan browser ke pihak ketiga; HTML escaping bukan validasi URL. |
| Prototype publik | Risiko terbuka, bukan PASS | `public/prototype` adalah symlink ke `../prototype`; route `/prototype` mengarah ke `/prototype/`. Sink `innerHTML` prototype tidak otomatis terlindungi oleh mitigasi storefront Laravel. Rincian di bawah. |
| CSP | Hardening terbuka | `SecurityHeaders` belum mengirim CSP. Header lain bukan pengganti contextual escaping. CSP ketat memerlukan inventaris inline handler/script sebelum diaktifkan. |
| getcat runtime | Tidak diperlukan oleh runtime yang diperiksa | Tidak ditemukan `CALL getcat` pada kode aplikasi/route; model kategori memakai query Eloquent. |
| getcat kompatibilitas dump | BLOCKED menurut bukti database terdahulu | `ERROR 1558`, sistem `mysql.proc` berisi 20 kolom sementara server memerlukan 21. Tidak diuji ulang melalui database dalam audit ini. |

## Prototype: reproduksi terisolasi dan mitigasi parsial

**Severity: rendah untuk penyerang jarak jauh; sedang bila origin/storage sudah terkontaminasi.** Prasyarat: penyerang dapat menulis state localStorage prototype di browser korban (misalnya melalui XSS lain, ekstensi berbahaya, atau akses lokal). Tidak ada jalur pengiriman payload lintas pengguna yang terbukti. `public/prototype` adalah symlink yang melayani prototype pada origin aplikasi, sehingga kontaminasi pada origin tetap perlu ditangani. Ini bukan bukti stored XSS backend atau eksekusi pada browser sungguhan.

`tests/prototype-security.test.mjs` menjalankan loader asli `PrototypeStore.load()` dan renderer asli dalam Node VM dengan localStorage dan DOM palsu terisolasi. Payload sintetis `<img src=x onerror="globalThis.injected=1">` dari storage mencapai string `innerHTML` mentah sebelum perubahan: `node --test tests/prototype-security.test.mjs` = **0/10 passed, 10 failed**. Ini membuktikan jalur data/HTML mentah, bukan bahwa handler gambar dieksekusi browser.

Mitigasi terfokus memakai `escapeHtml()` yang sudah ada pada teks dan atribut dari state di `showToast`, `renderKanbanBoard`, dan `renderAccountPortal`. ID pada handler inline dikonversi dengan `Number()` sebelum interpolasi; ID normal prototype berupa angka. Markup SVG dan tombol undo pada toast dipertahankan, termasuk callback. `node --test tests/*.test.mjs` = **37 passed, 0 failed** (termasuk 13 tes keamanan prototype). `node --check prototype/app.js` lulus.

**Risiko tersisa:** prototype memiliki sink `innerHTML` lain (keranjang, material, audit, modal kerja/sertifikat) yang tidak diaudit atau diperbaiki menyeluruh dalam perubahan terfokus ini. String input tidak dibuktikan bebas XSS pada seluruh prototype. Status browser/Playwright: **NOT RUN**. Jangan sambungkan prototype ke data pelanggan nyata sebelum audit sink dan verifikasi browser terpisah. Parameter URL `p` pada jalur yang diperiksa hanya dicocokkan ke katalog yang ada; tidak terbukti menjadi jalur injeksi langsung.

Review kode mandiri memeriksa diff perubahan terfokus, semua pemanggil `showToast`, penanganan ID melalui `openWorkOrderModal`/`openAuthenticityModal`, dan uji regresi. **Reviewer independen tidak tersedia pada harness ini; code-reviewer eksternal: NOT RUN.**

## getcat: runtime, migrasi, dan blocker

- `app/Models/Category.php:25–27`: `getById(int $cid)` menjalankan `where('cat_id', $cid)->first()`. Penyebutan procedure pada docblock menjelaskan ekuivalensi, bukan pemanggilan procedure.
- `database/migrations/2026_09_14_000004_create_getcat_procedure.php` hanya membuat procedure untuk driver MySQL/MariaDB; SQLite dilewati.
- SQL procedure bersifat statis: `SELECT * FROM categories WHERE cat_id = cid`; bukan SQL yang dikonstruksi dari input HTTP.
- Migrasi melakukan `DROP PROCEDURE` lalu `CREATE PROCEDURE`, dan menangkap seluruh `Throwable` tanpa meneruskan galat. Status migrasi `Ran` tidak membuktikan procedure berhasil dibuat. Kegagalan setelah drop juga dapat meninggalkan procedure tidak tersedia.
- Procedure dibutuhkan untuk kompatibilitas konsumen SQL/dump yang secara eksplisit memanggil `CALL getcat`, bukan runtime Laravel yang diperiksa.
- Bukti sebelumnya: `database/final-readonly.md:88–96` dan `database/restore-validation.md:18` mencatat kegagalan metadata/restore akibat `ERROR 1558`.
- Perbaikan sistem database memerlukan otorisasi, backup, dan rencana verifikasi terpisah. Jangan menjalankan `mysql_upgrade` otomatis pada server bersama. Alternatif server terisolasi masih perlu diputuskan pemilik lingkungan.

## Verifikasi dan perubahan

Verifikasi dilakukan melalui pembacaan sumber serta pencarian sink, referensi procedure, dan pemetaan source-to-sink. `Product::swatchHex`, seluruh pemakai `imageWidth` pada view, validasi `product_image`, dan pemakai gambar pada JS ditelusuri. Pemeriksaan filesystem mengonfirmasi symlink prototype publik.

Berkas yang dibuat audit ini: `docs/execution/evidence/2026-10-11/phase2-security.md` saja. Ledger, final handoff, dan indeks dokumentasi tidak diubah.

Tidak menjalankan ulang PHP/Node/browser test karena tidak ada perubahan kode; tidak ada klaim kerentanan baru yang telah direproduksi dan diperbaiki. Risiko prototype, pembatasan URL gambar, CSP, dan kompatibilitas `getcat` tetap terbuka dengan batas bukti di atas.
