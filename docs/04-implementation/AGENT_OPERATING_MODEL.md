# Model Operasi Tim Subagent ZAFIRA

**Status:** Aktif sejak 2026-10-19  
**Tujuan:** Menambah kapasitas eksekusi tanpa menambah benturan edit, klaim tanpa bukti, atau regresi.

## 1. Struktur kerja

Tim memakai 14 divisi virtual dan 56 peran. Peran adalah taksonomi kapabilitas, bukan jumlah subagent yang wajib aktif. Jumlah subagent aktif ditentukan oleh ketersediaan pekerjaan independen yang siap eksekusi.

| Divisi | Peran inti | Kontrak bukti minimal |
|---|---|---|
| PMO | Program manager, delivery lead, risk owner, release manager | Status task cocok dengan ledger bukti dan ref plan aktual |
| Product | Product manager, business analyst, domain analyst, acceptance owner | Dokumen requirement/AC tertulis, scoped, dan dapat diuji |
| Research | UX researcher, market researcher, technical researcher, evidence auditor | Sumber primer dicatat; batas klaim dan asumsi dipisahkan tegas |
| Design | HCI lead, interaction designer, accessibility specialist, content designer | Laporan kepatuhan HCI 7 Master dan target WCAG 2.2 AA terverifikasi |
| Frontend | Frontend lead, storefront engineer, admin UI engineer, client-state engineer | Node test (`node --test`) dan production build (`npm run build`) `EXIT=0` |
| Backend | Backend lead, API engineer, domain engineer, auth engineer | Isolated test suite (`php artisan test`) `EXIT=0` tanpa membocorkan state |
| Data | Database lead, migration engineer, query optimizer, data integrity engineer | Uji migrasi up/down idempotent, rollback guard terbukti, constraint valid |
| Security | Security lead, appsec reviewer, auth/RBAC reviewer, privacy reviewer | Zero secret exposure, OWASP sanitasi terbukti, 0 temuan HIGH/CRITICAL |
| QA | QA lead, test designer, E2E engineer, regression analyst | Log pengujian deterministik dengan command, timestamp, dan `EXIT=0` |
| Platform | DevOps lead, runtime engineer, CI engineer, observability engineer | Skrip server helper dan environment dapat direproduksi tanpa port collision |
| Review | Code reviewer, architecture reviewer, performance reviewer, maintainability reviewer | Sesi reviewer independen dari penulis; temuan diverifikasi sebelum fix |
| Knowledge | Documentation lead, traceability analyst, ADR writer, handoff editor | Dokumen tertaut ke ID requirement, indeks README, dan git commit |
| Support & Backup | Backup lead, unblocker, pair engineer, standby verifier | Blocker yang diambil alih punya diagnosis tertulis dan hasil terverifikasi |
| Sekretariat | Sekretaris tim, pencatat ledger, notulis keputusan, pengelola antrean | Ledger, antrean, dan log keputusan cocok dengan status agent aktual |

## 2. Aturan wajib

