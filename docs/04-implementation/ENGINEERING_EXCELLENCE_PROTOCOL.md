# Protokol Keunggulan Kerja ZAFIRA

**Status:** Aktif sejak 2026-10-19  
**Berlaku untuk:** agent utama, semua subagent, reviewer, dan manusia yang bekerja di repo ini.  
**Pelengkap:** `AGENT_OPERATING_MODEL.md` (struktur tim) dan `AGENTS.md` bagian 2 (Dewan HCI).

## 1. Nilai kerja yang dapat diperiksa

Nilai tanpa perilaku yang dapat diperiksa hanyalah slogan. Setiap nilai di bawah punya bukti wajib.

| Nilai | Perilaku wajib | Bukti yang diperiksa |
|---|---|---|
| Cepat | Pekerjaan independen dijalankan paralel; blocker didahulukan; tidak ada pekerjaan pengisi | Tidak ada lane menganggur saat ada pekerjaan siap |
| Tepat | Kerjakan persis yang diminta; cakupan file tertulis | Diff hanya menyentuh file dalam cakupan |
| Akurat | Setiap klaim punya sumber: kode, log, atau dokumen primer | Klaim tanpa sumber ditandai `TIDAK TERBUKTI` |
| Efisien | Pakai kode, helper, dan dependensi yang sudah ada sebelum menulis baru | Tidak ada abstraksi tanpa pemanggil |
| Solutif | Perbaiki akar masalah, bukan gejala | Ada tes yang gagal sebelum perbaikan dan lulus sesudahnya |
| Jujur | Laporkan kegagalan, batas bukti, dan hal yang tidak diperiksa | Laporan memuat bagian "tidak diperiksa" |
| Disiplin | Ikuti gate, TDD, dan aturan keamanan tanpa pengecualian diam-diam | Gate tidak pernah diturunkan tanpa keputusan tertulis |
| Kompeten | Pilih skill dan agent yang sesuai domain; reviewer berbeda dari penulis | Ledger mencatat skill dan sesi reviewer |
| Smart | Tulis hipotesis sebelum mengubah kode; ukur sebelum mengoptimalkan | Baseline tercatat sebelum perubahan |
| Pekerja keras | Selesaikan sampai bukti lengkap; jangan berhenti di "seharusnya jalan" | Evaluation Record lengkap dengan `EXIT` |
| Aktif | Agent yang selesai langsung ditugaskan ulang atau dilepas | Tidak ada agent selesai yang tidak ditindaklanjuti |

**Batas jujur:** tidak ada "1000% kemampuan". Yang bisa diwajibkan adalah upaya maksimal berbasis bukti: semua skill yang relevan dipakai, semua klaim diverifikasi, dan semua celah dilaporkan.

## 2. Dewan audit profesional

Dewan adalah **lensa audit**. Nama tokoh dipakai untuk metode dan gagasan publik mereka. Dewan bukan orang sungguhan, dan tidak ada tokoh yang menyetujui hasil kerja ini.

**Status sumber:** semua lensa di bawah adalah parafrase kerja yang **belum diverifikasi terhadap karya primer**. Jangan mengutipnya sebagai pernyataan tokoh. Saat sebuah lensa menjadi dasar keputusan keamanan, data, atau arsitektur, catat sumber primernya lebih dulu (bagian 5).

### 2.1 Interaksi, produk, dan aksesibilitas

| Tokoh | Lensa | Pertanyaan audit |
|---|---|---|
| Don Norman | Affordance, signifier, jurang eksekusi dan evaluasi | Apakah pengguna tahu apa yang bisa dilakukan dan apa hasilnya? |
| Jakob Nielsen | 10 heuristik usabilitas | Heuristik mana yang dilanggar? Seberapa parah? |
| Ben Shneiderman | 8 aturan emas, mantra pencarian informasi | Adakah umpan balik, penutupan, dan undo? |
| Alan Cooper | Desain berbasis tujuan persona, eliminasi excise | Langkah mana yang tidak bernilai bagi tujuan pengguna? |
| Douglas Engelbart | Augmentasi kemampuan manusia | Apakah alat ini memperkuat kerja pengguna, bukan sekadar mencatat? |
| Tim Brown | Desirability, feasibility, viability | Apakah ketiga lensa seimbang? |
| Steve Krug | "Don't make me think" | Apakah halaman bisa dipahami dalam pindai singkat? |
| Léonie Watson | Aksesibilitas nyata dengan pembaca layar | Apakah alur selesai hanya dengan keyboard dan pembaca layar? |
| Marty Cagan | Empat risiko produk: nilai, usabilitas, kelayakan, viabilitas bisnis | Bukti apa bahwa pelanggan butuh ini? |

### 2.2 Arsitektur, kode, dan data

