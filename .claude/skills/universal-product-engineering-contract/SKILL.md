---
name: universal-product-engineering-contract
description: Operating system untuk membangun produk yang trustworthy, measurable, human-centred, dan bebas slop. Berlaku untuk semua proyek. Pakai untuk intake fitur, desain domain, review arsitektur, implementasi, dan verifikasi DoD.
metadata:
  version: "1.0.0"
---

# Universal Product & Engineering Contract (UPEC)

Kontrak operasi untuk semua pekerjaan produk dan engineering. Tujuan: setiap output
**trustworthy** (bisa diverifikasi), **measurable** (ada metrik), **human-centred**
(manusia paham dan memegang kendali), dan **bebas slop** (tidak generik, tidak halusinasi).

## 0. Cara Menggunakan Skill Ini

### Kapan dipakai
- Permintaan fitur baru, perubahan perilaku produk, refactor arsitektur, integrasi eksternal.
- Review desain / kode / PRD yang butuh standar objektif.
- User mengetik `/upec`, "pakai UPEC", "ikuti kontrak produk", atau meminta DoD / recap / ATM.

### Kapan TIDAK dipakai
- Pertanyaan faktual satu kalimat, fix typo, perintah shell murni.
- Eksplorasi visual murni (pakai `prototype`), audit gerak (pakai `improve-animations`),
  optimasi Vercel (pakai `vercel-optimize`), polish visual (pakai `impeccable`).
  UPEC tetap membungkus mereka sebagai sub-fase — lihat 2.4.

### Cara jalan
1. Identifikasi fase workflow saat ini (lihat §3). Jika ambigu, mulai dari Fase 0 Intake.
2. Jalankan protokol wajib yang relevan (§2) — minimal Research + Cross-check + Decision Log.
3. Isi artefak minimum fase tersebut. Jangan loncat fase tanpa mencatat alasan di Decision Log.
4. Akhiri dengan DoD (§7.1) dan Recap (§7.2) untuk pekerjaan >1 edit.

Aturan baca: SKILL.md ini adalah kontrak ringkas. Template detail ada di `references/`.
Jika ada konflik antara skill lain dan UPEC, UPEC menang untuk standar bukti dan DoD;
skill spesialis menang untuk detail craft di domainnya.

## 1. Prinsip Inti (Core Principles)

1. **Evidence before synthesis.** Klaim harus menunjuk ke file (`path:line`), output perintah,
   metrik, atau dokumen. Dilarang menyimpulkan dari ingatan.
2. **Trustworthy over clever.** Pilih solusi yang bisa diaudit, di-rollback, dan dijelaskan
   ke non-engineer. Hindari magic, implicit behavior, dan false precision.
3. **Measurable by default.** Setiap fitur punya metrik sukses + cara mengukurnya.
   Tanpa metrik = belum siap build.
4. **Human-centred & human-in-control.** AI / otomasi memberi rekomendasi + alasan +
   aksi yang bisa dibatalkan. Keputusan destruktif selalu konfirmasi eksplisit.
5. **No slop.** Dilarang lorem ipsum, data dummy yang lolos ke produksi, copy generik
   ("seamless", "cutting-edge", "revolutionary"), dan kode yang "kelihatan jadi" tapi tak diverifikasi.
6. **Narrow scope, clean edge.** Satu perubahan = satu tujuan. Tepi (error, kosong, loading,
   permission, rollback) didesain eksplisit, bukan `TODO nanti`.
7. **Reuse before invent.** Cek pola, model, komponen, dan skill yang sudah ada sebelum
   menambah yang baru. Duplikasi harus dijustifikasi di Decision Log.

## 2. Protokol Wajib (Mandatory Protocols)

### 2.1 Research Protocol
Jalankan sebelum menulis kode / desain final:
1. Petakan ground: baca file terkait (route, controller, model, migration, config),
   `composer.json` / `package.json`, `.env.example` untuk batasan stack.
2. Untuk klaim framework / API eksternal: verifikasi ke docs resmi versi yang dipakai
   (catat versi). Jangan mengandalkan memori cutoff.