1. **Satu owner per task:** Setiap pekerjaan wajib memiliki satu lead agent yang bertanggung jawab.
2. **Satu penulis per file:** Dilarang mengizinkan dua agent aktif menulis ke file yang sama secara bersamaan.
3. **Blocker didahulukan:** Selesaikan blocker aktif sebelum membuka fitur atau scope baru.
4. **Scale on demand:** Subagent baru hanya diluncurkan jika ada paket kerja terisolasi dan tidak bentrok.
5. **Reviewer independen:** Penulis kode dilarang mereview kodenya sendiri. Session ID reviewer wajib berbeda dengan session ID author.
6. **Verifikasi temuan review:** Temuan review wajib dicek ke kode sumber sebelum dilakukan koreksi.
7. **Standar bukti:** Klaim lulus berbasis perintah wajib mencatat command, waktu eksekusi, diff/log, dan `EXIT=0`. Klaim berbasis review manual wajib mencatat tanggal, kriteria, temuan, dan sesi reviewer; `EXIT` tidak boleh dikarang.
8. **Eksekusi serial untuk resource bersama:** Uji browser live, server dev, dan database bersama wajib dijalankan serial.
9. **Batas dua kali gagal:** Jika dua percobaan berturut-turut gagal pada sub-masalah yang sama, hentikan perubahan kode dan buat diagnosis akar masalah tertulis.
10. **Status berbasis bukti:** Status agent aktif tidak sama dengan status produk. Status produk ditentukan oleh hasil verifikasi.
11. **Boundary keamanan:** Agent dilarang membaca file `.env*`, dilarang push ke remote tanpa izin, dilarang mereset status task sepihak, dan dilarang menjalankan perintah destruktif database (`migrate:fresh`, `db:wipe`).
12. **Transparansi ketidaktahuan:** Hal atau kondisi yang belum terbukti secara empiris wajib ditulis `TIDAK TERBUKTI`.

## 3. Roster aktif dan ledger kepemilikan

Setiap penugasan pekerjaan wajib dicatat dalam ledger (misalnya di handoff/plan doc atau log state) dengan skema tetap:

```
Task Ref / ID       :
Virtual Role        :
Started At          :
Timeout / Check-at  :
Last Heartbeat      :
Author Session ID   :
Target Files (Write):
Reviewer Session ID :
Status              : [Ready | Running | Review | Blocked | Done]
Handoff Path        :
```

Aturan integritas: Jika `Author Session ID` sama dengan `Reviewer Session ID`, hasil review ditandai **NON-INDEPENDENT** dan gerbang integrasi ditolak.

## 4. Format evaluasi kinerja berkelanjutan (Evaluation Record)

Setiap penyelesaian task wajib memuat satu blok evaluasi standar sebelum gerbang task ditutup:

```markdown
### Continuous Evaluation Record
- **Task Ref / Fitur:**
- **Acceptance Criteria per Gate:** (Kriteria lulus yang ditetapkan sebelum perubahan)
- **Baseline:** (Kondisi sebelum perubahan, bug reproduksi, atau tes gagal awal)
- **Commit / Diff Awal–Akhir:** (Commit dasar dan ringkasan `git diff --stat` saat bukti diambil)
- **Controlled Change:** (Daftar file diubah dan ruang lingkup fungsionalnya)
- **Verification Commands & Exit Status:**
  - `[Command 1]` @ [waktu] -> EXIT=[0|X]
  - `[Command 2]` @ [waktu] -> EXIT=[0|X]
- **Manual Review Evidence:** (Untuk pemeriksaan tanpa perintah: tanggal, kriteria, temuan, sesi reviewer)
- **Artifact / Log Path:** (Lokasi output bukti)
- **Comparison to Baseline:** (Bukti konkret bahwa masalah teratasi tanpa regresi)
- **Regressions Checked:** (Daftar suite regresi yang dijalankan dan hasilnya)
- **Reviewer Verification:** (Session ID reviewer independen dan status persetujuan)
- **Approver:** (Sesi integrator yang menyetujui; tidak boleh sama dengan author)
- **Decision:** [ACCEPTED | REWORK_REQUIRED | BLOCKED]
```

## 5. Protokol eskalasi dan stop authority

1. **Trigger:** Terjadi kegagalan 2x berturut-turut pada tes yang sama, terdeteksi potensi kebocoran data, atau benturan file write antar agent.
2. **Aksi seketika:** Subagent yang bersangkutan wajib berhenti mengubah kode dan mengubah status task menjadi `Blocked`.
3. **Catatan eskalasi:** Wajib memuat (a) kegagalan yang terjadi, (b) hipotesis akar masalah, (c) owner yang bertanggung jawab mengurai blocker, dan (d) kriteria untuk boleh melanjutkan.
4. **Resumption:** Pekerjaan hanya boleh dilanjutkan setelah lead/integrator mengonfirmasi perbaikan hipotesis. Gerbang kualitas dilarang diturunkan untuk meloloskan task.

