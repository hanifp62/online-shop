# Riset mendalam chatbot pada e-commerce (literatur + reverse engineering REA)

Tanggal riset dan seluruh akses URL: **2026-10-30**. Status: riset sekunder (web) dan observasi teknis storefront publik memakai REA, tanpa login, tanpa mengirim pesan ke chatbot mana pun. **Bukan** uji pengguna, A/B test, atau audit aksesibilitas widget pihak ketiga.

Keyakinan keseluruhan: **sedang**. Bukti kausal kuat hanya ada untuk sedikit klaim (satu RCT pra-cetak, satu field experiment terbitan jurnal). Sebagian besar angka industri adalah klaim vendor.

## Daftar isi

0. [Ringkasan eksekutif](#0-ringkasan-eksekutif)
1. [Metode, label, dan batas](#1-metode-label-dan-batas)
2. [Taksonomi dan evolusi](#2-taksonomi-dan-evolusi)
3. [Bukti dampak bisnis](#3-bukti-dampak-bisnis)
4. [Sikap pengguna dan mode kegagalan](#4-sikap-pengguna-dan-mode-kegagalan)
5. [Luxury dan layanan high-touch](#5-luxury-dan-layanan-high-touch)
6. [Indonesia dan WhatsApp](#6-indonesia-dan-whatsapp)
7. [Lanskap industri dan agentic commerce](#7-lanskap-industri-dan-agentic-commerce)
8. [Hasil reverse engineering REA pada 10 storefront](#8-hasil-reverse-engineering-rea-pada-10-storefront)
9. [Arsitektur referensi dan keamanan](#9-arsitektur-referensi-dan-keamanan)
10. [Regulasi](#10-regulasi)
11. [Pedoman UX/HCI dan pemetaan ke dewan HCI ZAFIRA](#11-pedoman-uxhci-dan-pemetaan-ke-dewan-hci-zafira)
12. [Implikasi untuk ZAFIRA](#12-implikasi-untuk-zafira)
13. [Kesenjangan dan pertanyaan terbuka](#13-kesenjangan-dan-pertanyaan-terbuka)
14. [Daftar sumber](#14-daftar-sumber)

---

## 0. Ringkasan eksekutif

1. **Chatbot bisa menaikkan konversi, tetapi buktinya sempit.** Satu RCT di platform e-commerce lintas negara (44.614 konsumen) menemukan chatbot pra-penjualan menaikkan konversi 21,7% dan penjualan 16,3% dibanding *tidak ada layanan*, bukan dibanding manusia [W1]. Angka lain (Amazon Rufus, Klarna, Salesforce) adalah klaim vendor.
2. **Pengguna menerima bot untuk riset produk, bukan untuk masalah.** Baymard (2.273 pembelanja AS, 2026): AI dipilih untuk membandingkan fitur (63%) dan cek stok (59%), sedangkan manusia dipilih untuk barang rusak/salah (69%) dan pesanan terlambat (67%) [U7]. Gartner: 64% pelanggan lebih suka perusahaan tidak memakai AI untuk layanan; kekhawatiran utama adalah sulit menjangkau manusia [W10].
3. **Kegagalan berbiaya hukum dan reputasi.** Perusahaan bertanggung jawab atas jawaban bot (Moffatt v. Air Canada 2024) [W14]. Bot bisa dimanipulasi (Chevrolet "mobil $1", DPD). Klarna menarik kembali sebagian otomatisasi pada 2025 karena kualitas turun [W9].
4. **Luxury: AI sebagai penguat advisor manusia, bukan pengganti.** BCG: 62% konsumen luxury menilai hilangnya sentuhan manusia sebagai risiko AI terbesar [W19]. Polanya: asisten virtual → handoff ke advisor manusia dengan konteks penuh.
5. **Indonesia adalah pasar chat-first.** WhatsApp menjadi kanal utama UMKM (69% dari yang berjualan online) [W22]. Meta meluncurkan Business Agent di WhatsApp Indonesia (Agustus 2026) dengan penjadwalan janji temu dan handoff ke manusia [I8]. Harga WhatsApp Business Platform berubah 1 Okt 2026 [I4].
6. **Observasi REA (10 storefront):** tidak ada storefront luxury yang teramati memakai chatbot AI di beranda. Hermès hanya menyediakan "Contact us". Pola Indonesia yang dominan adalah **WhatsApp + help center**. Buttonscarves, brand premium hijab Indonesia yang paling dekat dengan positioning ZAFIRA, memasang **dua titik masuk terpisah**: launcher asisten AI (kemungkinan Microsoft Clarity Brand Agents) dan tombol WhatsApp. Buttonscarves juga menautkan "Ask ChatGPT/Claude/Perplexity" serta virtual try-on.
7. **Agentic commerce (ACP, AP2, UCP) masih bergejolak.** OpenAI dilaporkan mengurangi Instant Checkout pada Maret 2026. Langkah aman: data produk terstruktur yang akurat. ZAFIRA **sudah punya** JSON-LD `Product`/`Offer`.
8. **Rekomendasi ZAFIRA:** jangan mulai dari chatbot LLM umum. Urutannya:
   1. Satukan pintu "Bantuan & Concierge" yang konsisten.
   2. Tambahkan asisten terpandu deterministik dengan handoff ke `ConciergeRequest` yang sudah ada.
   3. Baru kemudian asisten RAG terbatas dengan disclosure AI, sitasi sumber, dan tanpa aksi tulis tanpa konfirmasi.

   Rinciannya ada di §12. Semua usulan berstatus **kandidat**, belum menjadi requirement.

---

## 1. Metode, label, dan batas

### 1.1 Dua skill yang dipakai

| Skill | Peran | Keluaran |
|---|---|---|
| `deep-research` | Riset literatur multi-sumber. Tool Firecrawl/Exa tidak tersedia di sesi ini, jadi WebSearch/WebFetch dipakai. Tiga agen paralel menangani (a) bukti dampak, (b) lanskap industri dan arsitektur, (c) UX/HCI dan aksesibilitas. | ±85 sumber unik; sumber yang dibaca penuh ditandai `[F]` di §14 |
| `reverse-engineer-anything` (REA, `rea-agents@6.7.0`) | Observasi teknis storefront nyata: `list_browser_targets` (MCP) dan `capture-browser-scenario` (CLI) dengan Chrome headless 156. Diambil inventaris jaringan (origin, script, WebSocket), pohon aksesibilitas, DOM, dan screenshot. | 14 Evidence ID di [evidence-chatbot-rea-2026-10-30](../05-quality/evidence-chatbot-rea-2026-10-30/) |

Alasan memakai REA: klaim "situs X memakai vendor chat Y" atau "widget memuat via WebSocket" tidak bisa dijawab dari literatur dan memerlukan observasi runtime.

Batas etika yang dipegang selama observasi:
- tanpa login;
- tanpa mengirim pesan ke bot;
- tanpa melewati proteksi anti-bot;
- satu sesi per situs.

### 1.2 Label

Mengikuti [RESEARCH_METHODOLOGY.md](RESEARCH_METHODOLOGY.md), ditambah satu label:

| Label | Makna |
|---|---|
| FACT | Sumber primer, standar, peer-review, atau kode ZAFIRA yang dibaca langsung |
| THIRD-PARTY METRIC | Survei/estimasi pihak independen; periode dan sampel disebut |
| **VENDOR CLAIM** | Angka yang dilaporkan perusahaan tentang produknya sendiri; tidak diaudit independen |
| OBSERVATION | Benar-benar dikembalikan REA pada URL dan waktu tertentu; tidak membuktikan perilaku di luar jendela observasi |
| INTERPRETATION | Kesimpulan analitis |
| RECOMMENDATION | Usulan untuk ZAFIRA; bukan fitur yang sudah ada |

Tanda tambahan:
- ⚑ = klaim satu sumber.
- `[F]` = halaman dibaca penuh. `[S]` = hanya cuplikan hasil pencarian, sehingga keyakinannya lebih rendah.

### 1.3 Pemeriksaan prompt injection

Tidak ada halaman yang dibaca berisi instruksi yang ditujukan ke agen AI.

Satu temuan relevan: Buttonscarves menaruh tautan `Ask ChatGPT/Claude/Perplexity` berisi prompt pemasaran yang sudah diisi ("tell me why buttonscarves product is a great fit for me"). Ini dicatat sebagai **data observasi** (pola *AI-referral marketing*), bukan instruksi, dan tidak diikuti.

---

## 2. Taksonomi dan evolusi

[INTERPRETATION, contoh bersumber]

| Generasi | Cara kerja | Contoh |
|---|---|---|
| G1 — Bot aturan/menu | Pohon keputusan, tombol, kata kunci; jawaban templat | Bot WhatsApp format tetap (Kata.ai) [I-ka] |
| G2 — Bot intent NLU | Klasifikasi intent → slot → backend; dominan untuk layanan | Tokopedia "Tanya" (skripsi Telkom Univ) ⚑; BRI "Sabrina" di WhatsApp |
| G3 — Asisten LLM/RAG | Penemuan produk percakapan yang berpijak pada katalog, ulasan, dan kebijakan | Zalando Assistant (Okt 2024, 25 pasar) [I7]; Lazada LazzieChat (Mei 2023, Azure OpenAI, termasuk ID) [I15]; Walmart Sparky (Jun 2025); IKEA GPT (Feb 2024) |
| G4 — Agentic commerce | Agen bertindak: memesan, memantau harga lalu membeli otomatis, menangani purnajual, membayar dengan token | Amazon Rufus "Buy For Me" [I1]; Qwen × Taobao, auto-order saat harga target tercapai [I3]; Google AI Mode agentic checkout; Perplexity Instant Buy |
| Cabang — copilot penjual / agen yang di-host platform | AI untuk operator toko, atau agen yang di-host platform atas nama merchant | Shopify Sidekick; Meta Business Agent di WhatsApp (ID, Agu 2026) [I8]; Microsoft Clarity Brand Agents (beta, Shopify) [R-MS] |

[INTERPRETATION] Generasi ini **bertumpuk, tidak saling menggantikan**. Sistem produksi yang matang memadukan alur deterministik untuk langkah berisiko (pembayaran, status pesanan, pembatalan) dengan LLM untuk penemuan produk yang terbuka.

---

## 3. Bukti dampak bisnis

| Klaim | Label | Desain / batas | Sumber |
|---|---|---|---|
| Chatbot pra-penjualan menaikkan konversi **+21,7%** (+1,31 pp) dan penjualan **+16,3%**; AOV −4,5% (tidak signifikan); retur dan rating tidak memburuk; pembeli kurang berpengalaman paling diuntungkan | FACT (RCT, pra-cetak, belum peer-review) ⚑ | 44.614 konsumen, Sep–Okt 2023, satu platform lintas negara. Kontrolnya "agen tidak tersedia", jadi ini bot vs **tanpa layanan**, bukan vs manusia | [W1] Fang et al., arXiv 2510.12049 `[F]`. Crosscheck 2026-10-30: abstrak v6 mengonfirmasi efek "hingga 16,3%" lewat konversi. Angka per-lengan (n, +21,7%, kontrol) berasal dari tabel HTML v5 |
| Mengungkap bahwa lawan bicara adalah bot **sebelum** percakapan menurunkan pembelian >79,7%; bot yang tak diungkap setara pekerja mahir | FACT (field experiment, *Marketing Science* 2019) | >6.200 pelanggan; **panggilan suara** penjualan jasa keuangan, bukan chat e-commerce | [W3] Luo et al. 2019 `[F]` |
| Rufus: 300 juta+ pengguna; "hampir $12 miliar incremental annualized sales" 2025 | VENDOR CLAIM | Model atribusi milik Amazon sendiri | [I1] Amazon Q4 2025 `[F]` |
| Klarna: 2,3 juta percakapan bulan pertama, setara 700 agen, waktu penyelesaian 11 → 2 menit | VENDOR CLAIM | Proyeksi satu bulan; tanpa audit CSAT | [W8] `[F]` |
| Klarna 2025: CEO mengakui kualitas layanan turun, kembali merekrut manusia | FACT (pernyataan CEO di media) | — | [W9] `[F]` |
| Trafik ritel AS dari alat GenAI: holiday 2024 +1.300% YoY dengan konversi 9% *lebih rendah*; holiday 2025 konversi 31% *lebih tinggi* | THIRD-PARTY METRIC | **Trafik rujukan dari luar situs**, bukan chatbot di situs; observasional; data 2025 dari laporan sekunder | [W5] `[F]`, [W6] `[F]` |
| AI dan agen "memengaruhi" $67 miliar (20%) pesanan global saat Cyber Week 2025 | VENDOR CLAIM | "Influenced" tidak didefinisikan | [W7] `[F]` |
| Hanya 20% pemimpin layanan yang mengurangi headcount karena AI (Okt 2025); 50% organisasi yang berencana memangkas akan membatalkannya pada 2027 | THIRD-PARTY METRIC / prediksi | n=321 | [W11] `[S]` |

[INTERPRETATION] Bukti terkuat menunjukkan nilai chatbot terbesar ada **ketika alternatifnya adalah tidak ada layanan sama sekali**: di luar jam kerja, saat volume tinggi, atau untuk pembeli baru. Tidak ada bukti independen bahwa bot mengalahkan advisor manusia yang tersedia. **Biaya per kontak** yang diaudit independen tidak ditemukan.

---

## 4. Sikap pengguna dan mode kegagalan

### 4.1 Sikap pengguna

- [THIRD-PARTY METRIC — Baymard, survei 2.273 pembelanja AS, Jun 2026, terbit 2026-10-27]
  - AI dipilih untuk: membandingkan fitur 63%, cek stok 59%, rekomendasi personal 56%.
  - Manusia dipilih untuk: barang rusak/salah 69%, pesanan terlambat/hilang 67%, kesalahan tagihan/pengiriman 66%.
  - [U7] `[F]`
- [THIRD-PARTY METRIC — Gartner, n=5.728, Des 2023]
  - 64% lebih suka perusahaan tidak memakai AI untuk layanan; 53% mempertimbangkan pindah ke pesaing.
  - Kekhawatiran utama: 60% takut makin sulit menjangkau manusia.
  - [W10] `[S]`; halaman Gartner mengembalikan 403, angka konsisten di beberapa media.
- [THIRD-PARTY METRIC — Qualtrics, n>20.000 di 14 negara, Q3 2025]
  - Hampir 1 dari 5 pengguna AI layanan pelanggan tidak mendapat manfaat; ini hampir 4× tingkat kegagalan AI untuk tugas lain.
  - 50% takut terhalang dari manusia.
  - [W12] `[F]`
- [FACT — Crolic et al., *Journal of Marketing* 2022, ±35.000 sesi chat + eksperimen]
  - Pada pelanggan yang **marah**, bot yang mirip manusia menurunkan kepuasan dan niat beli karena ekspektasinya dilanggar.
  - Penawarnya: turunkan ekspektasi sejak awal dan kurangi persona.
  - [W4] `[F]`
- [FACT — NN/g 2018, kualitatif n=8]
  - Pengguna ingin diberi tahu bahwa lawan bicaranya bot.
  - Bot linear gagal ketika pengguna keluar dari skrip.
  - Jalan keluar ke manusia dihargai.
  - [U1] `[F]`

### 4.2 Insiden

| Kasus | Apa yang terjadi | Pelajaran | Sumber |
|---|---|---|---|
| Moffatt v. Air Canada (BCCRT, 14 Feb 2024) | Bot salah menjelaskan kebijakan tarif duka. Tribunal menolak argumen bahwa bot adalah "entitas terpisah" | Perusahaan terikat pada ucapan bot; jawaban kebijakan wajib berpijak pada sumber | [W14] `[F]` |
| Chevrolet Watsonville (Des 2023) | Prompt injection membuat bot "setuju" menjual Tahoe seharga $1 | Bot tidak boleh punya wewenang harga atau komitmen | [W16] `[S]` |
| DPD UK (Jan 2024) | Setelah pembaruan sistem, bot memaki dan menyebut DPD "perusahaan terburuk" | Perlu uji regresi dan red-team setiap rilis | [W15] `[S]` |
| Klarna (2024 → 2025) | Otomatisasi agresif, lalu ditarik sebagian | Selalu sediakan jalur manusia | [W8], [W9] |

---

## 5. Luxury dan layanan high-touch

- [FACT — Chung et al. 2020, *J. Business Research* 117]
  - Model lima dimensi e-service chatbot merek luxury: interaksi, hiburan, tren, kustomisasi, pemecahan masalah.
  - Semuanya terkait dengan kepuasan.
  - Ukuran sampel tidak terverifikasi (paywall). [W18] `[F]` abstrak
- [FACT, cuplikan ⚑] Studi lab n=116: asisten penjual manusia menghasilkan imersi dan kebahagiaan lebih tinggi daripada robot. [W-R] `[S]`
- [FACT, cuplikan ⚑] Konsumen lebih suka asisten virtual saat **diskon**, tetapi lebih suka manusia untuk luxury **harga penuh** (dimediasi *face consciousness*). [W-Su] `[S]`
- [THIRD-PARTY METRIC — BCG 2025, sampel tidak diungkap]
  - 56% tidak puas dengan pengalaman belanja luxury; 64% pembelian terakhir terjadi di toko fisik.
  - **62%** menyebut hilangnya sentuhan manusia sebagai risiko AI terbesar.
  - Keluhan yang muncul: harus mengisi ulang data, dan advisor tidak mengenal preferensi pelanggan.
  - Model yang direkomendasikan: asisten virtual → **handoff ke advisor manusia dengan konteks penuh**; balasan dirancang AI lalu disunting advisor.
  - [W19] `[F]`
- [VENDOR CLAIM — Salesforce/Gucci]
  - GenAI menyusun draf balasan untuk ±600 client advisor melalui WhatsApp/SMS/WeChat; advisor mengirim, mengedit, atau menulis ulang.
  - Dilaporkan efisiensi +30% saat pilot. [W21] `[S]`
- [OBSERVATION — REA, §8] Hermès, satu-satunya maison luxury yang berhasil diamati, tidak memuat vendor chat di beranda. Jalurnya "Contact us" dan "Made to measure". Mytheresa memblokir observasi.

[INTERPRETATION] Dalam luxury, chatbot yang tampil sebagai "pengganti advisor" berisiko merusak lapisan *reflective* (prestise). Pola yang didukung bukti adalah **AI di belakang layar untuk advisor** dan **AI di depan layar untuk riset produk**, keduanya dengan jalan cepat ke manusia.

---

## 6. Indonesia dan WhatsApp

- [THIRD-PARTY METRIC — Mandiri Business Survey 2024, n=523 UMKM] 69% UMKM yang berjualan online memakai aplikasi pesan sebagai kanal utama. [W22] `[F]`
- [VENDOR CLAIM — Kantar untuk Meta, op-ed Jakarta Post Okt 2024]
  - 87% orang Indonesia lebih suka mengirim pesan untuk menghubungi bisnis.
  - 68% frustrasi bila bisnis tidak menyediakan kanal pesan.
  - [W23] `[F]`
- [FACT, berita — Katadata 11 Agu 2026] Meta Business Agent di WhatsApp Indonesia:
  - Tier UMKM dapat dilatih dengan info bisnis, merekomendasikan katalog, **menjadwalkan janji temu**, dan **menyerahkan ke manusia**.
  - Tier enterprise terintegrasi dengan Shopify, Zendesk, dan Shopee.
  - Angka kinerja dalam artikel adalah VENDOR CLAIM. [I8] `[F]`
- [FACT — halaman harga WhatsApp Business Platform, dibaca 2026-10-30]
  - Penagihan per pesan template terkirim sejak 1 Jul 2025; Indonesia adalah pasar tersendiri dalam IDR.
  - Mulai **1 Okt 2026**: kuota gratis 1.000 *service message* per nomor per bulan, dan pesan utility di dalam jendela layanan tidak lagi gratis.
  - Meta Business Agent dikenai $2 per 1 juta token.
  - [I4] `[F]` — ⚠ halaman tidak menampilkan tanggal pembaruan; cek ulang sebelum memutuskan.
- [FACT — studi akademik Indonesia, survei PLS-SEM, **bukan kausal**]
  - Afrina et al. 2025 (*Jurnal Sisfokom*): penggunaan chatbot berpengaruh pada kepuasan di Shopee, tetapi tidak di Lazada. [W24] `[F]`
  - Lita & Tjhin 2026 (n=403): responsivitas dan kemudahan menaikkan kepuasan; risiko yang dirasakan menurunkannya. [W25] `[F]`
- [OBSERVATION — REA, §8] Sociolla, Erigo, dan Buttonscarves menampilkan nomor/tautan WhatsApp. Erigo dan Buttonscarves memakai aplikasi tombol WhatsApp di Shopify.

[INTERPRETATION] Di Indonesia, "chatbot" sering berarti **WhatsApp**, bukan widget di situs. Ini sejalan dengan [D-06](ZAFIRA_DIFFERENTIATION.md#d-06--kanal-whatsapp-untuk-concierge-investigate). Kini ada bukti tambahan (Meta Business Agent dengan penjadwalan janji temu), tetapi juga biaya baru (harga 1 Okt 2026).

---

## 7. Lanskap industri dan agentic commerce

- **Protokol agentic** [campuran FACT/THIRD-PARTY]:
  - **ACP** (OpenAI + Stripe, Sep 2025): Shared Payment Token yang dibatasi per merchant dan total keranjang; merchant tetap memegang fulfilment dan retur. [I2] `[F]`
  - **AP2** (Google, Sep 2025): "mandate" bertanda tangan sebagai bukti otorisasi. [I17] `[S]`
  - **UCP** (Google + Shopify, Etsy, Wayfair, Target, Walmart; Jan 2026). [I19] `[S]`
  - Visa Intelligent Commerce / Trusted Agent Protocol; Mastercard Agent Pay. [I21] `[S]`
- **Ketidakstabilan:** beberapa media dagang melaporkan OpenAI mengurangi Instant Checkout pada Maret 2026 karena adopsi rendah dan beralih ke penemuan produk plus aplikasi merchant. **Tidak ada pernyataan primer OpenAI yang ditemukan.** [I20] `[S]`, THIRD-PARTY
- **Shopify Catalog / Agentic Storefronts** (Winter '26) menyindikasikan produk merchant ke ChatGPT, Perplexity, dan Copilot. [I16] `[S]`
- **Alibaba (11 Mei 2026):** Qwen terhubung ke 4 miliar+ produk Taobao/Tmall melalui "skills library" untuk pesanan, logistik, dan purnajual, termasuk pemantau harga 30 hari yang memesan otomatis. [I3] `[F]`

[INTERPRETATION] Bagi merchant kecil-menengah, **data produk yang akurat dan dapat dibaca mesin** (atribut, harga, stok, kebijakan) adalah investasi yang tetap berguna apa pun protokol yang menang. Integrasi checkout agentic sebaiknya menunggu standarnya stabil.

[FACT — kode ZAFIRA] Halaman produk sudah memancarkan JSON-LD schema.org `Product`/`Offer` dengan status stok yang tidak usang ([StorefrontController.php:82-113](../../app/Http/Controllers/StorefrontController.php#L82-L113)).

---

## 8. Hasil reverse engineering REA pada 10 storefront

**Protokol**
- `rea-agents@6.7.0 capture-browser-scenario`, Chrome headless 156, viewport 1366×900, locale `id-ID`, zona waktu Asia/Jakarta, penyimpanan kosong.
- Satu aksi: tunggu 12 detik. Yang direkam: event jaringan, WebSocket, frame, page-error, pohon aksesibilitas.
- Vendor dideteksi dengan pencocokan pola URL terhadap ±40 vendor chat ([summarize.py](../05-quality/evidence-chatbot-rea-2026-10-30/summarize.py)).
- Ringkasan lengkap: [summary.txt](../05-quality/evidence-chatbot-rea-2026-10-30/summary.txt).

| Situs | Status | Request / origin | Kanal bantuan teramati | Vendor chat terdeteksi | Evidence |
|---|---|---|---|---|---|
| Sociolla (ID, beauty) | SUCCESS | 160 / 36 | Help Center, Contact us, 2 nomor WhatsApp di footer | Tidak ada widget chat | `ev_2805d660…` |
| Erigo (ID, fashion, Shopify) | SUCCESS | 461 / 39 | "Hubungi Kami", bagian BANTUAN | **Shopify chat web component** (telemetri); aplikasi WhatsApp tabs (`whatschat.shopiapps.in`) | `ev_9ad4c382…` |
| Buttonscarves (ID, premium hijab, Shopify) | SUCCESS | 867 / 76 | Contact Us; tombol WhatsApp `wa.me` (kanan bawah); launcher AI ✦ (kiri bawah) | `#ads-agent-host` + script `adsagentclient…azurefd.net`; Fast Simon (autocomplete, visual discovery, container `fast-simon-chatbot` kosong); **OpenAI pixel** (`bzr.openai.com`); Auglio virtual try-on (face-mesh WASM); tautan "Ask ChatGPT/Claude/Perplexity" | `ev_54380038…`, `ev_b577cfd7…` |
| Zalora ID | BLOCKED (dokumen hanya iframe; PerimeterX/captcha) | 191 / 39 | — | NOT TESTED | `ev_b71ad406…` |
| Blibli | BLOCKED (verifikasi Cloudflare) | 36 / 5 | Tautan "hubungi customer service" | NOT TESTED | `ev_ffdb0516…` |
| Gymshark (global) | SUCCESS | 252 / 48 | Bagian Help | **Intercom** (`widget.intercom.io`); Algolia recommendations | `ev_fb55bebf…` |
| Allbirds (global, Shopify) | SUCCESS | 586 / 39 | help@ email, FAQ/Contact | Script `adsagentclient…azurefd.net` (sama dengan Buttonscarves); tanpa vendor chat klasik | `ev_361ccb67…` |
| Hermès (luxury) | SUCCESS | 132 / 16 | Customer Service: Contact us, Made to measure, Maintenance and repair | Tidak ada widget chat | `ev_25673816…` |
| Mytheresa (luxury) | BLOCKED (halaman "BOT" reference) | 9 / 2 | — | NOT TESTED | `ev_e75b81fc…` |
| Sephora US | BLOCKED (HTTP 403 Akamai) | 2 / 1 | — | NOT TESTED | `ev_66472155…` |

### Temuan

1. **[OBSERVATION]** Pada empat storefront yang berhasil diamati dan memiliki sinyal chat (Erigo, Buttonscarves, Gymshark, Allbirds), **tidak ada koneksi WebSocket yang terbuka selama 12 detik pertama**. Widget dimuat sebagai script statis, dan koneksi realtime baru terbuka saat dibuka (lazy). [INTERPRETATION] Pola ini menjaga performa halaman. ZAFIRA sebaiknya meniru: jangan memuat SDK chat sebelum pengguna meminta.
2. **[OBSERVATION]** Buttonscarves menampilkan **dua titik masuk bantuan yang terpisah** di sudut berlawanan (screenshot 2026-10-30). [INTERPRETATION] Ini bertentangan dengan pedoman NN/g 2026 G1 (gabungkan chat AI dengan jalur manusia dalam satu pintu) [U2], dan menambah beban keputusan (Krug).
3. **[INFERENCE, sedang]** Launcher ✦ Buttonscarves kemungkinan besar **Microsoft Clarity Brand Agents**. Dasarnya: host `#ads-agent-host`, script dari `adsagentclient…azurefd.net`, Clarity yang aktif, dan dokumentasi Microsoft bahwa Brand Agents adalah beta khusus Shopify [R-MS] `[S]`. Script yang sama muncul di Allbirds. **Belum terverifikasi:** klik ke `#ads-agent-host button` timeout (`ev_90b00af8…`), kemungkinan karena shadow DOM tertutup.
4. **[OBSERVATION]** Container `#fast-simon-chatbot` ada di DOM tetapi kosong. Fast Simon dipakai untuk autocomplete dan visual discovery. [INTERPRETATION] Merchant dapat memasang beberapa vendor AI yang tumpang tindih; ini adalah biaya dan risiko privasi yang perlu diaudit.
5. **[OBSERVATION]** Gymshark memuat Intercom, tetapi launcher-nya tertutup modal region dan banner cookie. Klik tombol "Open Intercom Messenger" timeout (`ev_33bc06fd…`). [INTERPRETATION] Lapisan overlay (cookie, region, promo) bersaing dengan pintu bantuan, persis masalah yang dicatat Baymard [U6]. Sesuai WCAG 2.4.11, launcher tidak boleh tertutup.
6. **[OBSERVATION]** Buttonscarves menautkan "Ask ChatGPT/Claude/Perplexity" dengan prompt pemasaran yang sudah diisi, dan memasang OpenAI pixel. [INTERPRETATION] Merchant Indonesia sudah mengoptimalkan **rujukan dari asisten AI eksternal**, bukan hanya chatbot di situs, sejalan dengan data trafik Adobe [W5][W6].
7. **[INTERPRETATION]** 4 dari 10 situs memblokir browser otomatis. Dampaknya bagi strategi agentic commerce: **agen AI yang sah juga bisa terblokir** oleh proteksi anti-bot. Merchant perlu kebijakan eksplisit tentang agen mana yang diizinkan.

**Batas:**
- Satu titik waktu, beranda saja, tanpa halaman produk atau checkout.
- Lokasi jaringan Indonesia dengan header headless. Tidak ada perbandingan A/B atau geolokasi.
- Absennya vendor di beranda tidak membuktikan absen di halaman bantuan atau aplikasi mobile.
- Isi percakapan bot tidak diuji.

---

## 9. Arsitektur referensi dan keamanan

[INTERPRETATION, jangkar bersumber]

```
Kanal (widget web · WhatsApp · email)
        │
Orkestrator ── kebijakan sistem, router intent, alur deterministik untuk langkah berisiko
   ├── RAG: katalog (harga/stok live), FAQ, kebijakan retur/pengiriman, panduan perawatan → simpan sitasi
   ├── Tools (least privilege): cari produk · status pesanan (wajib login) · keranjang (konfirmasi)
   │                             · buat janji concierge · cek stok
   ├── Handoff manusia: confidence rendah / sentimen negatif / VIP / pengecualian kebijakan → konteks penuh
   └── Guardrail + evaluasi + observability
```

**Pemetaan OWASP Top 10 untuk LLM 2025** [I5] `[F]`:

| Risiko | Wujud di e-commerce | Mitigasi |
|---|---|---|
| LLM01 Prompt Injection | Instruksi tersembunyi di ulasan, catatan pelanggan, atau UGC yang masuk ke RAG; kasus "$1" | Pisahkan data dari instruksi; jangan beri bot wewenang harga |
| LLM02 Sensitive Info Disclosure | Membocorkan pesanan orang lain | Tool pesanan hanya untuk sesi terautentikasi dan memeriksa ownership (selaras FR-204) |
| LLM06 Excessive Agency | Mengubah pesanan atau memberi diskon tanpa konfirmasi | Validasi argumen di server; konfirmasi eksplisit untuk setiap aksi tulis |
| LLM07 System Prompt Leakage | Membocorkan aturan internal | Tidak ada rahasia di prompt |
| LLM09 Misinformation | Harga atau kebijakan halusinasi (Air Canada) | Jawaban faktual hanya dari data live + tautan sumber |
| LLM10 Unbounded Consumption | Penyalahgunaan token atau biaya | Rate limit (sudah ada pola `throttle:concierge` 3/jam di [web.php:89](../../routes/web.php#L89)) |

**Evaluasi:**
- Set tanya-jawab emas (katalog, kebijakan).
- Uji groundedness dan sitasi.
- Metrik: resolution rate, handoff rate, CSAT.
- Prompt red-team setiap rilis (pelajaran DPD).

**Biaya:**
- Model kecil untuk routing dan FAQ.
- Model besar hanya untuk saran kompleks (contoh Zalando: GPT-4o-mini, trafik 12× dengan biaya serupa — VENDOR CLAIM [I11]).
- Vendor seperti Intercom Fin (≈$0,99 per resolusi), Zendesk (outcome-based), dan Gorgias: angka dari pihak ketiga, **cek halaman harga resmi**.

---

## 10. Regulasi

| Aturan | Status per 2026-10-30 | Implikasi chatbot ZAFIRA | Label |
|---|---|---|---|
| UU 27/2022 PDP | Berlaku penuh sejak Okt 2024 | Transkrip, nomor kontak, data pesanan, dan **ukuran tubuh** adalah data pribadi. Diperlukan dasar hukum, pemberitahuan, retensi, DPA dengan vendor LLM/WhatsApp, dan aturan transfer lintas negara bila memakai API LLM luar negeri | FACT (UU); INTERPRETATION (implikasi) |
| PP 33/2026 (pelaksana PDP) | Dilaporkan diundangkan 16 Jul 2026, **berlaku 16 Jan 2027**, denda administratif hingga 2% pendapatan | Siapkan kepatuhan sebelum Jan 2027 | THIRD-PARTY (beberapa sumber sekunder; teks resmi belum dicek) [I27] |
| Lembaga PDP | Belum operasional per Sep 2026 | — | THIRD-PARTY [I28] |
| SE Menkominfo 9/2023 Etika AI | Berlaku, tidak mengikat | Nilai transparansi dan akuntabilitas, mendukung disclosure AI | FACT [I30] `[S]` |
| Dua rancangan Perpres AI (Peta Jalan 2026–2030, Etika AI, tiga tingkat risiko) | Per 3 Sep 2026 "menunggu tanda tangan"; **status penandatanganan belum terkonfirmasi** | Pantau JDIH Setneg | THIRD-PARTY [I29] |
| EU AI Act Pasal 50(1) | Berlaku 2 Agu 2026 | Hanya relevan bila melayani pengguna di UE. Wajib memberi tahu bahwa lawan bicara AI paling lambat pada interaksi pertama | FACT (mirror tidak resmi; verifikasi ke EUR-Lex) [I6] `[F]` |
| UU 8/1999 Perlindungan Konsumen | Berlaku | Klaim produk atau harga yang menyesatkan dari bot dapat menjadi tanggung jawab pelaku usaha; analogi dengan Moffatt | INTERPRETATION — **perlu konsultasi hukum** |

---

## 11. Pedoman UX/HCI dan pemetaan ke dewan HCI ZAFIRA

Sumber utama:
- NN/g 2018 [U1] dan 2026 "10 Guidelines" [U2]
- Baymard 2019 dan 2026 [U6][U7]
- Microsoft HAX 18 guidelines [U8]
- Apple HIG Generative AI (2026-06-08) [U11]
- Google PAIR [U12]
- IBM Carbon AI label [U13]
- WCAG 2.2 [U16–U18]
- ARIA `role="log"` [U19]
- APG Dialog [U21]

| Tokoh (AGENTS.md §2) | Prinsip | Penerapan pada chatbot |
|---|---|---|
| **Don Norman** | Signifier, conceptual model, gulf of evaluation, emotional design | Launcher berlabel "Bantuan & Concierge" (bukan ikon misterius ✦). Sapaan pembuka menyatakan kemampuan dan batasnya. Status "sedang mencari…" yang spesifik. Nada atelier yang tenang, tanpa emoji (emoji menurunkan status merek luxury aspiratif ⚑ [W-Li]) |
| **Jakob Nielsen** | Visibility of status, user control, error recovery, help | Status menggunakan WCAG 4.1.3. Tutup dengan Esc, tombol, dan klik backdrop. Fallback yang menyebut apa yang tidak dipahami beserta langkah berikutnya, tanpa kode galat |
| **Ben Shneiderman** | Closure, reversal, internal locus of control | Handoff menghasilkan nomor referensi dan perkiraan waktu balasan. Tambah ke keranjang dari chat bisa di-undo. Tidak ada aksi tanpa konfirmasi |
| **Alan Cooper** | Goal-directed, eliminate excise, polite software | Madame Vivienne ingin *akses cepat ke manusia*, bukan mengobrol dengan mesin. Konteks ikut terbawa saat handoff (WCAG 3.3.7 Redundant Entry). Tidak ada pop-up proaktif |
| **Douglas Engelbart** | Augmentasi | AI terbaik untuk **atelier**: merangkum permintaan concierge dan menyusun draf balasan yang disunting staf (pola Gucci). Ini augmentasi, bukan pengganti |
| **Tim Brown** | Desirability, feasibility, viability | Desirability: prestise layanan manusia. Feasibility: Laravel + React + data yang sudah ada. Viability: biaya per pesan WhatsApp dan token LLM vs volume concierge nyata (belum ada data) |
| **Steve Krug** | Don't make me think; omit needless words; trunk test | Satu pintu bantuan, bukan dua. Label tegas "Tanya Atelier" atau "Bicara dengan Concierge". Posisi konsisten di setiap halaman (WCAG 3.2.6) |

**Daftar periksa yang dapat diuji (CB-01 – CB-22)** — diringkas dari laporan agen UX. Setiap butir menaut ke sumber di §14.

| ID | Persyaratan uji | Sumber |
|---|---|---|
| CB-01 | Launcher dan pesan pertama menyebut "Asisten AI" sebelum giliran pertama pengguna; nama aksesibel memuat "AI" | NN/g 2018; Apple HIG; EU AI Act 50 |
| CB-02 | Sapaan menyebut 3–5 kemampuan dan ≥1 batasan, disesuaikan dengan halaman (produk/keranjang/concierge) | NN/g 2026 G3; HAX G1–G2 |
| CB-03 | 2–4 saran pertanyaan berupa tombol; saran yang ditolak tidak muncul lagi | NN/g 2026 G4 |
| CB-04 | Jalur "Bicara dengan Concierge" dapat dicapai ≤1 aksi; otomatis ditawarkan setelah 2 giliran gagal dan untuk intent pesanan/kerusakan/tagihan | NN/g 2018; Baymard 2026; PAIR |
| CB-05 | Handoff membawa transkrip dan data; pengguna tidak mengetik ulang nama, nomor pesanan, atau item | WCAG 3.3.7; BCG 2025 |
| CB-06 | Eskalasi menampilkan nomor referensi, perkiraan waktu, dan kanal; bila tidak ada staf online, tawarkan formulir atau janji temu | Baymard 2026; Shneiderman closure |
| CB-07 | Fallback menyebut apa yang tidak dipahami dan langkah berikutnya; tanpa jargon atau kode galat | Google conversation design; Nielsen #9 |
| CB-08 | Toleran salah ketik nama produk/bahan dan campuran Indonesia–Inggris | NN/g 2018 (bagian Indonesia = gap) |
| CB-09 | Harga, stok, bahan, dan kebijakan diambil dari data live dan ditautkan ke halaman sumber; ada pemberitahuan "AI dapat keliru" | Apple HIG; HAX G11; Moffatt |
| CB-10 | Rekomendasi berupa kartu (gambar, nama, harga, aksi); lebih dari ±4 item membuka katalog terfilter, bukan carousel panjang | NN/g 2026 G5; NN/g 2018 |
| CB-11 | Aksi tambah ke keranjang atau buat janji dari chat butuh konfirmasi eksplisit dan menampilkan umpan balik + undo | Apple HIG; HAX G16; Shneiderman |
| CB-12 | Jawaban panjang memakai progressive disclosure; streaming tidak auto-scroll melewati awal pesan baru | NN/g 2026 G6–G7 |
| CB-13 | Tidak ada auto-open atau pop-up; tidak pernah muncul otomatis di filter katalog, keranjang, atau checkout | Baymard 2019; HAX G3/G8 |
| CB-14 | Posisi launcher tetap di semua halaman; tidak menutupi elemen fokus, swatch, atau filter pada lebar 320 px; tidak tertutup banner cookie | WCAG 3.2.6, 2.4.11; observasi Gymshark |
| CB-15 | Panel `role="dialog"` berlabel; fokus masuk saat dibuka; Esc dan tombol tutup berfungsi; fokus kembali ke launcher | APG Dialog; WCAG 2.4.3 |
| CB-16 | Seluruh chat dapat dioperasikan dengan keyboard, tanpa keyboard trap (termasuk iframe); fokus terlihat | WCAG 2.1.1, 2.1.2, 2.4.7 |
| CB-17 | Daftar pesan memakai `role="log"` berlabel; balasan diumumkan polite tanpa memindah fokus; status "mengetik" atau "menghubungkan" lewat `role="status"` | MDN; WCAG 4.1.3 |
| CB-18 | Dapat dipakai pada 320 CSS px dan teks 200% tanpa gulir horizontal | WCAG 1.4.10, 1.4.4 |
| CB-19 | Target sentuh ≥44×44 px (standar ZAFIRA, lebih ketat dari WCAG 2.5.8 AA 24 px) | AGENTS.md; WCAG 2.5.8 |
| CB-20 | Kontras teks ≥4,5:1 dan komponen ≥3:1 pada tema Sapphire/Ivory | WCAG 1.4.3, 1.4.11 |
| CB-21 | Tidak ada timeout sesi tanpa peringatan; percakapan bertahan saat berpindah halaman | WCAG 2.2.1; NN/g 2026 G2 |
| CB-22 | Tidak meminta nomor kartu atau kata sandi di chat; pemberitahuan privasi menyebut data yang dikirim ke model; umpan balik 👍/👎 opsional; transkrip dapat disimpan | NN/g 2018; Apple HIG; UU PDP |

**Dilema disclosure:** Luo et al. menemukan disclosure awal menurunkan pembelian, tetapi studinya untuk panggilan suara jasa keuangan. NN/g, Apple HIG, SE 9/2023, dan EU AI Act mendorong disclosure. [RECOMMENDATION] Tetap ungkapkan sejak awal, tetapi bingkai sebagai kompetensi ("Asisten AI atelier — dapat menjelaskan bahan, ukuran, dan menghubungkan Anda ke concierge") alih-alih permintaan maaf. Turunkan ekspektasi persona (Crolic et al.).

---

## 12. Implikasi untuk ZAFIRA

### 12.1 Baseline (FACT, kode dibaca 2026-10-30)

- Concierge sudah ada:
  - `POST /concierge` dengan rate limit 3/jam per IP ([web.php:89-124](../../routes/web.php#L89-L124)).
  - Model `ConciergeRequest` dengan state machine `new → contacted → confirmed → completed/cancelled` ([ConciergeRequest.php](../../app/Models/ConciergeRequest.php)).
  - Layanan tervalidasi: `private-styling`, `virtual-consultation`, `gifting`, `atelier`.
  - Admin dapat memperbarui status.
- Notifikasi pelanggan hanya lewat email. Belum ada WhatsApp atau push ([FEATURE_DELIVERY_TRACKER.md:113](../04-implementation/FEATURE_DELIVERY_TRACKER.md)).
- WhatsApp berstatus *Investigate* (D-06). FR-203 masih satu-satunya jalur.
- JSON-LD `Product`/`Offer` sudah ada (§7).
- **Belum ada** chatbot, widget chat, atau integrasi LLM.

### 12.2 Usulan bertahap (RECOMMENDATION — status kandidat, belum requirement)

Sesuai aturan [ZAFIRA_DIFFERENTIATION](ZAFIRA_DIFFERENTIATION.md), pola tanpa requirement berstatus `investigate`. ID di bawah adalah **kandidat** untuk diputuskan pemilik produk; belum dimasukkan ke [REQUIREMENTS_TRACEABILITY](../02-product/REQUIREMENTS_TRACEABILITY.md).

| Tahap | Kandidat | Isi | Acceptance yang dapat diuji | Bukti pendukung |
|---|---|---|---|---|
| 0 (tanpa AI) | **FR-401** Satu pintu bantuan | Launcher "Bantuan & Concierge" berposisi tetap di setiap halaman storefront. Isinya: FAQ singkat, formulir concierge (FR-203), dan, bila D-06 disetujui, tautan WhatsApp dengan pesan prefill (produk/halaman aktif) | CB-04, CB-13 – CB-20; tidak ada pop-up otomatis; trunk test lulus pada 3 jenis halaman | NN/g G1/G2; Baymard 2019; observasi Buttonscarves/Gymshark |
| 1 (deterministik) | **FR-402** Asisten terpandu | Alur tombol, tanpa LLM: ukuran, bahan, perawatan (dari data produk); status pesanan untuk pengguna login (memakai ownership FR-204); "Buat janji" membuat `ConciergeRequest` dengan konteks terisi | CB-05, CB-06, CB-11; tidak ada data yang diketik ulang; ownership diuji dengan email sama tanpa akun (pola FR-204) | Taksonomi G1/G2; BCG; Baymard 2026 |
| 1 | **FR-403** Augmentasi atelier (admin) | Ringkasan permintaan concierge dan draf balasan yang **wajib disunting** staf sebelum dikirim | Tidak ada pengiriman otomatis; jejak audit siapa yang mengirim | Engelbart; pola Gucci (VENDOR CLAIM) |
| 2 (LLM, setelah metrik tahap 0–1 ada) | **FR-404** Asisten AI berpijak pada sumber | RAG atas katalog, kebijakan, dan panduan perawatan; disclosure AI; sitasi tautan; read-only kecuali aksi yang dikonfirmasi | CB-01, CB-02, CB-07 – CB-12, CB-22; set Q&A emas ≥50 butir, akurasi faktual 100% untuk harga/stok/kebijakan; red-team OWASP LLM01/06/09 lulus | §3, §4, §9 |
| Lintas tahap | **NFR-401** Privasi chat | Retensi transkrip, DPA vendor, pemberitahuan PDP, tidak ada data kartu di chat | Dokumen kebijakan + uji konten | UU PDP; PP 33/2026 |
| Lintas tahap | **NFR-402** Performa | SDK chat atau LLM dimuat **lazy** setelah launcher diklik | Tidak ada request ke vendor chat sebelum interaksi (dibuktikan dengan capture REA atau DevTools) | Temuan REA #1 |

**Yang tidak diambil:**
- Chatbot persona manusia.
- Janji 24/7 tanpa kapasitas staf (konsisten dengan D-01).
- Checkout agentic (ACP/UCP) sebelum standarnya stabil.
- Pop-up chat proaktif.

**Pembaruan 2026-10-30:** FR-401, FR-402, NFR-401 (parsial), dan NFR-402 sudah diimplementasikan dan dipindah ke [REQUIREMENTS_TRACEABILITY](../02-product/REQUIREMENTS_TRACEABILITY.md#adendum-asisten-bantuan-tahap-01-2026-10-30). Bukti: [evidence-chatbot-impl-2026-10-30](../05-quality/evidence-chatbot-impl-2026-10-30/README.md). FR-403 dan FR-404 tetap kandidat. Kolom `concierge_requests.source = 'assistant'` kini memisahkan permintaan dari asisten untuk metrik §12.3.

### 12.3 Metrik untuk membandingkan dengan baseline

Pencatatan baseline di tahap 0, sebelum ada AI:
- jumlah permintaan concierge per minggu;
- waktu balasan pertama;
- persentase permintaan yang `completed` vs `cancelled`;
- topik pertanyaan teratas (manual).

Tanpa baseline ini, klaim "chatbot meningkatkan layanan" tidak dapat divalidasi (AGENTS.md §1.3).

---

## 13. Kesenjangan dan pertanyaan terbuka

1. Tidak ada RCT bot vs **advisor manusia** di luxury, dan tidak ada studi kausal Indonesia tentang konversi.
2. Biaya per kontak yang diaudit independen tidak ditemukan.
3. Status primer: pengurangan OpenAI Instant Checkout (tidak ada pernyataan OpenAI), teks resmi PP 33/2026, dan tanda tangan Perpres AI belum diverifikasi.
4. REA: 4 dari 10 situs terblokir. Launcher Buttonscarves belum terbuka sehingga identitasnya masih inferensi. Isi percakapan bot tidak diuji. Halaman produk dan bantuan tidak diamati.
5. Tidak ada standar untuk pengumuman screen reader atas **streaming token** LLM.
6. Belum ada riset UX chatbot berbahasa Indonesia, termasuk campuran kode Indonesia–Inggris.
7. Pertanyaan bisnis untuk pemilik ZAFIRA:
   - Berapa volume concierge nyata?
   - Siapa yang membalas, dan pada jam berapa?
   - Apakah ada nomor WhatsApp Business terverifikasi?
   - Berapa anggaran per bulan untuk pesan dan token?

---

## 14. Daftar sumber

Semua diakses 2026-10-30. `[F]` = dibaca penuh, `[S]` = cuplikan pencarian.

**Dampak dan perilaku (W)**
- W1 Fang et al., *Generative AI and Sales Productivity: Field Experiments in Online Retail* — https://arxiv.org/abs/2510.12049 `[F]`
- W3 Luo, Tong, Fang & Qu 2019, *Marketing Science* 38(6) — https://econpapers.repec.org/RePEc:inm:ormksc:v:38:y:2019:i:6:p:937-947 `[F]`
- W4 AMA press release on Crolic et al., "Blame the Bot" — https://www.ama.org/press-releases/press-release-from-the-journal-of-marketing-when-humanlike-chatbots-miss-the-mark-in-customer-service-interactions/ `[F]`
- W5 Adobe Analytics, GenAI traffic, 2025-03-17 — https://blog.adobe.com/en/publish/2025/03/17/adobe-analytics-traffic-to-us-retail-websites-from-generative-ai-sources-jumps-1200-percent `[F]`
- W6 Digital Commerce 360, holiday 2025 AI traffic — https://www.digitalcommerce360.com/2026/01/13/generative-ai-online-holiday-shopping-traffic-2025/ `[F]`
- W7 Salesforce Cyber Week 2025 — https://www.salesforce.com/news/press-releases/2025/12/05/cyber-week-ai-agents-sales/ `[F]`
- W8 Klarna release (VKTR copy) — https://www.vktr.com/the-wire/klarna-ai-assistant-handles-two-thirds-of-customer-service-chats-in-its-first-month `[F]`
- W9 Entrepreneur, Klarna rehiring humans — https://www.entrepreneur.com/business-news/klarna-ceo-reverses-course-by-hiring-more-humans-not-ai/491396 `[F]`
- W10 Gartner, 64% prefer no AI — https://www.gartner.com/en/newsroom/press-releases/2024-07-09-gartner-survey-finds-64-percent-of-customers-would-prefer-that-companies-didnt-use-ai-for-customer-service `[S]`
- W11 Gartner, headcount survey — https://www.gartner.com/en/newsroom/press-releases/2025-12-02-gartner-survey-finds-only-20-percent-of-customer-service-leaders-report-ai-driven-headcount-reduction `[S]`
- W12 Qualtrics 2026 CX Trends — https://www.qualtrics.com/news/ai-powered-customer-service-fails-at-four-times-the-rate-of-other-tasks/ `[F]`
- W14 McCarthy Tétrault, Moffatt v. Air Canada — https://www.mccarthy.ca/en/insights/blogs/techlex/moffatt-v-air-canada-misrepresentation-ai-chatbot `[F]`
- W15 The Register, DPD chatbot — https://www.theregister.com/2024/01/23/dpd_chatbot_goes_rogue `[S]`
- W16 Jalopnik, Chevrolet dealer chatbot — https://jalopnik.com/chevrolet-dealer-ai-help-chatbot-goes-rogue-pranksters-1851112556 `[S]`
- W18 Chung et al. 2020, JBR 117 — https://ideas.repec.org/a/eee/jbrese/v117y2020icp587-595.html `[F]` (abstrak)
- W19 BCG, *Why Luxury Experience Needs an AI Moment* — https://www.bcg.com/publications/2025/why-luxury-experience-needs-an-ai-moment `[F]`
- W21 Salesforce, Gucci — https://www.salesforce.com/customer-stories/gucci-ai-amplifies-client-service/ `[S]`
- W22 Databoks, Mandiri Business Survey 2024 — https://databoks.katadata.co.id/en/technology-telecommunications/statistics/673d740337c25/these-are-the-popular-online-sales-channels-used-by-small-businesses-in-2024 `[F]`
- W23 Jakarta Post, business messaging op-ed — https://www.thejakartapost.com/business/2024/10/11/we-need-to-chat-about-business-messaging-five-success-factors-from-ris-industry-leaders `[F]`
- W24 Afrina et al. 2025, Jurnal Sisfokom — https://jurnal.atmaluhur.ac.id/index.php/sisfokom/article/view/2312 `[F]`
- W25 Lita & Tjhin 2026 — https://zenodo.org/records/18666543 `[F]`
- W-R iMotions, robot vs human in luxury retail — https://imotions.com/blog/publications/young-customer-responses-to-service-robots-vs-humans-in-luxury-retail-a-multidisciplinary-approach/ `[S]` ⚑
- W-Su Su et al., virtual assistant vs salesperson in luxury — https://cem.scau.edu.cn/2026/0827/c5592a442225/page.htm `[S]` ⚑
- W-Li Li & Shin 2022, emoticons in luxury chatbots — cuplikan, URL tidak terverifikasi ⚑

**Industri, arsitektur, regulasi (I)**
- I1 Amazon Q4 2025 results — https://ir.aboutamazon.com/news-release/news-release-details/2026/Amazon-com-Announces-Fourth-Quarter-Results/ `[F]`
- I2 Stripe, Instant Checkout / ACP — https://stripe.com/newsroom/news/stripe-openai-instant-checkout `[F]`
- I3 Alibaba, Taobao × Qwen — https://www.alibabagroup.com/document-1991231293551017984 `[F]`
- I4 WhatsApp Business Platform pricing — https://developers.facebook.com/docs/whatsapp/pricing `[F]`
- I5 OWASP Top 10 for LLM Applications 2025 — https://genai.owasp.org/llm-top-10/ `[F]`
- I6 EU AI Act Article 50 — https://artificialintelligenceact.eu/article/50/ `[F]` (mirror tidak resmi)
- I7 Zalando Assistant — https://corporate.zalando.com/en/node/10667 `[F]`
- I8 Katadata, WhatsApp AI agent — https://katadata.co.id/digital/teknologi/6a7ac1822cbd4/whatsapp-buka-agen-ai-24-jam-umkm-bisa-layani-pelanggan-tanpa-tambah-staf `[F]`
- I11 OpenAI × Zalando — https://openai.com/index/zalando/ `[S]`
- I15 Microsoft, LazzieChat — https://news.microsoft.com/source/asia/2023/05/30/lazada-unveils-lazziechat-the-first-ecommerce-ai-chatbot-of-its-kind-in-se-asia/ `[S]`
- I16 Inside Retail, Shopify Winter '26 — https://insideretail.com.au/digital/sidekick-agentic-storefronts-simgym-how-shopifys-latest-edition-harnesses-ai-202512 `[S]`
- I17 Digital Commerce 360, AP2 — https://www.digitalcommerce360.com/2025/09/19/google-ai-payments-protocol-ap2/ `[S]`
- I19 Shopifreaks, UCP — https://www.shopifreaks.com/google-and-shopify-launch-universal-commerce-protocol/ `[S]`
- I20 Shopifreaks, OpenAI pivot — https://www.shopifreaks.com/openai-pivots-from-instant-checkout-to-an-app-based-shopping-model-inside-chatgpt/ `[S]`
- I21 American Banker, Visa/Mastercard agentic payments — https://www.americanbanker.com/payments/news/visa-mastercard-launch-payments-for-agentic-ai `[S]`
- I27 RSM, PP 33/2026 client alert — https://www.rsm.global/indonesia/sites/default/files/media/publications/Client%20Alert/2026/RSM%20Indonesia%20Client%20Alert%20-%20PP%20332026%20Six%20Implementation%20Priorities%20before%2016%20January%202027.pdf `[S]`
- I28 Bisnis, pembentukan Badan PDP — https://teknologi.bisnis.com/read/20260913/84/2003698/pembentukan-badan-pdp-dipercepat-implementasi-uu-pdp-dinilai-belum-efektif `[S]`
- I29 Kontan, dua Perpres AI — https://nasional.kontan.co.id/news/dua-perpres-ai-tinggal-tunggu-tanda-tangan-prabowo `[S]`
- I30 Kominfo SE 9/2023 — https://web.kominfo.go.id/sites/default/files/kominfo-Surat-Edaran-Kecerdasan-Artifisial.pdf `[S]`
- I-ka Kata.ai, chatbot WhatsApp Business API — https://blog.kata.ai/kata-chatbot-whatsapp-business-api `[S]`
- R-MS Microsoft Learn, Clarity Brand Agents — https://learn.microsoft.com/en-us/clarity/brand-agents/installation/shopify-install-guide `[S]`

**UX/HCI dan aksesibilitas (U)**
- U1 NN/g, *The User Experience of Chatbots* (2018) — https://www.nngroup.com/articles/chatbots/ `[F]`
- U2 NN/g, *10 Guidelines for Designing Your Site's AI Chatbots* (2026) — https://www.nngroup.com/articles/ai-chatbots-design-guidelines/ `[F]`
- U3 NN/g, *Accordion Editing and Apple Picking* — https://www.nngroup.com/articles/accordion-editing-apple-picking/ `[F]`
- U4 NN/g, *Prompt Controls in GenAI Chatbots* — https://www.nngroup.com/articles/prompt-controls-genai/ `[F]`
- U6 Baymard, live chat usability issues (2019) — https://baymard.com/research-articles/live-chat-usability-issues `[F]`
- U7 Baymard, AI engagement quantitative insights (2026) — https://baymard.com/research-articles/ai-engagement-quantitative-ux-insights-2026 `[F]`
- U8 Microsoft HAX Toolkit library — https://www.microsoft.com/en-us/haxtoolkit/library/ `[F]`
- U11 Apple HIG, Generative AI — https://developer.apple.com/design/human-interface-guidelines/generative-ai `[F]`
- U12 Google PAIR, Errors + Graceful Failure — https://pair.withgoogle.com/chapter/errors-failing/ `[F]`
- U13 IBM Carbon, AI label usage — https://carbondesignsystem.com/components/ai-label/usage/ `[F]`
- U14 Google, *Learn about conversation* — https://developers.google.com/assistant/conversation-design/learn-about-conversation `[F]`
- U16 WCAG 2.2 — https://www.w3.org/TR/WCAG22/ `[F]` (sebagian)
- U17 What's New in WCAG 2.2 — https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/ `[F]`
- U18 Understanding SC 4.1.3 Status Messages — https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html `[F]`
- U19 MDN, ARIA `log` role — https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Roles/log_role `[F]`
- U21 WAI-ARIA APG, Dialog (Modal) pattern — https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/ `[F]`

**Bukti REA:** [docs/05-quality/evidence-chatbot-rea-2026-10-30/](../05-quality/evidence-chatbot-rea-2026-10-30/) berisi:
- `inputs/` — 13 skenario JSON
- `summarize.py`
- `summary.txt`
- `evidence-ids.txt` — Evidence ID dan SHA-256 awal dari keluaran mentah

Keluaran mentah (±10 MB, berisi konten pihak ketiga) tidak disimpan di repo.

**Metodologi:** ±50 kueri pencarian oleh tiga agen paralel. Sub-pertanyaan: dampak dan perilaku; industri, agentic, arsitektur, dan regulasi; UX/HCI dan aksesibilitas. Ditambah 14 capture REA pada 10 storefront.
