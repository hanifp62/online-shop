# AGENTS.md — Persistent Engineering Rules & Operational Guidelines

**Project:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Target Directory:** `~/Documents/online-shop`  
**Primary Language for Docs:** Bahasa Indonesia  
**Last Updated:** 2026-10-10

---

## 1. Core Principles (Prinsip Wajib)

1. **Evidence Before Implementation (Berbasis Bukti):**
   - Setiap keputusan teknis dan produk harus bersumber dari kode sumber aktual, konfigurasi terverifikasi, dokumentasi resmi, atau pengujian nyata.
   - Jangan menyajikan asumsi atau data simulasi sebagai fakta riwayat yang sesungguhnya.
   - Bedakan dengan tegas: Fakta terverifikasi, Inferensi beralasan, Asumsi sementara, Informasi belum tersedia, dan Rekomendasi terbuka.

2. **Preserve Existing Quality (Pertahankan Yang Sudah Baik):**
   - Identitas visual, aset desain, tipografi (*Bodoni Moda* & *Manrope*), dan palet warna (*Sapphire, Linen, Gold, Ivory*) pada storefront publik ZAFIRA yang berkualitas tinggi harus dipertahankan.
   - Fitur dan modul yang telah berjalan dan teruji harus tetap berfungsi tanpa regresi.
   - Hindari *rewrite* total tanpa alasan teknis yang justified.

3. **Continuous Evaluation (Evaluasi Berkelanjutan):**
   - Setiap perbaikan wajib mengidentifikasi baseline, merumuskan masalah, mengeksekusi perubahan terkontrol, menjalankan verifikasi, dan membandingkan hasil dengan baseline.
   - Klaim keberhasilan hanya valid jika didukung bukti eksekusi test atau verifikasi browser.

4. **Safety & Zero Secret Leakage:**
   - Dilarang keras menampilkan nilai rahasia seperti `APP_KEY`, kata sandi database, API keys, atau token sesi dalam chat atau dokumentasi terbuka.
   - Dilarang menjalankan perintah database destruktif (`migrate:fresh`, `db:wipe`, atau `DROP TABLE`) tanpa persetujuan eksplisit.
   - Jangan melakukan upgrade major version dependency atau framework secara sembarangan tanpa rencana kompatibilitas lengkap.

5. **Traceability & Documentation Governance:**
   - Semua requirement produk wajib memiliki ID unik (`FR-xxx`, `NFR-xxx`, `US-xxx`) dan acceptance criteria yang dapat diuji.
   - Seluruh task implementasi dan pengujian harus tertaut ke requirement terkait.
   - Dokumentasi tersentralisasi di folder `docs/` dengan indeks utama di `docs/README.md`.

6. **Interactive Prototype as North Star:**
   - Prototype adalah implementasi kode nyata yang dapat dijalankan dan diakses melalui browser, bukan sekadar mockup gambar statis.
   - Prototype merepresentasikan interaksi ideal untuk storefront pelanggan dan antarmuka admin atelier.

---

## 2. HCI, Usability & Design Thinking Council Protocol (Aturan Tetap Dewan Audit Interaksi)

Setiap pengembangan antarmuka, perbaikan UX, evaluasi silang (*crosscheck evaluating*), dan rilis prototype ZAFIRA **WAJIB** diaudit dan diselaraskan secara permanen dengan prinsip, teori, dan logika dari 7 Tokoh Pelopor Interaksi Manusia & Komputer (HCI/IMK):

1. **Don Norman (The Design of Everyday Things & Emotional Design):**
   - **Affordance & Signifiers:** Setiap elemen interaktif (tombol, input, swatch kain) harus secara visual dan taktil menunjukkan kemampuan interaksinya dengan penanda (*signifier*) yang tidak ambigu.
   - **Constraints & Conceptual Models:** Terapkan batasan fisik dan logis (misalnya panjang inisial monogram maksimal 3 karakter dengan indikator counter aktif `[2/3]`) agar pengguna membentuk model mental yang akurat tanpa salah duga.
   - **Gulf of Execution & Evaluation:** Minimalkan jurang eksekusi (mudah dipahami apa yang harus dilakukan) dan jurang evaluasi (umpan balik seketika yang mengonfirmasi efek tindakan pengguna).
   - **Emotional Design (Visceral, Behavioral, Reflective):** Pertahankan daya pikat estetika kemewahan (*visceral*), kehalusan dan efisiensi interaksi (*behavioral*), serta prestise dan rasa memiliki nilai warisan karya (*reflective*).