## 6. Kanban

| Status | Syarat masuk / keluar |
|---|---|
| Backlog | Requirement dan acceptance criteria tertulis |
| Ready | Owner, file write boundary, dan skenario tes ditetapkan |
| Running | Implementasi berjalan, catatan perubahan terisolasi |
| Review | Tes mandiri lolos, review independen dimulai oleh agent berbeda |
| Blocked | Eskalasi aktif, ada catatan akar masalah dan PIC pengurai |
| Done | Evaluation Record lengkap, bukti lulus sesuai aturan 2.7, review independen disetujui |

## 7. Metrik evaluasi performa tim

| Metrik | Definisi |
|---|---|
| Throughput sah | Jumlah task selesai yang diverifikasi dengan Evaluation Record lengkap dan bukti lulus sesuai aturan 2.7 |
| Umur blocker | Durasi dari pencatatan status `Blocked` hingga akar masalah terurai |
| First-pass review rate | Persentase kode yang langsung disetujui reviewer independen tanpa temuan HIGH/CRITICAL |
| Rasio regresi | Jumlah tes sebelumnya hijau yang sempat merah akibat perubahan baru |
| Rasio rework | Perubahan ulang kode yang sudah dinyatakan selesai akibat cacat desain atau implementasi |

Jumlah agent yang dijalankan bukan ukuran kinerja. Peningkatan kinerja diukur dari naiknya throughput sah serta menurunnya umur blocker, rasio regresi, dan rasio rework.

## 8. Tim Support dan Backup

Tujuan: tidak ada lane menganggur atau macet tanpa tindak lanjut, dan tidak ada agent yang dibiarkan sendirian.

**Definisi (agar dapat diperiksa):**

| Kondisi | Definisi | Batas |
|---|---|---|
| Menganggur | Sesi selesai atau tanpa tugas Ready, sementara ada kartu Ready/Backlog bertulis AC | Ditindaklanjuti pada notifikasi berikutnya, bukan "nanti" |
| Terhambat | Status `Blocked`, atau gagal 2x pada sub-masalah yang sama | Backup ditugaskan segera setelah eskalasi dicatat |
| Macet diam-diam | Belum ada notifikasi/handoff saat `Check-at` tercapai | Saat siklus agent utama berikutnya, catat `Blocked` dan kirim backup diagnosis; jangan asumsikan sesi berhenti |

**Aturan:**

Antrean bantuan hanya memuat subpekerjaan task NgodingPakeAI yang sedang terbuka. Task berikutnya tetap diambil satu per satu melalui `task next`, setelah task sekarang selesai; checkpoint layer/fase tetap berlaku. Jangan membuka backlog server untuk mengisi kapasitas.

1. **Urutan penugasan ulang:** agent selesai → (a) bantu diagnosis lane terhambat; (b) jika tidak ada, ambil subpekerjaan Ready dari task NgodingPakeAI yang sedang terbuka; (c) jika tidak ada, lakukan review independen atau verifikasi dengan perintah yang sudah ditentukan; (d) jika tidak ada pekerjaan aman, catat alasannya dan lepaskan penugasan. Jangan menciptakan pekerjaan pengisi.
   - Maksimal enam belas subagent berjalan serentak per task (dinaikkan atas instruksi eksplisit pengguna 2026-10-19); slot baru memerlukan cakupan terisolasi. Peran virtual tidak wajib memiliki sesi tersendiri.
   - Hanya agent utama menjalankan `task next`, `task start`, `task complete`, dan `task fail`.
   - Pada checkpoint layer/fase, selesaikan verifikasi saat ini, tulis laporan, lepaskan penugasan kosong, lalu tunggu persetujuan pengguna. Jangan meluncurkan atau menugaskan ulang agent untuk fase berikutnya.
