# Kriteria Penerimaan Spesifik (Acceptance Criteria)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal dasar:** 2026-10-10; **revisi:** 2026-10-21 — REM-20261021 DOC

**Status aktif:** Semua AC-REM di bawah **NOT RUN / PENDING REVIEW** pada revisi ini; keberadaan source/tes bukan acceptance lulus.

## 1. Kontrak aktif: kepemilikan dan tiga alur terpisah

Mengacu pada [PRD revisi REM](../02-product/PRD.md), [kontrak REM](REMEDIATION_REAL_WORLD_2026-10-21.md), dan [crosswalk kode/tes/hasil/reviewer](REQUIREMENT_TRACEABILITY.md). ID AC-REM adalah identitas kriteria lokal, bukan task atau status plan server.

| ID / requirement | Skenario penerimaan yang wajib dibuktikan |
|---|---|
| **AC-REM-OWN** — FR-008/204, FR-REM-001/004 | Pengguna terautentikasi membuka `/akun`: hanya inquiry dengan `user_id` miliknya tampil. Guest/null owner dan akun lain ber-email sama dikecualikan; owner dengan email berbeda tetap dapat membaca record miliknya. `/orders/{id}` milik orang lain ditolak. Klaim guest bukan fallback email dan memerlukan kontrak verifikasi tersendiri; tidak dinyatakan sudah tersedia. |
| **AC-REM-CART** — FR-004, US-003, revisi batas FR-201 | `POST /cart/items` menyimpan produk/kuantitas; harga dari klien tidak dipercaya. `size`/`monogram` ditolak pada batas purchase, tidak diam-diam menjadi varian. Pembacaan/delta/hapus `/cart` konsisten dengan `carts`/`cart_items`; localStorage legacy bukan sumber transaksi. |
| **AC-REM-ORDER** — FR-REM-002, FR-004 | `POST /checkout` dengan data/alamat sah membuat satu order beserta snapshot item, reservasi dan riwayat awal; order baru mengosongkan cart dalam transaksi. Replay identik mengembalikan order yang sama; payload konflik ditolak tanpa order/stok ganda. Kegagalan tidak menghilangkan cart; `pending` tidak berarti lunas. `GET /checkout` redirect, bukan submit concierge. |
| **AC-REM-INQUIRY** — FR-005/203, US-004 | `POST /concierge` tervalidasi membuat `concierge_requests` dan mengantrekan email, tidak membuat `orders`/`order_items` atau membersihkan cart pembelian. UI mengirim ukuran/monogram sebagai catatan inquiry; payload legacy `order_items` hanya ringkasan catatan. Konfirmasi menyatakan inquiry diterima, bukan pembayaran atau delivery email nyata. |

## 2. Acceptance fungsional REM