2. **Jakob Nielsen (10 Usability Heuristics & Heuristic Evaluation):**
   - **Visibility of System Status:** Umpan balik langsung untuk setiap status sistem (indikator pemuatan, stepper langkah concierge 1/2/3, status keranjang, dan breadcrumbs).
   - **Match Between System and Real World:** Terminologi ramah manusia dan atelier autentik (kriya, sutra, janji temu) tanpa istilah teknis database yang membingungkan.
   - **User Control and Freedom ("Emergency Exits"):** Setiap modal, laci keranjang, dan formulir harus menyediakan jalur keluar mudah melalui tombol tutup yang jelas, klik backdrop luar, dan tombol keyboard `Escape`.
   - **Consistency and Standards:** Standar navigasi konsisten, hierarki visual teratur, dan pola interaksi yang seragam di seluruh storefront dan konsol admin.
   - **Error Prevention & Defensive Design:** Cegah kesalahan sebelum terjadi melalui validasi input saat blur, nilai bawaan yang aman, dan konfirmasi untuk aksi berisiko.
   - **Recognition Rather Than Recall:** Tampilkan pilihan pengguna (misal: inisial monogram terpilih) langsung di ringkasan keranjang belanja sehingga pengguna tidak perlu mengingatnya.
   - **Flexibility and Efficiency of Use:** Akselerator keyboard untuk pengguna ahli (`?` untuk membuka panduan pintasan keyboard, filter instan satu ketukan).
   - **Aesthetic and Minimalist Design:** Rasio sinyal-ke-kebisingan (*signal-to-noise*) tinggi; tidak ada dekorasi visual tanpa fungsi.
   - **Help Users Recognize, Diagnose, and Recover from Errors:** Pesan ramah dan konstruktif jika terjadi kendala, bukan kode galat mentah.
   - **Help and Documentation:** Panduan kontekstual dan bantuan instan yang relevan di setiap tahapan.

3. **Ben Shneiderman (8 Golden Rules & Visual Information Seeking Mantra):**
   - **Strive for Consistency & Universal Usability:** Konsistensi aksi dan ketersediaan target sentuh aksesibel (minimal 44×44px, kontras WCAG 2.2 AA).
   - **Offer Informative Feedback & Design Dialogs to Yield Closure:** Tahapan transaksi dan janji temu memberikan penutupan psikologis (*closure*) yang melegakan dengan nomor referensi dan instruksi tindak lanjut.
   - **Permit Easy Reversal of Actions (Undo):** Menyediakan mekanisme pembatalan (*undo action*) saat barang dihapus dari keranjang belanja.
   - **Keep Users in Control & Reduce Short-Term Memory Load:** Antarmuka harus membuat pengguna merasa sebagai pengendali, bukan korban sistem.
   - **Visual Information Seeking Mantra:** *"Overview first, zoom and filter, then details-on-demand"* — Katalog menyajikan ringkasan visual, filter kategori/pencarian instan, dan drawer/modal detail mendalam saat diminta.

4. **Alan Cooper (Goal-Directed Design & Eliminating Excise):**
   - **Goal-Directed Design:** Rancang antarmuka berdasarkan tujuan hakiki persona (*Madame Vivienne - Klien VIP* menginginkan kemudahan dan eksklusivitas; *Maître Artisan* menginginkan kejelasan pesanan dan ritme kerja tanpa hambatan).
   - **Eliminating Excise:** Hilangkan beban kognitif dan administratif yang tidak bernilai tambah (kurangi pengisian data berulang, auto-format nomor kontak, simpan preferensi otomatis).
   - **Manifest Model vs Implementation Model:** Jangan mencerminkan struktur database internal ke antarmuka; tampilkan model konseptual yang alami bagi pengguna.
   - **Polite Software:** Perangkat lunak harus beradab, mengingat preferensi pengguna (mata uang, setelan suara, keranjang belanja di `localStorage`), dan toleran terhadap kekeliruan kecil.

5. **Douglas Engelbart (Augmenting Human Intellect & High-Bandwidth Interaction):**
   - **Augmentasi Kecerdasan:** Panel manufaktur atelier bukan sekadar tabel pencatat, melainkan instrumen augmentasi yang memperluas kapasitas analisis artisan dan pengelola (*DSS Goldratt*, visualisasi *takt time*, dan peta kendala).
   - **High-Bandwidth Interaction:** Penyajian informasi multidimensi dalam satu pandangan terpadu (*split-view blueprint* kriya, lembar kerja teknis terintegrasi).

6. **Tim Brown (Design Thinking & Human-Centered Innovation):**
   - **Tiga Lensa Desain:** Selaraskan *Desirability* (hasrat estetika mewah dan sentuhan emosional), *Feasibility* (kemudahan rekayasa kode berbasis web modern), dan *Viability* (nilai bisnis berkelanjutan Maison ZAFIRA).
   - **Prototyping as Thinking:** Jadikan prototype sebagai laboratorium interaktif yang dapat diuji nyata, bukan sekadar etalase statis.
   - **Extreme Users Empathy:** Perhatikan kenyamanan klien VIP yang menuntut kecepatan reservasi maupun pengrajin yang berinteraksi di bengkel kerja.

7. **Steve Krug ("Don't Make Me Think!" & Common Sense Usability):**
   - **Hukum Pertama Usabilitas:** *"Jangan buat saya berpikir!"* — Setiap elemen harus jelas maksudnya secara intuitif tanpa perlu tafsir.
   - **Billboard Design (Scan, don't read):** Rancang antarmuka untuk pengguna yang memindai cepat dengan hierarki visual tegas, penonjolan judul, dan poin-poin ringkas.
   - **Omit Needless Words:** Pangkas kata-kata instruksi klise; gunakan label aksi yang lugas dan berbobot (*"Pesan Janji Temu"* daripada *"Klik di sini untuk mengirimkan formulir reservasi"*).
   - **The Trunk Test:** Pengguna di halaman mana pun harus seketika memahami identitas situs, kategori aktif, lokasi saat ini, dan cara kembali ke beranda.