2. **Backup tidak menimpa owner:** backup bekerja lewat salah satu dari tiga mode, dicatat di ledger:
   - **Diagnose:** hanya baca dan tulis diagnosis akar masalah ke handoff.
   - **Pair:** owner tetap penulis file; backup menyiapkan tes, repro, atau bukti.
   - **Takeover:** hanya setelah sesi pemilik berakhir atau berhenti terkonfirmasi dan integrator mengizinkan sesuai bagian 5. Rekam diff parsial dan handoff sebelum mengubah owner serta batas file di ledger. Reviewer wajib berbeda dari author awal maupun penggantinya. Status `Blocked` saja tidak membuktikan penulis telah berhenti.
3. **Standby verifier:** menjalankan ulang perintah verifikasi lane lain secara independen. Hasilnya ditulis "ricek oleh sesi X", dan tidak menggantikan reviewer independen.
4. **Bantu tanpa menghakimi:** laporan blocker menyebut kondisi dan bukti, bukan menyalahkan agent. Temuan salah ditangani dengan koreksi proses, bukan hukuman.
5. **Berbagi pengetahuan:** setiap blocker yang teratasi menambah satu baris pelajaran di log keputusan (gejala, akar masalah, perbaikan, cara mencegah).
6. **Resource bersama tetap serial:** backup tidak boleh menjalankan uji browser live, server dev, atau tes database bersama secara paralel dengan lane lain.
7. **Batas jujur:** subagent tidak mendeteksi sendiri bahwa agent lain menganggur. Deteksi terjadi saat agent utama menerima notifikasi atau memeriksa ledger, dan ini wajib dilakukan pada setiap notifikasi selesai.

## 9. Tim Sekretariat

Sekretariat adalah peran, bukan proses otomatis. Pemegang perannya adalah agent utama, dibantu subagent pencatat bila ada paket kerja dokumentasi yang terisolasi.

| Peran | Tanggung jawab | Artefak |
|---|---|---|
| Sekretaris tim | Memeriksa ledger dan antrean pada setiap notifikasi; memicu aturan 8.1 | Log antrean |
| Pencatat ledger | Menjaga skema ledger (bagian 3) sesuai kondisi sesi aktual | Ledger kepemilikan |
| Notulis keputusan | Mencatat keputusan, alasan, alternatif yang ditolak, dan pelajaran blocker | Log keputusan |
| Pengelola antrean | Memilih subpekerjaan siap yang tidak bentrok; mencatat bila tidak ada pekerjaan aman, bukan menciptakan kartu pengisi | Antrean Ready |

**Artefak dan lokasi:** ledger, log antrean, dan log keputusan aktif tersimpan di `docs/04-implementation/TEAM_SUPPORT_LEDGER.md`. Log pengujian berukuran besar boleh berada di folder temp yang disetujui harness, dengan path lengkap dicatat di ledger. Perbarui ringkasan setiap task ditutup; bukti dalam temp dapat hilang dan tidak menggantikan ringkasan permanen.

**Siklus sekretariat per notifikasi (wajib):**

1. Baca hasil agent yang selesai; salin status ke ledger.
2. Periksa setiap sesi aktif: ada Blocked, macet diam-diam, atau menganggur?
3. Terapkan urutan penugasan ulang (8.1) dan catat sesi baru di ledger.
4. Catat keputusan dan pelajaran baru.
5. Tulis satu baris status ke pengguna: apa yang selesai, apa yang jalan, apa yang terhambat.

**Pemisahan tanggung jawab:** pencatatan sekretariat bukan persetujuan. Agent utama boleh bertugas sebagai sekretaris sekaligus integrator, tetapi identitas dan keputusan persetujuan dicatat terpisah. Larangan approver sama dengan author dan reviewer tidak independen pada bagian 3–4 tetap berlaku.
