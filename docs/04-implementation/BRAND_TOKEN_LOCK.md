# R-55: Token Warna & Tipografi Brand Terpusat + Gerbang Otomatis

**Tanggal:** 2026-10-21
**Task NgodingPakeAI:** `baseline-codebase/warna-huruf-brand-terkunci-otomatis/token-warna-tipografi-brand-terpusat` (`375aeae0-252f-4373-be81-7e932a77318c`)
**Requirement:** R-55 (PRD baris 216 & 1284) — warna dan huruf brand dikunci otomatis sehingga perubahan menyimpang gagal di gerbang.
**Keputusan dewan:** `COUNCIL_DECISIONS.md` #054.

## 1. Sumber token (tidak dipindah, tidak diubah nilainya)

Satu sumber yang sudah ada dipakai apa adanya: blok `:root` di `resources/css/app.css` (baris 13–34).

| Token | Nilai | Token | Nilai |
|---|---|---|---|
| `--ivory` | `#f3efe7` | `--sapphire` | `#263a5d` |
| `--parchment` | `#e8e0d3` | `--sapphire-deep` | `#17263f` |
| `--sand` | `#c8b8a4` | `--gold` | `#b69a64` |
| `--taupe` | `#927f6b` | `--white` | `#fffdf9` |
| `--ink` | `#20201d` | `--serif` | `'Bodoni Moda', 'Times New Roman', serif` |
| `--muted` | `#6e6a62` | `--sans` | `'Manrope', Arial, sans-serif` |

Tidak ada palet baru, tidak ada skala shade baru, dan nama brand tidak berubah. Skrip generator bawaan skill (`.agents/skills/brand/scripts/sync-brand-to-tokens.cjs`, `.agents/skills/design-system/scripts/validate-tokens.cjs`) **tidak dijalankan dan tidak diubah**: keduanya menulis ulang palet, mengganti nama brand, melewati Blade, dan menunjuk path `.claude/skills/...` yang tidak ada di repo ini.

## 2. Gerbang otomatis

`tests/brand-tokens.test.mjs` (Node test runner, dijalankan CI lewat `node --test tests/*.test.mjs` di `.github/workflows/ci.yml`).

Gerbang memindai seluruh berkas di `resources/css`, `resources/js`, dan `resources/views` (termasuk Blade) dan gagal bila:

1. Ada hex literal yang nilainya **sama persis** dengan salah satu warna brand di `:root` (duplikasi token) — tanpa membedakan huruf besar/kecil (`#B69A64`); bentuk 3-digit diperluas sebelum dibandingkan. Entitas HTML seperti `&#039;` tidak dihitung. **Ini bukan detektor warna off-brand umum:** hex sembarang yang bukan nilai token (mis. `#ff00ff`) lolos. Lihat bagian 6 untuk format yang tidak dipindai.
2. Ada `font-family` yang menyebut face di luar Bodoni Moda / Manrope, fallback generik (`serif`, `sans-serif`, `monospace`, `ui-*`, `system-ui`, `inherit`), atau fallback platform yang sudah ada (`-apple-system`, `BlinkMacSystemFont`, `SFMono-Regular`, `Menlo`, `Arial`, `Times New Roman`, `Georgia`). `var(--token, fallback)` diurai berikut fallback-nya, termasuk fallback berkutip dan bertingkat (`var(--a, var(--b, "Papyrus"))`). Face asing yang tidak dikenal selalu gagal. Deklarasi inline Blade (`style="font-family: …"`) dan string JS ikut dipindai; kutip penutup atribut HTML menghentikan stack.

Definisi `--font*`, `--serif`, `--sans`, dan `--mono` dalam blok sederhana `:root` maupun `@theme` juga diperiksa dengan validator keluarga yang sama. Di kode aktual, `--font-serif` berada di `@theme`, sedangkan `--serif` berada di `:root`. Mutasi keduanya menjadi face asing ditolak, walaupun token belum dipakai.

`var()` menerima spasi/tab sebelum nama token dan sebelum kurung penutup (`var( --sans )`). Tanpa fallback, nama harus terdaftar sebagai token font pada `:root`/`@theme` di `resources/css/app.css`; `var(--custom)` dan `var(--mono)` yang belum terdefinisi gagal. Dengan fallback, fallback tetap divalidasi, termasuk yang bertingkat. Atribut `style` berkutip tunggal maupun ganda dipotong pada kutip pembukanya yang berpasangan **sebelum** penguraian `var()`, sehingga properti di elemen berikutnya tidak ikut terbaca sebagai font.

Gerbang juga menyimpan self-check fixture (hex brand huruf besar, stack campuran, entitas HTML, face asing, JetBrains Mono di luar dua berkas, metadata `theme-color` di luar daftar, fallback `var()` berkutip/bertingkat/berspasi, atribut Blade, string JS) agar aturannya sendiri terbukti masih menangkap pelanggaran.

