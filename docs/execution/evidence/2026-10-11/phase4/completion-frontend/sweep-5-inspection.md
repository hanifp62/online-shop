# Sweep 5 — pemeriksaan terbaru

Server: http://127.0.0.1:8015. Tidak mengubah source, .env, port 8000/8001, atau DB operasional.
Sweep 4 stale; bukan bukti perubahan terakhir.

## Hasil

- Sweep: 56 kasus, 0 kegagalan, exit 0.
- LCP maksimum: 484 ms (/prototype/, fr, 768×1024), kondisi lokal tanpa throttling.
- CLS maksimum: 0.1021783272914058 (/, fr, 320×568). Melebihi target umum 0.1; bukan assertion kegagalan script.
- Overflow maksimum: 1 px (/prototype/, en, 1366×768); script mengizinkan <=1 px.
- Overlay run5: 14 kasus, 174/174 assertion lulus, 0 console/page error, 0 failed request, exit 0.
- Drawer check v2: 4/4 bahasa lulus; tidak ada raw key; FR dan localStorage bertahan setelah reload.
- Drawer/close aria-label berubah sesuai bahasa. Toggle #btn-mobile-nav-toggle tetap 'Buka Menu Navigasi' pada EN/FR/IT.

## Drawer

| Bahasa | Heading | Koleksi | Label bahasa | FR reload |
|---|---|---|---|---|
| ID | Navigasi Kriya | Koleksi | Bahasa | fr/fr |
| EN | Craft Navigation | Collections | Language | fr/fr |
| FR | Navigation des créations | Collections | Langue | fr/fr |
| IT | Navigazione dell'artigianato | Collezioni | Lingua | fr/fr |

Nama tombol bahasa ID/EN/FR/IT merupakan singkatan yang disengaja. Empat heading dan empat drawer aria-label berbeda.

PENTING: sweep-5-drawer-check.log/json/exitcode (v1) tidak valid sebagai hasil produk. Harness v1 menimpa localStorage setiap reload dan memakai ekspektasi label tebakan. Gunakan sweep-5-drawer-check-v2.log/json/exitcode. Bukti v1 dipertahankan untuk transparansi.

## Pemeriksaan visual langsung — 8 gambar terbaru

Semua gambar berikut berada di shots-sweep-5-visual/. Viewport 390×900 atau 1440×900; bukan screenshot full-page.

- store-id-390.png: hero ID dan CTA lengkap; tidak ada overlap; announcement dipotong dengan ellipsis.
- store-fr-390.png: hero FR tiga baris dan CTA lengkap; tidak ada overlap; announcement dipotong dengan ellipsis.
- store-id-1440.png: navigasi, logo, hero ID tiga baris dan CTA utuh; tidak ada overlap/clipping.
- store-fr-1440.png: navigasi FR, hero dua baris dan CTA utuh; tidak ada overlap/clipping.
- prototype-id-390.png: header dan gambar utuh; dock tetap menutupi sebagian paragraf hero bawah; announcement ellipsis.
- prototype-fr-390.png: hero FR membungkus tiga baris; dock menutupi sebagian paragraf bawah; announcement ellipsis.
- prototype-id-1440.png: hero split-view dan dua CTA utuh; dock tidak menutupi CTA; announcement ellipsis.
- prototype-fr-1440.png: hero FR split-view dan CTA utuh; placeholder pencarian terpotong; announcement ellipsis.

Kontras: NOT RUN, tidak ada pengukuran kontras pada eksekusi ini.

## Target internal 44×44

Sweep menyimpan maksimum 30 smallTargets per kasus dan hanya tag/label/ukuran, bukan selector. Hasil bukan inventaris lengkap semua kontrol.
Pengayaan selector read-only dari delapan viewport terbaru tersimpan dalam sweep-5-selector-inventory.json.

- button.theme-btn, button.foil: 20×20.
- button.lang-btn: sekitar 13×24.
- button.wishlist-button: 32×32 atau 37×37.
- button.product-add: 36×36 atau 40×40.
- button.icon-button: 37×37.
- button.btn-quick: tinggi sekitar 34; lebar 160/186.
- button.filter-pill: tinggi sekitar 34.

Frekuensi sweep (bukan jumlah kontrol unik): tombol kosong 20×20 sebanyak 36; Mode Terang/Gelap 20×20 masing-masing 32; Cari/Profil VIP/Buka tas belanja 37×37 masing-masing 28.

## Resource Timing transferBytes mentah

| Surface | Sampel | Minimum | Median atas | Maksimum |
|---|---:|---:|---:|---:|
| / | 28 | 4192268 | 4561940 | 6192968 |
| /prototype/ | 28 | 6628803 | 6628803 | 6628803 |

Jumlah dari resource entries, tidak termasuk dokumen navigasi; resource lintas-origin tanpa TAO dapat menghasilkan 0. Bukan total jaringan absolut.

## Batas bukti

Sweep menghasilkan 16 screenshot, walaupun stdout bawaan mengklaim 32. Nama *-id-390.png pada shots-sweep-5/ diambil setelah switch FR; jangan labeli gambar tersebut sebagai ID. Delapan gambar tambahan pada shots-sweep-5-visual/ memperbaiki cakupan bahasa untuk inspeksi.
Tidak menjalankan audit kontras, perangkat fisik, atau pengujian jaringan produksi. Tidak mengubah source untuk temuan kecil.
