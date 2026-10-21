# Matriks Nada Bicara per Konteks Permukaan (Voice & Tone Matrix)

**Brand:** ZAFIRA — La Maison de Lumière  
**Terkait Task:** `baseline-codebase/gaya-bahasa-yang-mengikat/tetapkan-matriks-nada-bicara-per-konteks-permukaan`  
**Keputusan Arsitektur:** `COUNCIL_DECISIONS.md` #040  
**Tanggal Penetapan:** 2026-10-20  

---

## 1. Prinsip Inti Suara Brand (Brand Voice Pillars)

ZAFIRA berbicara sebagai kurator busana adibusana bernuansa Nusantara–Eropa: anggun, lugas, bersahaja, berbobot kriya.

| Pilar Suara | Artinya | Pantangan |
|---|---|---|
| **Elegansi Bersahaja (Quiet Luxury)** | Menghargai waktu pelanggan; memilih kata presisi, tidak berisik, tidak bombastis. | Tidak memakai istilah diskon murahan ("Banting harga", "Diskon gila", tanda seru berulang `!!!`). |
| **Kriya Autentik (Artisanal Integrity)** | Menamai bahan dan proses dengan terminologi nyata (sutra, ATBM, sulam tangan, tenun). | Jangan memakai klaim teknis palsu atau jargon pabrik massal yang menipu. |
| **Kejelasan Fungsional (Krug: "Don't Make Me Think")** | Instruksi langsung ke intinya; tombol menyatakan aksi yang akan terjadi. | Hindari kalimat pasif berputar-putar ("Klik di sini untuk melanjutkan proses pengiriman formulir"). |
| **Adab & Penghormatan (Polite Software / Cooper)** | Menghargai pilihan dan data pengguna; meminta maaf jika sistem gagal tanpa menyalahkan pengguna. | Jangan menyalahkan pengguna ("Anda salah memasukkan kode", "Data tidak valid!"). |

---

## 2. Matriks Nada Bicara Berdasarkan Konteks Permukaan

| Konteks / Permukaan | Nada Bicara (Tone) | Persona Pendengar | Contoh Direkomendasikan | Contoh Dilarang |
|---|---|---|---|---|
| **1. Beranda & Etalase Publik (Storefront)** | Editorial, puitis terukur, hangat, inspiratif. | Madame Vivienne (Klien VIP mengapresiasi keindahan) | *"Koleksi adibusana sutra tenun tangan, dirajut untuk kilau abadi."* | *"Beli sekarang produk terlaris minggu ini, promo terbatas!"* |
| **2. Alur Checkout & Pembayaran** | Lugas, menenangkan, transparan, presisi tinggi. | Pembeli yang menuntut kepastian finansial | *"Ringkasan pesanan Anda. Total biaya sudah mencakup pajak dan kurir berasuransi."* | *"Awas kehabisan! Selesaikan checkout dalam 5 menit."* |
| **3. Notifikasi Transaksional (Email/WA)** | Sopan-formal, bersahabat, informatif dengan nomor referensi jelas. | Pelanggan menunggu konfirmasi | *"Pesanan #ORD-2026-0001 sedang dipersiapkan oleh artisan kami dengan estimasi pengiriman 2 hari kerja."* | *"Transaksi berhasil bro, tunggu barangnya sampai ya."* |
| **4. Layanan Concierge & Kriya Kustom** | Perhatian personal, melayani, menghormati privasi. | Klien kustom/Bespoke | *"Konsultan atelier kami siap menyelaraskan siluet busana dengan preferensi monogram Anda."* | *"Isi form ini kalau mau custom, nanti dihubungi CS."* |
| **5. Konsol Admin Atelier (Backoffice)** | Efisien, netral, berbasis data, tanpa basa-basi (Engelbart). | Artisan / Operator Atelier | *"Stok tersisa 3 unit. Batas ambang minimum tercapai."* | *"Hore, pesanan baru masuk! Jangan lupa diproses ya."* |
| **6. Pesan Error & Validasi Formulir** | Konstruktif, membantu pemulihan cepat (Nielsen H9). | Pengguna yang mengalami kendala teknis | *"Format nomor telepon memerlukan 10–13 digit angka, contoh: 08123456789."* | *"Nomor salah! Format tidak dikenali oleh server."* |

---

## 3. Glosarium Terminologi Wajib vs Dilarang

| Konsep | Terminologi Wajib | Terminologi Dilarang / Dihindari |
|---|---|---|
| Toko fisik/dapur produksi | *Atelier*, *Maison ZAFIRA* | Pabrik, workshop konveksi |
| Pengrajin | *Artisan*, *Penenun*, *Perajin* | Buruh, tukang jahit |
| Barang/Busana | *Koleksi*, *Busana*, *Karya Kriya* | Produk murah, barang obral |
| Keranjang Belanja | *Tas Belanja*, *Kantong Kriya*, *Keranjang* | Troli, keranjang diskon |
| Tombol Simpan/Konfirmasi | *Konfirmasi Pesanan*, *Jadwalkan Janji Temu* | Submit, Kirim data, Klik ini |
| Peringatan Galat | *Mohon periksa kembali...*, *Silakan masukkan...* | Error!, Anda salah!, Salah input! |

---

## 4. Evaluasi & Gerbang Kepatuhan

- **Automated Check:** `VoiceAndToneConsistencyTest` memeriksa bahwa tidak ada kata pantangan promosi murah (`diskon gila`, `banting harga`, `klik di sini`) di file Blade publik (`resources/views`).
- **Human Review:** Seluruh salinan mikrocopy baru wajib dirujuk silang dengan matriks di atas sebelum digabung ke cabang utama.