### 2.1 Tindak lanjut review (HIGH: fallback berkutip dalam `var()`)

Klaim reviewer: fallback berkutip di dalam `var()` lolos dari gerbang lewat `split(/"(?=\s|>)/)`. Hasil penilaian independen:

| Masukan | Sebelum perbaikan | Sesudah |
|---|---|---|
| `var(--sans, "Comic Sans MS", sans-serif)` | 1 galat (`comic sans ms`) | 1 galat — sama |
| `var(--font, "Papyrus")` | 1 galat (`papyrus`) | 1 galat — sama |
| `var(--sans, "Manrope", sans-serif)` | 0 | 0 |
| `var(--serif, "Bodoni Moda" , serif)` (spasi sebelum koma) | 1 galat **positif palsu** (`var(--serif`) | 0 |
| `var(--font, "Comic Sans MS" , sans-serif)` | 2 galat (positif palsu + `comic sans ms`) | 1 galat (`comic sans ms`) |
| `var(--font, var(--serif, "Bodoni Moda"))` | 2 galat **positif palsu** | 0 |
| `var(--font, var(--x, "Papyrus"))` | 2 galat (bentuk nama rusak) | 1 galat (`papyrus`) |

Kesimpulan: **bypass HIGH TIDAK TERBUKTI** — semua contoh reviewer sudah tertangkap sebelum perbaikan. Cacat nyata yang terbukti adalah **positif palsu** pada fallback berkutip yang diikuti spasi sebelum koma dan pada `var()` bertingkat. Perbaikan minimal: `var()` diurai dari dalam ke luar lebih dulu, lalu pemisah atribut HTML hanya memotong pada `"` yang diikuti `>` atau spasi lalu karakter bukan koma. RED: tes baru `quoted var() fallbacks…` gagal sebelum perbaikan (`off-brand font var(--serif`); GREEN sesudahnya. Contoh reviewer yang sudah lulus sebelumnya adalah cakupan regresi, bukan RED.

## 3. Allowlist eksplisit dan alasannya

| Cakupan | Alasan |
|---|---|
| `resources/views/mail/*.blade.php` (4 berkas) | Klien email tidak mendukung CSS custom property secara andal; inline literal adalah satu-satunya jalur yang aman. Saat ini keempat berkas **tidak memuat** hex atau `font-family` sama sekali; allowlist bersifat pencegahan. |
| `<meta name="theme-color" content="#17263f">` di 8 view yang sudah ada | Metadata chrome peramban tidak mengevaluasi CSS var. Pengecualian terikat pada string persis itu dan pada daftar berkas yang ada, bukan pola bebas. |
| `JetBrains Mono` di `resources/css/app.css` dan `welcome.blade.php` | Angka teknis (counter monogram, edisi hero) memakai monospace; bukan face brand baru. Dibatasi hanya pada dua berkas itu. |
| Dokumen PDF (`app/Services/PdfDocumentService.php`: `NotoSansSC-Regular.ttf`, DejaVu Sans) | Cakupan glyph zh/latin pada Dompdf. Bodoni Moda/Manrope tidak punya glyph Han. Di luar cakupan pindai (bukan `resources/`), dicatat agar tidak diganti di masa depan. |

## 4. Penggantian literal (nilai render tidak berubah)

Hanya literal yang **identik** dengan token yang diganti:

| Berkas | Sebelum | Sesudah |
|---|---|---|
| `resources/css/admin.css` | `--zafira-muted: var(--muted, #6e6a62)` | `var(--muted)` |
| `resources/css/app.css` (2×) | `var(--gold, #b69a64)` | `var(--gold)` |
| `resources/views/admin/auth-events.blade.php` | `--zafira-sapphire: #17263f` | `var(--sapphire-deep)` |
| `resources/views/welcome.blade.php` (11×) | `var(--gold, #b69a64)`, `var(--ink, #17263f)`, `var(--ivory, #f3efe7)` | `var(--gold)`, `var(--ink)`, `var(--ivory)` |
| `resources/views/welcome.blade.php` | `style="--foil:#263a5d" data-foil="#263a5d"` | `var(--sapphire)` (preview monogram memakai nilai ini sebagai `style.color`) |

Pada render normal (stylesheet `app.css` termuat lewat `@vite`), tampilan tidak berubah: token terdefinisi sehingga fallback tidak pernah dipakai. Urutan muat stylesheet **tidak dijamin**: bila `app.css` gagal dimuat atau terlambat, perilaku fallback berubah — properti yang dulu jatuh ke hex literal kini menjadi tidak valid saat nilai dihitung (`inherit`/`initial`), jadi warna bisa berbeda selama kondisi itu. Catat: `var(--ink, #17263f)` di `welcome.blade.php` sebelumnya memakai fallback **sapphire-deep** untuk token `--ink` (`#20201d`); pada render normal nilai yang dirender tetap `#20201d`.