3. Catat yang TIDAK ditemukan sama eksplisitnya dengan yang ditemukan
   ("dicari X di Y — tidak ada").
4. Output: daftar file yang dibaca + fakta kunci + lubang pengetahuan + rencana verifikasi.

### 2.2 Cross-check Protocol
- **Two-source rule** untuk klaim load-bearing: minimal 2 bukti independen
  (mis. kode + test, kode + log, docs + perilaku runtime).
- **Baca ulang region yang diedit** setelah `edit` dengan batasan preservasi;
  jika batasan dilanggar, perbaiki atau berhenti dan tanya.
- **Eksekusi > inspeksi** untuk perilaku: jalankan test / perintah / reproduksi
  bila memungkinkan dan murah. Tulis apa yang dijalankan + hasilnya di Recap.
- Jika temuan bertentangan dengan klaim sebelumnya, nyatakan diskrepansinya
  dan menangkan bukti.

### 2.3 Antislop Protocol
Ditolak saat review bila ditemukan:
- [ ] Copy / desain generik tanpa keputusan ("tombol biru standar", 3 varian nyaris sama).
- [ ] Angka presisi palsu (skor "87.42%", ETA "3.2 hari") tanpa sumber + interval / metode.
- [ ] State kosong / error / loading tanpa copy + aksi yang jelas.
- [ ] Field destruktif tanpa konfirmasi; aksi tanpa undo / rollback path.
- [ ] Kode tanpa verifikasi: tidak ada test / run / screenshot / log yang dikutip.
- [ ] Abstraksi prematur (framework mini, config untuk 1 pemakaian) tanpa 3 kasus nyata.
- [ ] Komentar sebagai diary berpikir panjang; komentar hanya "mengapa", bukan "apa".

Perbaikan: konkretkan (angka + sumber + aksi), kecilkan scope, atau hapus.

### 2.4 Skill Orchestration Protocol
UPEC adalah orkestrator. Delegasikan detail craft ke skill spesialis:

| Sinyal tugas | Skill yang dipakai | Peran dalam UPEC |
|---|---|---|
| Eksplorasi visual multi-varian | `prototype` | Fase Desain, divergensi |
| Review / polish UI, accessibility visual | `impeccable`, `review-animations` | Fase Verify untuk surface |
| Tambah gerak / audit animasi | `animate`, `improve-animations`, `find-animation-opportunities` | Fase Desain/Build untuk motion |
| Pilih library frontend | `pick-ui-library` | Fase Desain, keputusan dependensi |
| Deploy / optimasi Vercel, Web Vitals | `vercel-optimize`, `deploy-to-vercel`, `vercel-cli-with-tokens` | Fase Ship untuk target Vercel |
| Pola React/Next, view transitions | `vercel-react-best-practices`, `vercel-composition-patterns`, `vercel-react-view-transitions` | Fase Build frontend |
| Mobile (Expo/RN) | `animate-expo`, `vercel-react-native-skills` | Fase Build mobile |
| Image / brand direction | `imagegen-frontend-web`, `imagegen-frontend-mobile`, `brandkit`, taste skills | Fase Desain, referensi visual |

Aturan: satu skill per sub-tugas, catat di Skill Matrix (§7.4) skill apa dipakai
dan mengapa yang lain TIDAK dipakai. Jangan menjalankan dua skill yang tumpang tindih
untuk artefak yang sama tanpa justifikasi.

### 2.5 Decision Log Protocol
Setiap keputusan non-trivial dicatat ringkas (maks 5 baris per entri):
`Keputusan | Alasan | Alternatif ditolak | Risiko / rollback`.
- Minimal 1 entri untuk: pilihan arsitektur, skema DB, integrasi, auth model, metrik sukses.
- Koreksi / constraint aktif user tetap berlaku antar turn sampai dicabut eksplisit.
- Jika scope dipotong atau fase dilompati, catat alasannya di sini.

## 3. Workflow Pengembangan (Development Workflow)

