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