| ID / requirement | Kriteria terukur / skenario negatif |
|---|---|
| **AC-REM-F01** — FR-REM-001 | Link reset/verifikasi tidak mengikuti Host tak tepercaya; email array menghasilkan validasi, bukan 500. Recovery terikat owner, kedaluwarsa dan sekali pakai; ganti password mencabut sesi lain yang relevan; batas TOTP mengikuti akun lintas IP; enrolment bersaing tidak menimpa pemenang; email belum terverifikasi tidak memberi hak ekspor/hapus subscriber. |
| **AC-REM-F02** — FR-REM-002 | Challenge→accept dan settlement berulang tidak menggandakan paid; pembayaran kedua berbeda tetap anomali. Cancel paid/processing ditolak tanpa mutasi, cancel pending melepaskan stok/voucher sekali. Retur/refund memakai saldo bersih snapshot `subtotal−discount+tax`, tidak otomatis mengembalikan ongkir; penolakan/replay tidak menggandakan alokasi. Provenance parsial/tidak konsisten memerlukan rekonsiliasi. Retry bersarang diteruskan ke pemilik transaksi luar; expiry mengunci/recheck order, reservation dan hold; label shipment terminal sesuai state. |
| **AC-REM-F03** — FR-REM-003 | Target backup mengikuti konfigurasi koneksi efektif beserta transport yang didukung; secret tidak di argv/log. Retensi invalid gagal sebelum penghapusan. Snapshot SQLite invalid tidak menggusur backup valid terakhir, meskipun hash/gzip sah. Bukti snapshot dibandingkan dengan snapshot, bukan DB hidup pada waktu berbeda. Restore MySQL/MariaDB tetap gate terpisah, bukan disimpulkan dari fake/hash. |
| **AC-REM-F04** — FR-REM-004 | Hapus akun melakukan lock/recheck kewajiban; nilai IDR integral dan panjang URL gambar konsisten dengan skema. Migrasi uang `000050` preflight seluruh kolom sebelum narrowing DDL; `000060` tidak backfill provenance dan rollback menolak setiap data provenance non-null. Akses produk nonaktif dibatasi, retensi/PII eksplisit. Pengujian engine target dengan writers dihentikan wajib untuk rollback lintas tabel. |
| **AC-REM-F05** — FR-REM-005 | Favorit/search/locale konsisten; mutasi cart/admin terserialisasi dan respons usang tidak menimpa state baru. Timeout mempertahankan idempotency key untuk retry. Simpan gagal mempertahankan draft; simpan berhasil tetapi refresh gagal tidak diklaim refresh sukses. Fokus/Escape kembali ke pemicu; CSS prototype tidak mengubah viewport canonical. |
| **AC-REM-F06** — FR-REM-006 | Fixture memenuhi guard tanpa pengecualian lemah. Kegagalan substep, artefak, atau diagnosis membuat gate gagal; cleanup hanya process group milik runner. Mail SMTP gagal tidak dianggap berhasil lewat log. Runtime minimum sesuai dependency; konfigurasi branch protection mewajibkan approval independen, namun penerapan remote harus dibuktikan terpisah. |
| **AC-REM-F07** — FR-REM-007 | Inventaris TPS memisahkan INSERT/UPDATE/DELETE, retry dan satuan waktu; bobot asumsi bukan kapasitas terukur. `/admin/reports/operations-summary` memerlukan guard/TOTP/permission/feature flag dan no-store; jendela UTC `[from,to_exclusive)` ≤366 hari, snapshot vs event dibedakan. Metrik sumber tak tersedia bernilai unknown/null, bukan nol; keluaran bounded tanpa PII/SQL error, `best_effort`/partial dinyatakan. |

## 3. Acceptance nonfungsional REM

| ID / requirement | Gate dan batas kesimpulan |
|---|---|
| **AC-REM-N01** — NFR-REM-001; NFR-001 | Tetapkan workload, engine, cache state, unit/persentil latensi, ukuran aset, environment, SHA dan timestamp sebelum ukur. Rekonsiliasi target lama 50/100/150ms tanpa menurunkan bar diam-diam. Proyeksi TPS tidak membuktikan throughput; hasil ukur saat ini belum tersedia. |
| **AC-REM-N02** — NFR-REM-002 | Buktikan retry/failure, restore pada engine target, worker/scheduler dan drill staging dengan RPO/RTO. Jadwal di source atau backup ber-checksum bukan bukti continuous availability. |
| **AC-REM-N03** — NFR-REM-003; NFR-003 | Jalankan regresi negatif ownership, nominal/rounding, stok, idempotensi dan migrasi termasuk provenance nullable/rollback guard pada snapshot source terbaru; assertion tidak dilonggarkan. Engine parity tidak disimpulkan dari SQLite saja. |
| **AC-REM-N04** — NFR-REM-004; NFR-004/006/203 | Tujuh lensa HCI dan aksesibilitas diperiksa per alur; keyboard, fokus, Escape, viewport, kontras/target sentuh dan pembaca layar diuji dengan artefak. Unit/fake-DOM bukan sertifikasi WCAG 2.2 AA; yang tidak diuji tetap belum terbukti. |
| **AC-REM-N05** — NFR-REM-005 | Diff tetap terarah, batas cart/order/inquiry jelas, pola/dependency lama dipakai ulang dan tidak ada rewrite spekulatif. Reviewer independen memeriksa scope serta risiko regresi. |
| **AC-REM-N06** — NFR-REM-006 | Otorisasi, status/history, observability dan hasil gate konsisten; setiap klaim lulus menunjuk command, timestamp, SHA/diff, hasil, serta reviewer berbeda dari author. Tanpa reviewer/approver, tidak ada ACCEPTED atau penutupan plan. |

## 4. Arsip checklist storefront 10–19 Oktober — tersupersesi

