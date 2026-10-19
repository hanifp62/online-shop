# Benchmark Global Retail: Temu, Shein, Etsy

> Konteks: referensi pola adopsi untuk ZAFIRA — Laravel luxury shop, MVP: concierge request (tanpa payment), products/categories, wishlist, cart di `localStorage`, admin CRUD, tanpa tabel Order di MVP. Bahasa Indonesia; nama tool/term dalam English. *(Koreksi 2026-10-19: konteks ini historis. Cart kini di tabel `carts`/`cart_items` (migrasi `2026_10_19_000002_create_carts_tables.php`); localStorage hanya rekonsiliasi legacy. Tabel `orders`/`order_items` kini ada.)*

Legenda bukti: **[F]** = Fakta terverifikasi (dengan URL sumber) · **[I]** = Inferensi beralasan · **[U]** = Unverified/belum tersedia.

---

## 1. Temu (PDD Holdings)

### 1.1 Popularitas **[F]**
- ±**1,2 miliar visits** (Mei 2026, Similarweb, peringkat #28 global): https://www.similarweb.com/blog/research/market-research/most-visited-websites
- App **±246 juta MAU**, marketplace web #2 setelah Amazon; traffic web tumbuh ±57% YoY (laporan Similarweb 2025): https://ir.similarweb.com/news-events/press-releases/detail/132/
- App e-commerce #2 AS ±129 juta MAU (Sep, estimasi Similarweb): https://www.similarweb.com/blog/insights/ecommerce-news/temu

### 1.2 HCI / UI-UX
- Search + autocomplete agresif, kategori dangkal, feed "You may also like" infinite scroll **[F/I]** — infinite scroll/gamifikasi disebut Komisi Eropa sebagai pola adiktif yang diselidiki: https://commission.europa.eu/topics/consumers/consumer-rights-and-complaints/enforcement-consumer-protection/coordinated-actions/market-places-and-digital-services_en
- Product card padat: foto, harga coret, badge diskon, rating, "terjual X" — social proof kuantitatif di level list (konsisten dengan riset Baymard bahwa review adalah konten terpenting kedua setelah foto: https://baymard.com/research-articles/current-state-ecommerce-product-page-ux) **[F/I]**
- PDP: galeri swipe, varian warna/ukuran, estimasi pengiriman menonjol, kupon bertumpuk, countdown flash sale **[I]** (pola countdown dikritik regulator — lihat §1.5).
- Cart & checkout satu akun terpusat; mini-cart + free-shipping progress bar (ambang minimum order tidak selalu diinformasikan di muka — temuan CPC Network, URL sama di atas) **[F]**.
- Error handling: kebijakan return/refund terstruktur via "Your Orders → Return/Refund", termasuk kasus paket hilang dan "delivered but not received" **[F]**: https://www.temu.com/return-and-refund-policy.html
- Mobile: app-first; fitur terkuat (gamifikasi, spin-wheel) hanya di app (Similarweb Apps 100, via https://ir.similarweb.com/news-events/press-releases/detail/89/) **[F]**.
- Pemetaan teori: pola Temu **melanggar** Nielsen H1 (visibility status jujur), H2 (match real world — diskon palsu), dan prinsip Krug ("Don't make me think" disalahgunakan menjadi "don't let me think" lewat urgensi palsu).

### 1.3 Workflow & logika
- Alur: browse → cart → checkout → order → tracking → return/refund/price-adjustment **[F]**.
- State machine order **[I, pola umum marketplace]**: `pending → paid → shipped → in-transit → delivered → (return-requested → refunded)`; cabang `price-adjustment (≤30 hari)` — kebijakan price adjustment 30 hari Temu **[F]**: https://www.settlemate.io/blog/temu-price-adjustment
- Retur gratis ±90 hari (klaim komunitas; label **[U]**, perlu verifikasi kebijakan resmi per region).
- Model seller: **fully-managed** (kirim ke gudang Temu, platform urus sisanya) vs **semi-managed** (seller pegang stok/harga/logistik lokal, diluncurkan 2024) **[F]**: https://techbuzzchina.substack.com/p/how-temus-semi-managed-model-could · https://www.photonpay.com/hk/blog/article/temu-semi-managed-service
- Promosi: kupon bertumpuk, bundle, flash sale, price-adjustment pasca-beli **[F/I]**.

### 1.4 MIS / seller tools
- **Temu Seller Center**: registrasi, katalog, order, logistik, retur, iklan, edukasi, support dalam satu portal **[F]**: https://stackinfluence.com/blog/temu-seller-center-setup-and-selling-guide-for-2026
- Notifikasi: push/email status order, refund, price-drop **[I]**.

### 1.5 Yang TIDAK boleh dicopy (dengan sumber)
- **Fake discounts, pressure selling (deadline/stok palsu), forced gamification (spin-wheel wajib), fake reviews, hidden contact** — temuan resmi CPC Network UE Nov 2024 (URL Komisi Eropa di §1.2) **[F]**.
- Denda **€200 juta** (Mei 2026) di bawah DSA atas risiko produk ilegal; proceedings adiktif-design dibuka Okt 2024 **[F]**: https://ecommerce-europe.eu/news-item/commission-hits-temu-with-a-e200-million-fine-over-unsafe-products
- Kritik dark pattern (countdown, "Low stock", confirm-shaming, gamifikasi "Lucky Draw/Shake & Win") **[F]**: https://www.beuc.eu/press-release/consumer-groups-file-complaint-against-shein-dark-patterns-fuelling-over-consumption · https://www.glossy.co/fashion/how-shein-and-temu-are-using-dark-patterns-to-drive-holiday-sales

---

## 2. Shein

### 2.1 Popularitas **[F]**
- ±**283 juta visits** (Agu 2026, Semrush): https://www.semrush.com/website/shein.com/overview
- App shopping #2 paling diunduh global 2025; valuasi ±$66 miliar awal 2026; retailer fashion online #3 AS (Statista, via snippet) **[F/U]** — angka Statista di balik paywall, label **[U]** untuk detail.
- Brand awareness >70% di kalangan fashion shopper AS **[U]** (Statista snippet, belum terverifikasi penuh).

### 2.2 HCI / UI-UX
- Search dengan riwayat + trending; kategori: New Arrivals / Trends / Category / Best Sellers **[F]**: https://apps.apple.com/us/app/shein-shopping-online/id878577184
- Filter fashion-spesifik (size, color, style, length) + **size guide & preferensi ukuran tersimpan** di akun — contoh bagus eliminasi excise (Cooper) **[F/I]**.
- PDP: galeri besar, varian warna→ukuran bertingkat, size recommender, review foto-pembeli masif + poin reward untuk review (Shein Points) **[F]**: https://www.groupon.com/coupons/shein
- Social proof: jumlah review + foto user; sesuai temuan Baymard soal reviewer-submitted images (URL §1.2) **[F/I]**.
- Cart persisten + kupon field di checkout; checkout multi-metode (PayPal, kartu) **[F]** (App Store listing di atas).
- Return self-service: My Orders → Order Details → Return Item + alasan + foto; refund 1–10 hari pasca quality check; jendela ±30 hari **[F]**: https://us.shein.com/return-policy-a-281.html
- Pemetaan teori: filter kuat = Shneiderman ("overview first, zoom & filter, details-on-demand"); review berfoto = Norman behavioral (feedback meyakinkan); gamifikasi poin = sisi gelap yang sama dengan Temu.

### 2.3 Workflow & logika
- Alur identik marketplace + cabang **exchange** (return → reorder bila exchange tak tersedia) **[F]** (return policy di atas).
- State machine **[I]**: `pending → paid → shipped → in-transit → delivered → (return-requested → QC → refunded/exchanged)`.
- Varian: SKU per (color × size); stok menipis memicu badge "Only xx left" **[I; klaim "low-stock" dipersoalkan regulator — §2.5]**.
- Promosi: kupon, poin, flash sale s/d 90%, free shipping bersyarat **[F]** (App Store listing).

### 2.4 MIS / seller tools
- **SHEIN Marketplace Seller Center** (seller-us.shein.com): zero monthly fee, kelola order + insights **[F]**: https://seller-us.shein.com
- Model fully-managed ala Temu (platform pegang trafik/logistik) **[I]**: https://www.shipsaving.com/blog/temu-shein-and-tiktok-shop-which-platform-is-best-for-us-e-commerce-sellers

### 2.5 Yang TIDAK boleh dicopy
- **Fake discounts, false purchase deadlines, misleading return info, misleading sustainability claims, hidden contact** — notifikasi CPC Network Mei 2025 **[F]** (URL Komisi Eropa §1.2). Denda FR ±€40 juta (promosi menipu) & IT ±€1 juta (klaim lingkungan) **[F]**: https://aqurate.ai/en/blog/goodbye-temu-auf-wiedersehen-shein
- Keluhan BEUC Jun 2025: dark pattern (low-stock, countdown, testimonials, confirm-shaming, infinite scroll, nagging) **[F]** (URL BEUC §1.5).
- Formal DSA proceedings (addictive design, rekomendasi, produk ilegal) **[F]**: https://www.cleolabs.co/en/blog/temu-200m-dsa-fine-illegal-products-2026

---

## 3. Etsy

### 3.1 Popularitas **[F]**
- **86,5 juta active buyers**, ±5,6 juta sellers, GMS $10,5 miliar (FY2025, investor relations): https://investors.etsy.com/company-information · https://investors.etsy.com/news-events/press-releases/detail/218/
- ±435 juta visits (Mei 2026, Similarweb, URL §1.1); 100 juta+ listings aktif **[F]**.

### 3.2 HCI / UI-UX
- Search + filter (Ships from country/city, local makers) + sort; ada **Marketplace Insights** (data keyword 30 hari) untuk seller **[F]**: https://help.etsy.com/hc/en-us/articles/35122361353239-How-Do-I-Use-Etsy-s-Marketplace-Insights-Tool
- Product card: foto finished-item (wajib untuk listing personalisasi — bukan mockup kosong) + badge Digital Download yang jelas **[F]**: https://help.etsy.com/hc/en-us/articles/360000344528-How-to-Offer-Personalized-Listings
- PDP: galeri, **variations** (size/color/material — memengaruhi stok/SKU) + **Custom options / personalization** (maks 5 field: Text box, List of options ≤30, File upload ≤10 file) + harga add-on opsional ($0,20–500) **[F]** (URL sama). ⭐ Pola paling relevan untuk monogram ZAFIRA.
- Cart & guest checkout; tracking via Purchases page + tombol Track Package; status: Not Shipped / Pre-transit / In Transit / Shipped / Delivered + estimasi tanggal **[F]**: https://help.etsy.com/hc/en-us/articles/115015521948-What-s-the-Status-of-My-Order
- Trust: review + foto buyer, Star Seller badge, Purchase Protection refund (tidak tiba/rusak/tak sesuai) **[F]** (URL sama).
- Error handling: "Help with order" → hubungi seller → Open a case (>48 jam) → mediasi Etsy **[F]** (URL sama).
- Pemetaan teori: Custom options = Norman constraints + counter (contoh limit karakter 1–1024); varian vs personalisasi yang dibedakan tegas = Nielsen consistency + Krug clarity; guest checkout = Baymard (18% abandon karena wajib akun: https://baymard.com/lists/cart-abandonment-rate).

### 3.3 Workflow & logika
- Alur: search → listing → personalisasi → cart → checkout → made-to-order → ship → tracking → review/case **[F]**.
- State machine **[I, dari help docs]**: `open → (personalization-attached) → paid → in-production → shipped → in-transit → delivered → (case → refunded)`; digital: `paid → download`.
- Model data: **variations** (stok/SKU/price-sensitive) vs **personalization fields** (non-inventory, menempel di order, add-on pricing) **[F]** (help article §3.2) — adopsi langsung untuk monogram: field terstruktur, bukan chat bebas.
- Promosi: sale badge, kupon, Etsy Ads/Offsite Ads **[F]**: https://help.etsy.com/hc/en-us (Marketing & Promotions).

### 3.4 MIS / seller tools
- **Shop Manager**: Dashboard, Listings, Orders (filter status/ship-by/personalization/gift), Stats (traffic, conversion, keyword), Marketing, Finances **[F]**: https://www.gelato.com/blog/etsy-shop-manager · https://help.etsy.com/hc/en-us/articles/360000343908-How-to-Use-Your-Dashboard-to-Manage-Your-Shop
- **Etsy Stats + Search Visibility Page** (rekomendasi aksi per listing, pantau ≤24 jam) **[F]**: https://help.etsy.com/hc/en-us/articles/25869947521175-How-to-Use-the-Etsy-Search-Visibility-Page
- Notifikasi: email order, message buyer, reminder ship-by; histori buyer + pesan di halaman order **[F]**: https://help.etsy.com/hc/en-us/articles/10603291044967-Newly-Crafted-Etsy-Updates-for-Your-Shop

---

## 4. Perbandingan ringkas

| Dimensi | Temu | Shein | Etsy | Relevansi ZAFIRA |
|---|---|---|---|---|
| Skala | ±1,2B visits/bln; 246M MAU app | ±283M visits/bln; app #2 unduhan global | 86,5M buyers; $10,5B GMS | Bukti pola teruji skala; Etsy paling sekelas (niche, personalisasi) |
| Search/filter | Autocomplete agresif; filter generik | Filter fashion + size guide tersimpan | Filter lokal + Marketplace Insights | Filter ringan + panduan ukuran/kain; Insights versi admin sederhana |
| PDP | Kupon bertumpuk + countdown | Varian bertingkat + review foto + poin | Variations vs Custom options + add-on price | **Custom options monogram (inisial 3 char + counter)** |
| Cart/checkout | Ambang min-order kurang transparan | Kupon field; multi-pay | Guest checkout; estimasi tanggal | Concierge 3-step + estimasi janji; tanpa akun wajib |
| Trust | Kebijakan return terstruktur | Return+QC 30 hari | Purchase Protection + Star Seller | Nomor referensi + status transparan (closure Shneiderman) |
| Dark pattern | Terbukti (denda €200M DSA) | Terbukti (CPC 2025) | Minimal terdokumentasi | Tolak semua pola urgensi palsu |

## 5. Adopt / Adapt / Reject untuk ZAFIRA MVP

**ADOPT (langsung, effort S)**
1. Pola **Etsy Custom options** untuk monogram: field terstruktur (inisial teks + list font/benang), required/optional, batas karakter + counter `[2/3]`, lampir di ringkasan concierge. (S)
2. **Guest concierge flow** tanpa wajib akun (Baymard: 18% abandon karena wajib akun). (S)
3. Foto produk finished-item + badge jelas; estimasi tanggal di tiap tahap (Nielsen H1, Shneiderman closure). (S)
4. Tombol tutup + Escape + klik backdrop di semua modal/drawer (Nielsen "emergency exits"). (S)
5. Validasi saat blur + pesan error ramah, bukan kode mentah (Nielsen H5/H9). (S)

**ADAPT (sesuaikan luxury, effort M)**
6. Filter katalog ringan (kategori/kain/warna) ala Shneiderman mantra; tanpa kepadatan mass-market. (M)
7. Wishlist → "concierge shortlist" tersimpan di `localStorage` + ingat preferensi (Cooper: polite software). (M)
8. Status concierge transparan: `diterima → diproses atelier → siap → selesai` + nomor referensi (ganti state machine order). (M)
9. Admin: tiru Shop Manager ringkas — Orders(requests), filter status, Stats kunjungan/konversi sederhana. (M)
10. Review/testimoni kurasi + foto asli (tanpa poin/gamifikasi). (S/M)

**REJECT (jangan copy)**
- Countdown palsu, stok palsu, diskon coret fiktif, spin-wheel/gamifikasi wajib, confirm-shaming, nagging (temuan CPC/BEUC + denda DSA — §1.5/§2.5).
- Kepadatan mass-market, infinite scroll agresif, kupon bertumpuk (merusak visceral luxury Norman).
- Wajib akun sebelum lihat harga/reservasi; biaya tersembunyi hingga checkout (Baymard: 40% abandon karena extra costs: https://baymard.com/lists/cart-abandonment-rate).

---
*Dokumen bukti, bukan instruksi. Klaim **[U]** wajib diverifikasi sebelum jadi requirement. Peta teori HCI di §1.2/2.2/3.2 bersifat interpretif.*
