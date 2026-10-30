# Spesifikasi Asisten Bantuan & Concierge (FR-401/FR-402)

**Versi:** 2.0 · 2026-10-30
**Status:** terimplementasi lokal, belum dirilis
**Dasar keputusan:** [riset chatbot e-commerce §11–§12](../research/CHATBOT_ECOMMERCE_DEEP_RESEARCH_2026-10-30.md#12-implikasi-untuk-zafira)
**Bukti:** [evidence-chatbot-impl-2026-10-30](../05-quality/evidence-chatbot-impl-2026-10-30/README.md)

## 1. Tujuan

Asisten ini memberi klien ZAFIRA satu pintu bantuan. Jawaban faktual bersifat instan dan berasal dari data resmi. Jalur ke concierge manusia selalu tersedia dalam satu langkah.

| Persona | Tujuan | Yang dijawab asisten |
|---|---|---|
| Madame Vivienne (klien VIP) | Akses cepat ke manusia, tanpa mengulang cerita | Tombol "Bicara dengan Concierge" selalu tampil. Catatan konteks terisi otomatis. Hasilnya nomor referensi |
| Pembeli yang sedang meriset | Menemukan kreasi dan memahami detailnya | Pencarian katalog, kartu produk, detail bahan dan asal, ukuran, perawatan |
| Maître Artisan / staf | Permintaan yang jelas dan bisa dilacak | Catatan terstruktur, label "via Asisten", pencarian `ZC-…` di admin |

**Bukan tujuan:**
- LLM atau AI generatif. Itu kandidat FR-404 dan butuh baseline metrik lebih dulu.
- Persona manusia palsu.
- Pop-up proaktif.
- Janji layanan 24/7.
- Mengubah pesanan atau memproses pembayaran.
- Checkout agentic.

## 2. Ruang lingkup halaman

| Halaman | Launcher | Catatan |
|---|---|---|
| Beranda `/` | Ya | Konteks: "Beranda" |
| PDP `/produk/{slug}` | Ya | Konteks: produk aktif, termasuk topik "Detail produk ini" |
| Katalog `/koleksi`, Belanja `/belanja` | **Belum** | Kedua halaman memakai layout lain. Ini item lanjutan |

## 3. Requirement turunan

| ID | Kebutuhan | Acceptance (dapat diuji) | Test |
|---|---|---|---|
| FR-401.1 | Launcher berlabel teks | "Bantuan & Concierge", ≥44×48 px, posisi tetap kanan bawah, tidak membuka sendiri, tidak keluar layar 320 px | Vitest "CB-13/NFR-402"; browser A, C |
| FR-401.2 | Dialog dapat diakses | `role="dialog"` berlabel; fokus masuk dan terkunci; Esc, tombol tutup, atau klik backdrop menutup; fokus kembali ke launcher; pintasan **H** di semua halaman storefront | Vitest "CB-01/02/03/15/17", "H opens help"; browser B |
| FR-402.1 | Disclosure dan kemampuan | Teks "bukan AI dan bukan staf" sebelum giliran pertama; sapaan menyebut kemampuan dan batas; nama depan bila klien login | Vitest |
| FR-402.2 | Status concierge live | Status "tersedia sekarang" atau "di luar jam layanan · balasan mulai …" dihitung dari jam WIB (Sen–Sab 10.00–19.00), tidak bergantung pada jam perangkat | Vitest "knows concierge hours" (5 kasus) |
| FR-402.3 | Pencarian kreasi | Teks bebas atau tile "Cari kreasi" memanggil `GET /bantuan/produk`. Setiap kata harus cocok (sinonim ID/EN, tanpa aksen, satu salah ketik). Maksimal 4 kartu dan tautan "Lihat semua". Hasil parsial diberi label. Produk nonaktif tidak pernah tampil | `ConciergeAssistantTest` (3 test); Vitest "CB-10/11"; browser A |
| FR-402.4 | Tambah ke tas dari chat | Dijalankan hanya setelah konfirmasi eksplisit yang menyebut nama dan harga. Hasilnya bisa di-undo (delta −1). Busana berukuran diarahkan ke "Pilih ukuran" di PDP. Stok habis tidak bisa ditambahkan | Vitest "CB-10/11"; browser A (badge 0→1→0) |
| FR-402.5 | Topik pengetahuan | Detail produk, ukuran, pengiriman & retur, status pesanan, perawatan, hadiah & kemasan, personalisasi, dan pembayaran. Semua jawaban memakai teks resmi yang sudah ada (§6) dan menautkan sumbernya | Vitest "gift and personalisation", "menu … order", "CB-09" |
| FR-402.6 | Langkah lanjut kontekstual | 2–3 saran sesuai jawaban terakhir (§5). Topik yang tidak relevan untuk halaman disaring | Vitest "CB-09" |
| FR-402.7 | Umpan balik jawaban | "Apakah jawaban ini membantu? Ya/Tidak" pada jawaban terakhir. `POST /bantuan/umpan-balik` anonim. "Tidak" menawarkan concierge | `ConciergeAssistantTest` feedback; Vitest; browser A (204) |
| FR-402.8 | Eskalasi ke manusia | Langsung untuk kata rusak/salah kirim/tagihan/staf, setelah dua pertanyaan tak dipahami, atau dari tombol permanen. Pratinjau catatan, konfirmasi, form concierge terisi (layanan sesuai konteks), lalu kembali ke chat dengan nomor `ZC-000000` | Vitest "CB-04/05/06/07"; browser B |
| FR-402.9 | Kontinuitas | Transkrip bertahan antarhalaman di `sessionStorage` (v2, maks. 60 entri). Ada tombol "Mulai ulang". Storage rusak atau terblokir tidak memutus fungsi | Vitest "CB-21"; browser D |
| FR-402.10 | Dukungan staf | Admin melihat nomor referensi dan label "via Asisten". Kolom pencarian menerima `ZC-000042` | `ConciergeAssistantTest` admin |
| NFR-401 | Privasi | Kata kunci pencarian dikirim tanpa disimpan atau dicatat. Umpan balik hanya menyimpan topik, ya/tidak, dan locale. Isi lain baru terkirim lewat form concierge yang dapat disunting. Ada peringatan larangan menulis nomor kartu atau kata sandi | `ConciergeAssistantTest` feedback (kolom tabel) |
| NFR-402 | Tanpa vendor | Tidak ada SDK chat, model, atau origin eksternal baru. Tidak ada panggilan `/bantuan` sebelum asisten dibuka | Browser A |
| NFR-403 | Aksesibilitas | Target ≥44 px; kontras ≥4,5:1 (terukur 4,69–16,08); tanpa gulir horizontal di 320 px; log `role="log"`; status pencarian `role="status"` | Browser C; `colors-*.json` |

## 4. Pengenalan intent

Intent dicek berurutan; yang pertama cocok menang. Kecocokan dihitung per kata, tanpa aksen. Akhiran "-nya" dilepas. Salah ketik satu huruf diterima untuk kata kunci ≥5 huruf.

| Prioritas | Intent | Contoh kata kunci | Hasil |
|---|---|---|---|
| 1 | issue | rusak, salah, tagihan, damaged, charged | Eskalasi langsung |
| 2 | human | staf, concierge, whatsapp, contact | Eskalasi |
| 3 | order | pesanan, lacak, resi, track | Tautan riwayat akun atau kode pelacakan |
| 4 | payment | bayar, kartu, transfer, cicilan | Jawaban pembayaran |
| 5 | appointment | janji, fitting, booking, konsultasi | Eskalasi layanan *private styling* |
| 6 | delivery | kirim, ongkir, retur, refund | Kebijakan retur dan pengiriman |
| 7 | personalize | monogram, inisial, custom | Atelier Z |
| 8 | gift | hadiah, kado, kemasan, pita | L'Art d'Offrir |
| 9 | care | perawatan, cuci, simpan | Perawatan produk atau umum |
| 10 | size | ukuran, size, muat | Panduan ukuran |
| 11 | product | bahan, harga, warna, stok | Detail produk (PDP). Di beranda berubah menjadi pencarian |
| 12 | search | cari, rekomendasi, koleksi | Pencarian katalog |
| — | (tidak ada) | "kebaya", "songket" | Dicoba sebagai pencarian. Bila kosong dihitung gagal paham |

Bahasa Prancis, Italia, dan Mandarin belum punya kata kunci, tetapi tile dan tombol tetap berfungsi penuh.

## 5. Alur percakapan

```
[Launcher / H] → Sapaan + 8 tile topik (PDP: + "Detail produk ini", tanpa "Ukuran" bila produk tak berukuran)
   ├─ Tile / teks dikenali → Jawaban → [Ya/Tidak membantu] + 2–3 langkah lanjut
   ├─ Teks tak dikenali → Pencarian katalog → kartu │ kosong → gagal paham (#1) → gagal paham (#2) → Eskalasi
   ├─ Kartu "Tambah ke tas" → Konfirmasi → Ditambahkan [Urungkan][Buka tas]
   └─ Eskalasi → Pratinjau catatan → [Lanjutkan] → Form concierge terisi → Kirim → kembali ke chat dengan ZC-xxxxxx
```

Langkah lanjut per jawaban:

| Jawaban | Saran |
|---|---|
| produk | perawatan, ukuran, personalisasi |
| hasil pencarian | pengiriman & retur, hadiah & kemasan |
| ukuran | janji temu, pengiriman & retur |
| pengiriman & retur | status pesanan, pembayaran |
| status pesanan | pengiriman & retur, pembayaran |
| perawatan | produk, janji temu |
| hadiah & kemasan | personalisasi, janji temu |
| personalisasi | hadiah & kemasan, janji temu |
| pembayaran | status pesanan, pengiriman & retur |

## 6. Sumber jawaban (tata kelola teks)

Asisten tidak membuat klaim baru. Setiap jawaban merujuk kunci yang sudah ada:

| Topik | Sumber |
|---|---|
| Detail produk dan perawatan produk | Props PDP (`StorefrontPageProps::product`) saat jawaban dibuat |
| Pencarian | Database produk aktif saat permintaan (`AssistantProductSearch`) |
| Pengiriman & retur | `shop_ui.welcome.returns_policy` |
| Hadiah & kemasan | `shop_ui.welcome.packaging_desc`, `packaging_complimentary` |
| Personalisasi | `shop_ui.welcome.atelier_desc`, `shop_ui.pdp.personalization` |
| Perawatan umum | `shop_ui.welcome.service_3_desc` |
| Ukuran | `shop_ui.pdp.size_guide_note` |
| Pembayaran | `shop_ui.assistant.payment_answer`, yang menyalin fakta dari `shop_ui.shop.checkout_notice` |
| Jam layanan | `shop_ui.footer.hours` dan konstanta `CONCIERGE_HOURS` (**ubah keduanya bersamaan**) |

## 7. Kontrak API

**`GET /bantuan/produk?q=`**
- Pembatasan: throttle 30/menit, `NoStore`.
- `q`: wajib, maksimal 120 karakter.
- Respons:
  ```
  { items: [{ id, productId, name, category, priceLabel, available, image, alt, href, hasSizes }] (≤4), total, partial, terms, catalog }
  ```
- `terms: []` berarti kueri hanya berisi stopword.

**`POST /bantuan/umpan-balik`**
- Body: `{ topic, helpful }`.
- Pembatasan: throttle 20/menit.
- `topic` ∈ product, results, size, delivery, order, care, gift, personalize, payment.
- Respons: 204.

**`POST /concierge`** (yang sudah ada)
- Kini menerima `source` (`assistant` atau null).
- Mengembalikan `reference`.

## 8. Data

| Tabel/kolom | Isi | Migrasi |
|---|---|---|
| `concierge_requests.source` | `assistant` atau null | `2026_10_30_000010` |
| `assistant_feedback` | `topic`, `helpful`, `locale`, timestamps. Tanpa user, sesi, IP, atau teks | `2026_10_30_000020` |

## 9. Metrik (baseline §12.3 riset)

```sql
-- Porsi permintaan concierge dari asisten per minggu
SELECT strftime('%Y-%W', created_at) AS week, COUNT(*) AS total, SUM(source = 'assistant') AS via_assistant
FROM concierge_requests GROUP BY week;
-- Tingkat "membantu" per topik
SELECT topic, COUNT(*) AS votes, ROUND(AVG(helpful) * 100) AS helpful_pct FROM assistant_feedback GROUP BY topic;
```

Kueri di atas memakai fungsi SQLite. Untuk MariaDB/MySQL, ganti `strftime` dengan `YEARWEEK(created_at)`.

Ambang untuk mempertimbangkan FR-404 (AI) adalah usulan dan perlu diputuskan pemilik produk:
- ≥8 minggu data;
- ≥200 suara umpan balik;
- tingkat "Tidak membantu" ≥30% pada topik yang tidak bisa diperbaiki dengan teks resmi.

## 10. Batasan yang diketahui

- Kandidat pencarian dibatasi 500 produk aktif dan dicocokkan di PHP. Saat katalog tumbuh, ganti dengan indeks pencarian.
- Kamus sinonim (`AssistantProductSearch::SYNONYMS`) dirawat manual dan perlu diperbarui saat ada kategori atau nama produk baru.
- Belum diuji dengan screen reader sungguhan, pada teks 200%, atau oleh pengguna nyata.
- Tidak ada tampilan admin untuk umpan balik. Untuk sementara pakai kueri di §9.

## 11. Tahap berikutnya

1. Launcher di `/koleksi` dan `/belanja`.
2. Panel ringkas metrik asisten di admin.
3. FR-403: draf balasan untuk staf yang wajib disunting.
4. FR-404: asisten AI berbasis RAG, hanya setelah ambang di §9 terpenuhi dan set Q&A emas (≥50 butir) tersedia.
