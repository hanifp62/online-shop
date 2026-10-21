# Evaluasi Tiga Kacamata — Teks Permukaan Toko (HCI · Marketing · Brand)

**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/nilai-tiap-teks-dengan-tiga-kacamata-hci-marketing`  
**Sumber Teks:** `STOREFRONT_TEXT_INVENTORY.md` (39 berkas Blade), `resources/views/catalog/labels.php` (kamus 5 bahasa), dan surel transaksional (`app/Mail/*`)  
**Acuan Nada:** `docs/02-product/VOICE_AND_TONE_MATRIX.md` (Keputusan #040)  
**Tanggal:** 2026-10-20  

---

## 1. Kerangka Penilaian Tiga Kacamata

Evaluasi ini menguji teks antarmuka ZAFIRA terhadap tiga lensa profesional:

1. **Lensa HCI & Usabilitas (Norman, Nielsen, Krug, Cooper):**
   - *Affordance & Signifier (Norman):* Apakah tombol/label mengisyaratkan aksi yang jelas tanpa ambiguitas?
   - *Model Mental vs Model Implementasi (Cooper):* Apakah istilah internal sistem (mis. *pending*, *token*, *v3.9*) bocor ke antarmuka pengguna?
   - *Don't Make Me Think (Krug):* Apakah teks dapat dipindai seketika tanpa menuntut tafsir kognitif berat?
   - *Pencegahan & Pemulihan Galat (Nielsen H5/H9):* Apakah pesan penjelas menenangkan dan memandu langkah korektif?

2. **Lensa Pemasaran & Konversi (Marketing & Trust):**
   - Kejelasan proposisi nilai, tidak ada klaim palsu, tidak ada urgensi murahan (*dark patterns*), dan friksi checkout minimal.
   - Transparansi informasi pembayaran, penghitungan voucher, dan status pemesanan.

3. **Lensa Karakter Brand (Quiet Luxury & Artisanal Identity):**
   - Nada *editorial, lugas, menenangkan, bersahaja* sesuai Matriks Nada Bicara ZAFIRA.
   - Keselarasan padanan bahasa Nusantara–Eropa (penggunaan istilah Prancis/Italia terukur sebagai aksen kemewahan).

### Skala Dampak

- **Skor 5 (Kritis/Wajib Diperbaiki):** Kebocoran model implementasi internal, istilah teknis yang merusak kepercayaan finansial, atau frasa yang melanggar pilar brand secara langsung.
- **Skor 4 (Tinggi/Sangat Disarankan):** Istilah ambigu atau tidak konsisten antarhalaman yang membingungkan alur navigasi/pembelian.
- **Skor 3 (Sedang):** Frasa redundan atau kurang elegan yang dapat ditingkatkan tanpa mengubah alur logika.
- **Skor 2 (Rendah/Catatan Keselarasan):** Pilihan aksen dwibahasa (Prancis/Indonesia) yang memerlukan panduan gaya penulisan seragam.
- **Skor 1 (Teladan/Pertahankan):** Sesuai matriks dan standar HCI.

---

## 2. Tabel Evaluasi Lengkap

| ID | Lokasi Berkas & Baris | Teks Saat Ini (Verbatim) | Evaluasi HCI | Evaluasi Marketing | Evaluasi Brand | Skor | Rekomendasi Solusi |
|---|---|---|---|---|---|---|---|
| **E-01** | `resources/views/welcome.blade.php:28` | `<title>ZAFIRA: La Maison de Lumière - Haute Couture Flagship v3.9</title>` | **Model mental bocor:** label versi rilis internal `"Flagship v3.9"` tampil di tab peramban dan cuplikan mesin pencari. | Mengesankan perangkat lunak mentah/uji coba alih-alih maison adibusana yang matang. | Melanggar pilar *Elegansi Bersahaja* & *Kejelasan Fungsional*. | **5** | Ubah menjadi `"ZAFIRA — La Maison de Lumière · Haute Couture"`. |
| **E-02** | `resources/views/shop/index.blade.php:22, 29` & pesan JS terkait | - `:22`: `"...pesanan berstatus pending / belum dibayar. Tidak ada pembayaran online..."`<br>- `:29`: Tombol `"Buat pesanan pending"` | **Kebocoran implementasi:** status database `pending` dijadikan label tombol aksi utama. Pengguna awam ragu apakah pesanan sah atau menggantung. | Menurunkan konversi checkout karena status `pending` terdengar pasif dan tidak pasti. | Melanggar pilar alur checkout: *"lugas, menenangkan, transparan, presisi tinggi"*. | **5** | Tombol diubah menjadi `"Konfirmasi Pemesanan"` / `"Pesan Sekarang"`. Penjelasan `:22` dihaluskan menjadi instruksi tindak lanjut penyelesaian pembayaran. |
| **E-03** | `resources/views/shop/index.blade.php:31` | - Tombol `"Hapus token dari formulir"`<br>- Label `"Token pelacakan pesanan"` | **Jargon komputasi:** istilah `token` (hash 64 karakter) membingungkan pembeli tamu yang mencari pesanan mereka. | Menciptakan friksi bagi tamu yang ingin melacak pesanan. | Cooper: *"manifest model vs implementation model"*. | **5** | Ganti label menjadi `"Kode Pelacakan Pesanan"` dan tombol `"Hapus Kode dari Formulir"`. Tetap validasi format hash secara transparan di sisi sistem. |
| **E-04** | `resources/views/shop/index.blade.php:19` vs `resources/views/partials/purchase-cart.blade.php:2` | - `shop`: `<h2>Tas pembelian standar</h2>`<br>- `cart`: eyebrow `Pembelian standar` + `<h2>Tas Belanja (0)</h2>` | **Inkonsistensi terminologi (Nielsen H4):** kata `"standar"` membingungkan (standar vs kustom?). Dua halaman berbeda menamai tas belanja secara berlainan. | Mengaburkan konsep belanja langsung vs concierge. | Matriks glosarium menetapkan padanan resmi: *"Tas Belanja"* atau *"Kantong Kriya"*. | **4** | Seragamkan judul menjadi `"Tas Belanja"` di seluruh storefront. |
| **E-05** | `resources/views/welcome.blade.php:664` | Tombol `"Buka Portal Klien Lengkap (/akun)"` | **Path URL mentah bocor:** teks tombol memuat rute teknis `(/akun)`. Pengguna tidak perlu melihat struktur direktori routing. | Terlihat seperti petunjuk pengembang internal, bukan layanan concierge atelier. | Tidak mencerminkan sofistikasi adibusana. | **4** | Ubah menjadi `"Buka Portal Akun Klien"` atau `"Masuk ke Ruang Klien"`. |
| **E-06** | `resources/views/partials/purchase-cart.blade.php:5` | Notifikasi tas lama: `"Konsultasikan tas lama"` | Frasa `"tas lama"` kurang beradab dan terkesan usang tanpa konteks migrasi peramban lokal. | Pesan fungsional baik (menjaga data tamu), namun pemilihan kata kurang mengundang. | Bisa lebih elegan, mis. membedakan reservasi sebelumnya dengan kreasi baru. | **3** | Ubah menjadi `"Konsultasikan Pilihan Sebelumnya"` atau `"Bawa Pilihan ke Concierge"`. |
| **E-07** | `resources/views/shop/index.blade.php:26` | Catatan voucher: `"Diskon voucher dihitung server setelah kode diterapkan. Total memakai tarif server terbaru."` | Frasa `"dihitung server"` mengekspos arsitektur backend ke hadapan pembeli. | Menimbulkan kesan sistem kaku atau lambat. | Cukup jelaskan bahwa nilai penyesuaian diperbarui secara otomatis saat diterapkan. | **3** | Ubah menjadi `"Penyesuaian nilai voucher akan langsung diperbarui pada total pembayaran."` |
| **E-08** | `resources/views/shop/index.blade.php:17` | Paragraf pembuka pesanan: `"Kelola pesanan nyata dari koleksi ZAFIRA."` | Kata `"nyata"` redundan dan memicu keraguan bawah sadar (apakah ada pesanan palsu/simulasi?). | Melemahkan otoritas toko. | Melanggar *Omit Needless Words* (Krug). | **3** | Sederhanakan menjadi `"Pantau dan kelola riwayat pesanan koleksi ZAFIRA Anda."` |
| **E-09** | `resources/views/welcome.blade.php:452` | `"Pilihan Pita Sutra"`, `"Pita Emas (Or)"`, `"Pita Safir (Saphir)"` | Kurung dwibahasa `(Or)` dan `(Saphir)` pada swatch pita disengaja sebagai aksen Prancis, namun tidak ada tooltip pembeda. | Aksen Prancis memberi nilai eksklusivitas, tetapi perlu konsistensi dengan label warna di katalog (`labels.php`). | Selaras dengan nuansa Eropa-Nusantara jika diposisikan sebagai aksen estetika resmi. | **2** | Pertahankan aksen dwibahasa dengan atribut `lang="fr"` yang semantik untuk aksesibilitas screen reader. |
| **E-10** | `resources/views/welcome.blade.php:562` & `partials/product-footer.blade.php:6` | Tautan footer: `"Indonesia / ID"` | Format `"Negara / Kode"` di footer berpotensi disangka pemilih bahasa aktif (padahal pemilih bahasa utama ada di navigasi atas: `ID / EN / FR / IT`). | Menimbulkan klik sia-sia jika tautan hanya mengarah ke `#main-content`. | Netral. | **2** | Perjelas peran elemen (mis. penanda wilayah pasar / pengiriman resmi Maison). |
| **E-11** | `resources/views/partials/product-footer.blade.php:2` & `welcome.blade.php:371, 532` | `"Parisian in spirit. Italian by craft. Indonesian at heart."` | Tagline filosofis lugas, mudah diingat, ritme tiga frasa seimbang. | Mempertegas diferensiasi brand adibusana global berakar Nusantara. | Teladan perwujudan pilar *Kriya Autentik*. | **1** | **Pertahankan tanpa perubahan.** |
| **E-12** | `resources/views/partials/product-footer.blade.php:20` & `welcome.blade.php:683` | `"Sebuah waktu, khusus untuk Anda."` | Penutup hangat untuk janji temu atelier; menenangkan emosi pengguna (Emotional Design - Norman). | Ajakan reservasi tanpa tekanan (*zero friction*). | Teladan pilar *Adab & Penghormatan*. | **1** | **Pertahankan tanpa perubahan.** |
| **E-13** | `resources/views/errors/layout.blade.php` & berkas turunan | Judul galat (`"Ada kendala di sisi kami"`, `"Halaman tidak ditemukan"`, `"Sesi berakhir"`) | Nielsen H9: pesan galat tidak menyalahkan pengguna dan mengakui kendala di pihak penyedia. | Menjaga reputasi saat terjadi insiden teknis. | Bahasa santun dan beradab. | **1** | **Pertahankan kerangka pesan galat yang ada.** |

---

## 3. Prioritas Eksekusi Perbaikan (Rencana Kerja Task Berikutnya)

Perbaikan teks pada task `terapkan-perbaikan-teks` dikelompokkan ke dalam tiga fase berurutan:

1. **Fase 1 — Perbaikan Kritis Skor 5 (Wajib/Segera):**
   - **E-01:** Hapus `"Flagship v3.9"` dari `<title>` di `welcome.blade.php`.
   - **E-02:** Ganti tombol `"Buat pesanan pending"` dan rapikan notice checkout di `shop/index.blade.php` serta teks umpan balik JS.
   - **E-03:** Bersihkan istilah `"token"` menjadi `"kode pelacakan"` di formulir pelacakan tamu `shop/index.blade.php`.

2. **Fase 2 — Perbaikan Konsistensi & Redundansi Skor 4 & 3:**
   - **E-04:** Seragamkan `"Tas pembelian standar"` menjadi `"Tas Belanja"`.
   - **E-05:** Hapus URL `(/akun)` dari tombol portal klien.
   - **E-06, E-07, E-08:** Haluskan mikrocopy notifikasi keranjang lama, voucher, dan pembuka riwayat pesanan.

3. **Fase 3 — Aksesibilitas Semantik Skor 2:**
   - Tambahkan atribut semantik `lang="fr"` pada istilah aksen Prancis (`Or`, `Saphir`, `Rendez-vous`) agar pelafalan screen reader akurat.

---

## 4. Batasan & Risiko Evaluasi

- **Penyelarasan JavaScript:** Sebagian teks umpan balik transaksi (mis. notifikasi sukses order dibuat, galat validasi formulir) dihasilkan secara dinamis di `shop/index.blade.php` (blok `<script>`) dan `public/js/zafira-core.js`. Perbaikan teks Blade pada task berikutnya wajib menyelaraskan pesan di sisi JavaScript agar tidak terjadi kontradiksi.
- **Katalog Multibahasa:** Berkas `resources/views/catalog/labels.php` menyediakan terjemahan 5 bahasa untuk katalog. Perubahan terminologi di Blade tidak boleh merusak pemetaan kunci kamus katalog (`$t['...']`).