| Fase | Tujuan | Artefak minimum | Gate keluar |
|---|---|---|---|
| 0 Intake | Ubah request samar jadi scope tajam | Problem statement 3 kalimat, non-goal, user + konteks pakai | User setuju scope; jika tidak, jangan lanjut |
| 1 Research & Thesis | Pahami domain + bukti | Product Thesis Canvas (§4.1), file/bukti yang dibaca | Thesis terisi; metrik sukses terdefinisi |
| 2 Design | Domain + decision engine + UX | Model domain, aturan keputusan, sketsa surface + edge states | ATM (§7.3) terisi; DoD disepakati |
| 3 Build | Implementasi tersempit | Kode + migration + test, reuse-first | Semua edge state diimplementasi, bukan TODO |
| 4 Verify | Buktikan klaim | Test run, cross-check 2 sumber, cek antislop + a11y | DoD (§7.1) centang semua |
| 5 Ship | Rilis aman | Migration reversible, rollback plan, observability | Bisa rollback dalam 1 langkah terdokumentasi |
| 6 Recap | Jejak audit | Recap (§7.2) + Decision Log update | Recap ditulis; follow-up jadi todo eksplisit |

Aturan fase: boleh mundur (Build → Design) kapan saja saat bukti baru muncul;
maju tanpa gate = pelanggaran kontrak, harus dicatat.

## 4. Kerangka Produk (Product Frameworks)

### 4.1 Product Thesis Canvas
Wajib diisi di Fase 1 (lihat `references/thesis-canvas.md`):
Pengguna · Masalah (bukti) · Konteks pakai (§6.4) · Perubahan perilaku yang diharapkan ·
Metrik sukses + baseline · Non-goal · Risiko kepercayaan terbesar.

### 4.2 Core Product Loop
Definisikan loop nilai sebagai: `Trigger → Aksi user → Keputusan sistem → Feedback → Retensi`.
Syarat: loop harus bisa selesai <3 langkah untuk aksi inti; setiap langkah punya
empty-state dan error-state; ada ukuran per langkah (konversi / drop-off).

### 4.3 User-Facing Surfaces
Inventarisasi setiap surface (halaman, email, notifikasi, API response):
tujuan 1 kalimat · informasi minimum · aksi primer (1 saja) · aksi sekunder ·
state: loading / kosong / error / tanpa-akses. Surface tanpa error-state = belum desain.

### 4.4 Domain Modeling
- Bahasa ubiquitous: nama model/metode sama dengan bahasa pengguna, bukan jargon DB.
- Invariant ditulis eksplisit (mis. "stok tidak boleh negatif", "order punya tepat 1 status aktif").
- Aturan Laravel: invariant di Model / Observer / FormRequest + constraint DB,
  bukan hanya di controller. Controller tipis; logika di Action/Service + Policy.
- Setiap relasi punya jawaban: cascade vs restrict vs set-null — dan alasannya.

### 4.5 Decision Engine Design
Untuk setiap keluaran sistem (rekomendasi, harga, status, prioritas):
input · aturan / model · confidence + cara dihitung · penjelasan user-facing (§6.2) ·
fallback saat input buruk · siapa yang bisa override + audit trail-nya.
Tidak ada "skor ajaib" tanpa §6.3.

### 4.6 Data Quality Rules
- Klasifikasikan field: `berapaalasan` (boleh NULL + alasan) vs `wajib` (constraint DB + validasi).
- Sumber kebenaran tunggal per fakta; sinkronisasi memakai idempotency key + timestamp.
- Seed/factory realistis dan jelas labelnya; data dummy tidak boleh bocor ke response produksi.
- Aturan review: migration baru dicek reversibility (`down`), index untuk query terpanas,
  dan tidak ada breaking rename tanpa path migrasi.

## 5. Kerangka Engineering (Engineering Frameworks)

### 5.1 Architecture Principles
- Monolit modular dulu (Laravel): `app/Domain/<BoundedContext>/` untuk logika,
  `Http/Controllers` tipis, `Policies` untuk otorisasi, `Observers` untuk efek samping.
- Dependensi satu arah: UI → Application → Domain ← Infrastructure. Domain tidak import HTTP/Eloquent langsung bila bisa dihindari; minimal pisahkan via interface/DTO.
- Kontrak antar modul: DTO + FormRequest + Resource, bukan array bebas.
- Tambah abstraksi hanya jika 3 kasus nyata atau dipaksa integrasi eksternal; catat di Decision Log.

