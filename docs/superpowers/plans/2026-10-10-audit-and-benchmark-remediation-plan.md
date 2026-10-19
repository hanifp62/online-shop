# Implementasi Perbaikan Audit & Benchmark ZAFIRA

> **Peringatan PONYTAIL & AGENTS.md:** Jangan tulis kode sebelum rencana implementasi ini disetujui. Setelah disetujui, pilih metode eksekusi (`subagent-driven` atau `native`).

Dokumen acuan: `docs/superpowers/specs/2026-10-10-council-audit-remediation-design.md`, `docs/05-quality/COUNCIL_AUDIT_REPORT.md`, dan `docs/02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md`.

## Sasaran

Menutup celah keamanan, keabsahan data, kejujuran klaim, dan aksesibilitas dasar tanpa merusak 49 test baseline, aset visual, atau arsitektur dasar.

---

### Task 1: Batas Data dan Keamanan (Backend)
- Perbaiki `ProfileController` agar hanya membaca milik `user_id`.
- Validasi `order_items` di `StorefrontController`: wajib array terbatas, produk harus ada di database, harga ditarik dari database (abaikan input client).
- Samakan response newsletter untuk email baru maupun terdaftar.
- Hentikan password default lemah di seeder.
- Verifikasi dengan test otomatis (failing test lalu passing test).

### Task 2: Cache, Proxy, dan Service Worker
- Ganti `Cache::flush()` pada perubahan kategori dengan invalidasi key spesifik.
- Atur `sw.js` agar menggunakan network-first untuk navigasi halaman utama.
- Catat konfigurasi trust proxy yang aman.
- Pastikan cache throttle tidak hilang saat kategori diupdate.

### Task 3: Kejujuran UI & Personalisasi (Hapus Klaim Palsu)
- Sertakan ukuran dan monogram dalam payload cart, penyimpanan lokal, dan pengiriman concierge.
- Hapus semua klaim palsu: telemetri MSI, buku besar SHA-256, sertifikat digital `alert()`, waktu pengerjaan statis, ukuran tubuh fiktif di akun, dan nama advisor hardcoded.
- Hapus opsi mata uang asing di footer; gunakan IDR secara konsisten.
- Sembunyikan tautan `/prototype/` pada mode produksi (`APP_ENV=production`), namun tetap pertahankan aset gambar.
- Petakan status akun hanya pada status concierge yang valid.

### Task 4: Aksesibilitas Dasar (Storefront & PDP)
- Berikan label aksesibel pada form pencarian, tombol filter, dan drawer.
- Pastikan modal dan drawer dapat ditutup dengan tombol `Escape`, fokus keyboard terperangkap saat terbuka, dan kembali ke pemicu saat ditutup.
- Ganti dialog `alert()` native pada PDP dengan modal informasi atau drawer panduan ukuran yang semantik.

### Task 5: Kebersihan Kode & Verifikasi Akhir
- Jalankan formatting Pint pada migration terkait.
- Jalankan seluruh 49 test baseline untuk memastikan tidak ada regresi.
- Jalankan build asset dan uji coba fungsional ringkas.

---

Rencana selesai dan disimpan di `docs/superpowers/plans/2026-10-10-audit-and-benchmark-remediation-plan.md`.