| Tokoh | Lensa | Pertanyaan audit |
|---|---|---|
| Martin Fowler | Refactoring, integrasi kontinu, arsitektur evolusioner | Apakah perubahan kecil, terintegrasi, dan dapat dibalik? |
| Eric Evans | Domain-driven design, bahasa bersama, bounded context | Apakah istilah kode sama dengan istilah bisnis? |
| Kent Beck | TDD, XP; buat benar dulu, rapikan, baru percepat | Apakah ada tes merah lebih dulu? Apakah desain tetap sederhana? |
| Martin Kleppmann | Keandalan, skalabilitas, kemudahan pemeliharaan; konsistensi, isolasi, idempotensi | Apa yang terjadi pada retry, kegagalan parsial, dan permintaan paralel? |
| Michael Stonebraker | Pilih mesin data sesuai beban; ukur dengan benchmark | Apakah keputusan data didukung pengukuran, bukan asumsi? |
| Donald Knuth | Kebenaran algoritma; optimasi hanya pada titik kritis yang terukur | Apakah optimasi ini didukung profil? Apakah korektnya terbukti? |
| Leslie Lamport | Spesifikasi sebelum kode; invarian sistem terdistribusi | Apa invarian yang harus selalu benar? Di mana ia ditulis? |

### 2.3 Operasi, keandalan, dan performa

| Tokoh | Lensa | Pertanyaan audit |
|---|---|---|
| Werner Vogels | Rancang dengan asumsi komponen pasti gagal; tim pembangun ikut mengoperasikan | Bagaimana sistem berperilaku saat komponen gagal? Siapa pemiliknya? |
| Kelsey Hightower | Kesederhanaan operasional, otomasi, deploy yang dapat diulang | Bisa dijalankan ulang oleh orang lain dengan satu perintah terdokumentasi? |
| Nicole Forsgren | Metrik DORA: frekuensi deploy, lead time, change failure rate, waktu pemulihan | Apakah perubahan ini memperbaiki atau memperburuk metrik pengiriman? |
| Ben Treynor Sloss | SRE: SLO dan error budget | Apa SLO-nya? Berapa sisa error budget? |
| Charity Majors | Observabilitas: bisa menjawab pertanyaan baru dari telemetri | Jika ini rusak di produksi, apa yang kita lihat? |
| Brendan Gregg | Metodologi performa (USE method), profil dan bukti | Apakah bottleneck diukur sebelum diperbaiki? |

### 2.4 Keamanan

| Tokoh | Lensa | Pertanyaan audit |
|---|---|---|
| Bruce Schneier | Keamanan adalah proses; berpikir seperti penyerang; trade-off | Siapa penyerangnya, apa tujuannya, dan berapa biayanya bagi mereka? |
| Adam Shostack | Threat modeling empat pertanyaan | Apa yang kita bangun? Apa yang bisa salah? Apa yang kita lakukan? Sudah cukup baik? |
| Tanya Janca | Application security dalam siklus pengembangan | Kontrol keamanan mana yang ada di tiap tahap, bukan hanya di akhir? |
| Troy Hunt | Keamanan web praktis: kredensial, kebocoran data, validasi input | Apa dampaknya bila data pengguna bocor? Apakah kredensial dan input dilindungi? |

### 2.5 Web terbuka dan konteks Indonesia

| Tokoh | Lensa | Pertanyaan audit |
|---|---|---|
| Tim Berners-Lee | Web universal, standar terbuka, URL yang stabil | Apakah bekerja di perangkat dan peramban apa pun dan tidak mengunci pengguna? |
| Vint Cerf | Interoperabilitas, protokol berlapis, desain end-to-end | Apakah antarmuka antarkomponen jelas dan tidak bergantung pada implementasi? |
| Onno W. Purbo | Teknologi terbuka dan terjangkau untuk masyarakat Indonesia | Apakah berjalan baik di jaringan lambat dan perangkat murah? Apakah istilah dan alur cocok bagi pengguna Indonesia? |
| Paul Hudson | Pembelajaran bertahap dan contoh yang dapat langsung dijalankan | Apakah dokumentasi dapat diikuti pemula tanpa konteks tersembunyi? |

Catatan: Paul Hudson dikenal lewat Swift dan SwiftUI. Lensanya di sini terbatas pada kejelasan dokumentasi dan penjelasan, bukan keputusan teknis Laravel.

## 3. Gate lensa menurut jenis perubahan

Tidak semua lensa dipakai pada semua perubahan. Memakai semuanya pada tiap perubahan kecil melanggar nilai efisien. Pilih sesuai tabel; tulis lensa yang dilewati beserta alasannya. Pengecualian: perubahan UI selalu memakai ketujuh lensa Dewan HCI karena `AGENTS.md` bagian 2 mewajibkannya.

| Jenis perubahan | Lensa wajib |
|---|---|
| UI atau alur pengguna | Ketujuh lensa Dewan HCI di `AGENTS.md` bagian 2 (Norman, Nielsen, Shneiderman, Cooper, Engelbart, Brown, Krug), ditambah Watson |
| Fitur produk baru | Cagan, Brown, Cooper, Evans, Beck |
| Logika bisnis, cart, checkout, pembayaran | Evans, Beck, Kleppmann, Lamport, Schneier |
| Skema atau migrasi database | Kleppmann, Stonebraker, Fowler, Vogels |
| Auth, sesi, input pengguna | Schneier, Shostack, Janca, Hunt |
| Performa atau konkurensi | Gregg, Knuth, Kleppmann, Lamport |
| Deploy, server, CI | Hightower, Forsgren, Treynor Sloss, Majors, Vogels |
| Dokumentasi | Hudson, Purbo, Berners-Lee, Evans |
| Antarmuka antarmodul atau API | Cerf, Fowler, Evans, Berners-Lee |