### 5.2 Database & Data Layer
- Migration selalu reversible; `down()` diuji; rename kolom = tambah → backfill → hapus.
- Constraint di DB untuk invariant (unique, FK, check) + validasi aplikasi.
- Eloquent: eager-load untuk N+1, index untuk kolom filter/sort, pagination untuk list.
- Query terpanas harus punya `explain` / query-log saat review bila ragu.

### 5.3 External Integration
- Setiap integrasi: timeout + retry (bounded, backoff+jitter) + circuit breaker bila kritis +
  idempotency key untuk operasi tulis + webhook signature verification.
- Rahasia hanya via env; contoh di `.env.example` tanpa nilai nyata.
- Kegagalan pihak ketiga = degraded experience yang didesain (cached / queue / pesan jelas),
  bukan 500 mentah. Antri via queue untuk kerja lambat (lihat adapter §8: `QUEUE_CONNECTION=database`).

### 5.4 Security & Privacy
- Baseline OWASP: auth + otorisasi per-aksi (Policy/Gate, bukan hanya UI hide),
  mass-assignment guard (`$fillable`), validasi semua input (FormRequest),
  escape output (Blade `{{ }}`), CSRF untuk form, rate-limit untuk endpoint publik sensitif.
- Data sensitif: minimasi koleksi, enkripsi session bila perlu (`SESSION_ENCRYPT`),
  log tidak menyimpan PII / token / password. `APP_DEBUG=false` di produksi.
- Checklist sebelum ship: tidak ada secret di repo, tidak ada `dd()`/`dump()`,
  upload file tervalidasi tipe+ukuran, query aman dari injection (Eloquent/binding).

### 5.5 Reliability & Observability
- Log terstruktur: konteks (user_id, order_id, correlation), level benar, tanpa PII.
- Queue gagal harus terlihat (failed_jobs + alert/log) dan retryable.
- Health: smoke test rute kritis + `php artisan test` hijau sebelum ship.
- Metrik produk (§4.1) terhubung ke event yang benar-benar di-emit, bukan asumsi analytics.

## 6. UX/HCI Guidelines

### 6.1 Progressive Disclosure
Tampilkan minimum untuk keputusan saat ini; detail on-demand (expand, tab, step).
Aturan: 1 layar = 1 tugas primer; opsi lanjutan disembunyikan sampai relevan;
form panjang dipecah per langkah dengan ringkasan sebelum submit.

### 6.2 Explainability
Setiap output otomatis/sistem harus menjawab: apa yang terjadi, mengapa (faktor utama),
dan apa yang bisa dilakukan user (aksi + undo). Bahasa manusia, bukan kode status.
Contoh: bukan "Error 422", tapi "Stok varian M habis — pilih L atau aktifkan notifikasi."

### 6.3 No False Precision
- Dilarang menampilkan presisi yang tak didukung data (skor desimal, ETA menit, "% cocok" 2 desimal).
- Tampilkan rentang / kualitatif + dasar ("Laris — 120+ terjual minggu ini") atau
  confidence ("Estimasi 2–4 hari, berdasarkan 300 pengiriman terakhir").
- Harga, pajak, ongkir: tampilkan komponen + total final sebelum bayar; tidak ada biaya kejutan.

### 6.4 Two Contexts of Use
Desain untuk dua konteks:
- **Fokus** (desktop/tenang): tabel padat, filter kaya, bulk action, keyboard shortcut.
- **Terinterupsi** (mobile/sibuk): target sentuh ≥44px, 1 aksi primer sticky,
  state tersimpan otomatis (draft), bisa lanjutkan nanti.
Setiap flow inti harus lolos kedua konteks atau menyatakan pengecualiannya.

### 6.5 Accessibility
Target WCAG 2.2 AA: kontras teks ≥4.5:1, semua aksi bisa keyboard + focus visible,
form punya `<label>` + pesan error terasosiasi, gambar informatif punya `alt`,
motion menghormati `prefers-reduced-motion`, dan status diumumkan via `aria-live`
untuk aksi async. Uji dengan keyboard saja sebelum centang DoD.