**Semua `[x]` di arsip berikut merekam klaim lama, bukan hasil REM saat ini.** AC-004/005/008 yang mencampur cart, inquiry dan order digantikan AC-REM-CART/ORDER/INQUIRY/OWN serta F02/F05. Koreksi AC-005 bertanggal 19 Oktober juga salah bila diterapkan pada form concierge: `bookConcierge()` tetap membuat inquiry. Target `<50ms` belum memiliki bukti ukur baru.

### AC-001 (Beranda & Hero Brand ZAFIRA)
- [x] Beranda merender judul editorial "ZAFIRA: La Maison de Lumière".
- [x] Hero image beresolusi tinggi dimuat dengan prioritas `fetchpriority="high"`.
- [x] Kartu produk memuat foto, harga terformat rupiah (`Rp...`), nama kreasi, dan badge (misal: 'Eksklusif', 'Warisan Nusantara').
- [x] Respon server beranda dalam cache hit < 50ms.

### AC-002 (Filter Kategori Dinamis)
- [x] Pill kategori dirender berdasarkan data tabel `categories`.
- [x] Mengklik pill kategori memfilter kartu produk di halaman secara instan tanpa reload browser.
- [x] Pill kategori aktif memiliki aksen visual kontras yang jelas.

### AC-003 (Halaman Detail Produk & Kriya)
- [x] Akses ke `/produk/{slug}` menampilkan halaman detail produk dengan layout split elegan (foto besar di kiri, narasi dan tombol aksi di kanan).
- [x] Menampilkan data kriya lengkap: Material, Komposisi, Asal/Provenance, Keahlian/Craft, dan Petunjuk Perawatan.
- [x] Menampilkan 3 produk terkait (*related creations*) dari kategori yang sama.
- [x] Mengakses slug yang tidak terdaftar menghasilkan halaman 404 yang terstruktur.

### AC-004 (Tas Belanja & Personalisasi Monogram)
- [x] Mengklik tombol "Tambah ke Tas" membuka Cart Drawer dari sisi kanan layar.
- [x] Jumlah item tas belanja (*cart count badge*) ter-update secara otomatis di header.
- [x] Pengguna dapat menambahkan 2–3 huruf inisial monogram pada item di tas belanja; karakter non-alfabet otomatis dibersihkan.
- [x] Nilai subtotal dihitung secara real-time berdasarkan kuantitas masing-masing item.

### AC-005 (Alur Checkout Concierge)
- [x] Menekan tombol "Lanjut bersama Client Advisor" di tas belanja mengubah modal ke mode ringkasan pesanan.
- [x] Formulir checkout menampilkan ringkasan produk (nama kreasi × kuantitas + total harga).
- [x] Pengiriman formulir menyimpan pesanan ke `orders`/`order_items` dan mengosongkan isi tas belanja. *(Koreksi 2026-10-19: klaim lama `concierge_requests` sudah basi.)*
- [x] Menampilkan toast konfirmasi sukses berwarna emas safir.

---

## 5. Arsip checklist admin — bukan acceptance aktif

### AC-006 (Workspace Terpadu & Metrik Real-Time)
- [x] Panel admin memiliki sistem navigasi terpadu (Sidebar navigasi, top bar breadcrumbs, profil staf).
- [x] Kartu metrik KPI menampilkan angka riil untuk Total Pengguna, Arsip Audit, Pesanan Concierge, Subscriber Buletin, Total Produk, dan Kategori Koleksi.
- [x] Tampilan responsif pada perangkat desktop maupun tablet.

### AC-007 (Manajemen Produk Komprehensif)
- [x] Formulir tambah produk memungkinkan pengisian judul, kategori, harga, URL foto, badge, warna, deskripsi, dan seluruh narasi kriya.
- [ ] Jika slug produk dikosongkan, sistem secara otomatis menghasilkan slug unik yang ramah SEO. *(Belum terbukti: race condition dan update slug nullable.)*
- [x] Pengubahan atau penghapusan produk memicu pembersihan cache storefront secara otomatis.

### AC-008 (Manajemen Pesanan & Alur Status)
- [x] Admin dapat memfilter daftar permintaan concierge/pesanan berdasarkan status (`new`, `contacted`, `confirmed`, `completed`, `cancelled`) atau pencarian nama/email/nomor HP.
- [x] Admin dapat mengubah status pesanan melalui dropdown status dan menyimpan perubahannya.
- [x] Perubahan status langsung tercermin pada halaman `/akun` milik pelanggan yang bersangkutan.