## 4. Prosedur ricek dan crosscheck

**Ricek** adalah mengulang pemeriksaan oleh pihak yang sama. **Crosscheck** adalah pemeriksaan oleh pihak independen atau sumber independen. Keduanya wajib.

1. **Baseline:** catat kondisi awal dengan perintah dan hasilnya.
2. **Hipotesis:** tulis penyebab yang diduga sebelum mengubah apa pun.
3. **Perubahan terkontrol:** ubah satu hal, ukur, ulangi.
4. **Ricek:** jalankan ulang perintah verifikasi dan bandingkan dengan baseline.
5. **Crosscheck:** reviewer berbeda memeriksa diff dan bukti. Temuan reviewer diverifikasi ke kode sebelum diterapkan.
6. **Lensa dewan:** terapkan lensa wajib dari bagian 3 dan catat temuan per lensa.
7. **Catat:** isi Evaluation Record dari `AGENT_OPERATING_MODEL.md`.

## 5. Standar riset dan analisis

| Peringkat sumber | Contoh | Boleh dipakai sebagai |
|---|---|---|
| 1 | Kode sumber, log eksekusi, hasil tes di repo ini | Fakta |
| 2 | Dokumentasi resmi, spesifikasi, karya primer tokoh | Fakta atau dasar keputusan |
| 3 | Artikel teknis dari praktisi, benchmark pihak ketiga | Inferensi beralasan |
| 4 | Ringkasan sekunder, forum, memori model | Petunjuk saja; wajib diverifikasi |

Aturan:

- Riset menyebut sumber, tanggal akses, dan batas kesimpulan.
- Pisahkan: fakta terverifikasi, inferensi, asumsi, informasi belum tersedia, rekomendasi terbuka.
- Hasil riset tanpa sumber peringkat 1 atau 2 tidak boleh menjadi dasar perubahan keamanan atau data.
- Kutipan tokoh hanya dipakai jika sumber primernya dapat ditunjukkan. Selain itu, tulis parafrase gagasan.

## 6. Pemilihan skill dan agent

Memanggil semua skill pada setiap tugas memboroskan konteks dan waktu. Aturan yang berlaku:

1. Sebelum bertindak, periksa daftar skill dan panggil setiap skill yang relevan dengan tugas.
2. Skill proses didahulukan (brainstorming, systematic-debugging, TDD, writing-plans), baru skill implementasi.
3. Rumpun skill yang dipertimbangkan: superpowers, mattpocok, ECC, impeccable, ui-ux-pro-max, taste, accessibility, security, database, laravel.
4. Catat skill yang dipakai di ledger tugas. Skill relevan yang dilewati wajib disertai alasan.
5. Pekerjaan independen didelegasikan ke subagent sesuai domain (backend, frontend, database, security, QA, reviewer). Reviewer tidak boleh sama dengan penulis.
6. Agent yang selesai langsung ditindaklanjuti: tugaskan ulang, tutup, atau catat alasan tidak ada pekerjaan siap.

## 7. Aturan kejujuran

- Klaim lulus berbasis perintah wajib memuat `EXIT=0` dari perintah yang benar-benar dijalankan.
- Klaim lulus berbasis review manual (audit HCI, aksesibilitas, dokumen) wajib memuat tanggal, kriteria, temuan, dan sesi reviewer. Jangan mengarang `EXIT` untuk pemeriksaan yang tidak menjalankan perintah.
- Dilarang menyajikan hasil agent lain sebagai hasil verifikasi sendiri. Tulis "dilaporkan lane X, belum saya ulang".
- Setiap laporan memuat bagian: **tidak diperiksa** dan **risiko tersisa**.
- Kegagalan dan kekeliruan sendiri dilaporkan, bukan disembunyikan.
- Jika bukti tidak ada, tulis `TIDAK TERBUKTI`.

## 8. Anti-pola yang dilarang

| Anti-pola | Akibat |
|---|---|
| Agent soup: banyak agent tanpa owner | Benturan file, hasil tidak bisa digabung |
| Board theater: kartu tanpa kriteria penerimaan | Status tidak berarti |
| Self-review: penulis menilai karyanya sendiri | Cacat lolos |
| Gate weakening: tes dilonggarkan agar hijau | Kualitas turun diam-diam |
| Dekorasi dewan: menyebut nama tokoh tanpa pertanyaan audit | Audit kosong |
| Optimasi prematur tanpa profil | Kompleksitas tanpa manfaat |
| Klaim dari memori model tanpa sumber | Fakta palsu |

## 9. Penerapan

Protokol ini berlaku mulai sekarang untuk semua task R-11 dan task berikutnya. Setiap subagent baru menerima tautan ke dokumen ini di prompt penugasannya.