## 7. Checklist & Templates

### 7.1 Definition of Done
Pekerjaan belum selesai bila ada yang belum centang:
- [ ] Scope + non-goal disetujui; perubahan scope tercatat.
- [ ] Thesis + metrik sukses terisi; event pengukuran di-emit.
- [ ] Domain invariant di kode + DB; migration reversible dan diuji naik/turun.
- [ ] Semua state: loading / kosong / error / tanpa-akses didesain + diimplementasi.
- [ ] Otorisasi per-aksi (bukan hanya sembunyi UI); input tervalidasi; tanpa secret bocor.
- [ ] Test relevan hijau (`php artisan test` untuk Laravel); N+1 dicek untuk list.
- [ ] Cross-check 2 sumber untuk klaim utama; Decision Log terisi.
- [ ] Cek antislop (§2.3) + a11y keyboard-pass lolos.
- [ ] Rollback plan 1 langkah terdokumentasi; Recap ditulis (§7.2).

### 7.2 Recap Template
Lihat `references/recap-template.md`. Minimum: apa berubah (file + baris),
mengapa (thesis/issue), bukti verifikasi (perintah + output), keputusan + risiko,
cara rollback, follow-up eksplisit.

### 7.3 ATM Analysis Template
ATM = **Asumsi → Tes → Metrik**. Lihat `references/atm-template.md`.
Setiap asumsi berisiko ditulis sebagai hipotesis yang bisa gagal + tes termurah +
ambang metrik yang membatalkan / meneruskan. Tanpa ATM = asumsi tak teruji.

### 7.4 Skill Applicability Matrix Template
Lihat `references/skill-matrix.md`. Catat skill yang dipertimbangkan, dipakai/tidak,
dan alasannya. Minimal baris untuk: skill yang dipakai + 1 skill yang sengaja TIDAK dipakai.

## 8. Project Adapter (Isi Sendiri — terisi untuk proyek ini)

> Sesuaikan per proyek. Di bawah sudah diisi untuk repo ini (ZAFIRA online-shop).
> Untuk proyek lain, salin skill ini lalu ganti blok ini.

- **Proyek:** ZAFIRA / online-shop — toko online.
- **Stack:** Laravel 12, PHP ^8.2, Vite + Tailwind CSS v4, Axios.
- **Data/antrean:** `DB_CONNECTION=sqlite` (dev), session/cache database,
  `QUEUE_CONNECTION=database`, mail `log` (dev). Produksi: ganti ke MySQL/Postgres +
  Redis untuk cache/queue sebelum beban nyata.
- **Perintah kunci:** `composer setup`, `composer dev` (server+queue+pail+vite),
  `composer test` (= `php artisan config:clear` + `php artisan test`),
  `npm run build` / `npm run dev`.
- **Konvensi repo ini:** controller tipis di `app/Http/`, model di `app/Models/`,
  observer di `app/Observers/`, route di `routes/web.php`, Blade + Vite di `resources/`.
- **Batasan saat ini:** belum ada auth terpasang di route (cek `routes/web.php`
  sebelum klaim "butuh login"); tidak ada Redis/S3 dikonfigurasi; mail hanya log.
- **DoD tambahan proyek ini:** `composer test` hijau, `npm run build` lolos untuk
  perubahan frontend, tidak ada kredensial di `.env` yang ter-commit.

## 9. Referensi & Bacaan Lanjutan

- Template: `references/thesis-canvas.md`, `references/atm-template.md`,
  `references/recap-template.md`, `references/skill-matrix.md`.
- Standar: OWASP Top 10 & ASVS (security), WCAG 2.2 AA (a11y),
  Laravel 12 docs (versi terkunci di `composer.json`), Tailwind v4 docs.
- Metode: Shape Up (scope + appetite), Jobs-to-be-Done (thesis),
  Domain-Driven Design Lite (ubiquitous language + invariant),
  Evidence-Based Management (metrik + baseline).
- Prinsip review internal: `vercel-optimize` (metrics-first),
  `impeccable` (craft bar visual), `prototype` (divergensi sebelum konvergensi).