Palet varian per halaman yang **bukan** nilai token (shop/catalog `#0d1b2a`, `#a17d36`, `#f4ede2`, error `#10244a`, prototipe `#0f1c33`, `#b89858`) sengaja **tidak** dinormalisasi: menyamakannya akan mengubah tampilan, di luar lingkup task ini.

## 5. Bukti gerbang

| Gerbang | Hasil |
|---|---|
| RED awal (`tests/brand-tokens.test.mjs` sebelum perbaikan) | `EXIT=1`, 17 literal warna brand nyata pada run `brand-red2.log` (bukan fixture) di `admin.css`, `app.css`, `auth-events.blade.php`, `welcome.blade.php` |
| GREEN (`node --test tests/brand-tokens.test.mjs`) | 3 tes lulus (termasuk tes tindak lanjut review bagian 2.1), `EXIT=0`; `node --test tests/*.test.mjs` ulang: 308 lulus, `EXIT=0` |
| `php artisan test` | **956 lulus, 4 dilewati, 27.027 assertions**, `EXIT=0` |
| `node --test tests/*.test.mjs` | **307 lulus, 0 gagal**, `EXIT=0` |
| `npm run build` | `EXIT=0` (`app-C4pQrHjM.js`, 34,70 kB) |
| `vendor/bin/pint --dirty --test` | `EXIT=0` (`{"tool":"pint","result":"passed"}`) |
| `git diff --check` | `EXIT=0`, keluaran kosong |

Log: `g-php.log`, `g-node.log`, `g-build.log`, `g-pint.log`, `g-check.log`, `brand-red.log`, `brand-green2.log` di direktori temporer OpenCode.

## 6. Batas yang diketahui

- Temuan reviewer independen menjadi masukan perbaikan ini. Persetujuan reviewer atas diff akhir masih harus dilakukan integrator.
- Gerbang hanya memindai `resources/`. `public/prototype/app.css` (241 literal), `public/app.js`, dan `public/js/zafira-core.js` **di luar cakupan**: berkas itu memakai palet prototipe tersendiri plus tema gelap, dan memindainya akan memaksa redesain.
- Gerbang warna hanya mendeteksi **duplikasi hex token brand yang dikenal**, bukan semua warna off-brand. Tidak terdeteksi: hex non-token sembarang, hex yang beda satu digit, hex 8-digit dengan alpha (`#b69a64cc`), `rgb()`/`rgba()`/`hsl()`/`oklch()` ekuivalen (mis. `rgba(182,154,100,...)` yang masih dipakai untuk opasitas), dan nama warna CSS.
- Shorthand CSS `font:` tidak dipindai; yang dipindai adalah `font-family` serta definisi token font pada blok sederhana `:root`/`@theme`. Properti yang dibangun dinamis di JS (mis. `style.fontFamily = x`, `setProperty('font-family', …)`) juga tidak dipindai.
- Parser berbasis regex, bukan parser CSS/HTML/Blade: deklarasi `font-family` dibatasi satu baris; komentar, escape CSS, interpolasi Blade, selector `:root` gabungan, cascade, dan alias siklik tidak dimodelkan. Token lokal di luar blok sederhana `:root`/`@theme` tidak menjadi daftar referensi; token tanpa fallback harus terdaftar di `app.css`. Token dengan nama lain tidak diperiksa sebagai definisi font.
- Parser `font-family` berbasis regex: stack tak valid tanpa koma setelah nama berkutip (`"Manrope" Papyrus`) terpotong di kutip dan face kedua tidak diperiksa. Browser menolak deklarasi seperti itu.
- Tidak ada verifikasi browser atau pembanding piksel; kesamaan tampilan disimpulkan dari identitas nilai token, bukan diukur.

### Bukti tindak lanjut MEDIUM/LOW (2026-10-21)

- Scope perubahan: hanya `tests/brand-tokens.test.mjs` dan dokumen ini; tidak mengubah CSS produksi.
- Mutating fixture mengubah `--serif` menjadi Papyrus dan `--font-sans` menjadi Comic Sans MS dalam salinan string `app.css`.
- RED rekonstruksi: salinan guard dengan pemeriksaan definisi token dinonaktifkan gagal pada fixture root (`actual: []`, expected Papyrus), `EXIT=1`. Ini bukti mutasi setelah edit awal, **bukan klaim test-first sebelum edit**.
- `node --test tests/brand-tokens.test.mjs`: 6 lulus, `EXIT=0`.
- `node --test tests/*.test.mjs`: 315 lulus, `EXIT=0`.
- Log: `brand-red-review.log`, `brand-review-full.log` pada direktori temporer OpenCode.
- Browser, build, PHP, dan coverage tidak dijalankan ulang pada perubahan guard ini; tidak ada klaim coverage 80%.
