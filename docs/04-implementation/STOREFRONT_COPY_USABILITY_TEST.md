# Rencana Uji Kejelasan Teks Toko dengan Pengguna Nyata

**Status:** KIT SIAP — SESI BELUM DIJALANKAN
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/uji-kejelasan-teks-dengan-pengguna-nyata`
**Sumber Teks yang Diuji:** `STOREFRONT_TEXT_REWRITE_LOG.md` (E-01–E-10)
**Tanggal Kit:** 2026-10-20

> **Kejujuran:** Dokumen ini adalah *kit siap jalan*, bukan laporan hasil. Belum ada satu pun
> partisipan yang direkrut, belum ada sesi yang dijalankan, dan seluruh tabel hasil di bawah
> sengaja berstatus **BELUM DIJALANKAN**. Agent tidak boleh merekrut pengguna nyata dan tidak
> boleh mengarang data partisipan. Operator manusialah yang menjalankan sesi dan mengisi tabel ini.

---

## 1. Tujuan

Menguji apakah teks permukaan toko yang telah ditulis ulang (E-01–E-09) dipahami oleh pengguna
tanpa bantuan, sebelum perubahan dianggap final. Tujuan diikat langsung ke frasa "Sesudah" pada
`STOREFRONT_TEXT_REWRITE_LOG.md`:

| ID | Frasa "Sesudah" yang diuji | Pertanyaan yang harus terjawab |
|---|---|---|
| E-01 | `ZAFIRA — La Maison de Lumière · Haute Couture` | Apakah identitas brand terbaca tanpa label versi internal? |
| E-02 | `menunggu pembayaran` | Apakah pengguna paham pesanan belum dibayar dan menunggu pelunasan? |
| E-03 | `Kode pelacakan` | Apakah pengguna paham "kode pelacakan" untuk melacak pesanan tamu? |
| E-04 | `Tas Belanja` | Apakah istilah konsisten dan tidak ambigu dibanding istilah lama? |
| E-05 | `Buka Ruang Klien` | Apakah tombol portal dipahami tanpa path teknis `/akun`? |
| E-06 | `Tas belanja versi lama di peramban ini` | Apakah pesan migrasi tas lama dipahami? |
| E-07 | `Potongan voucher dan total akhir dipastikan saat pesanan dibuat.` | Apakah pengguna paham kapan voucher dihitung, tanpa jargon server? |
| E-08 | `Pantau dan kelola pesanan koleksi ZAFIRA Anda.` | Apakah kalimat terbaca alami tanpa kata "nyata"? |
| E-09 | `Pita Emas` | Apakah opsi personalisasi pita dipahami? |

E-10 tidak diubah (skor 2, menunggu keputusan peran elemen) sehingga **tidak diuji** di sini.

## 2. Kriteria Partisipan

5 partisipan, dibagi dua persona:

| Persona | Jumlah | Kriteria |
|---|---|---|
| Klien VIP (persona "Madame Vivienne") | 3 | Pernah berbelanja barang mewah; nyaman membaca bahasa campur Prancis; ekspektasi layanan eksklusif tinggi. |
| Tamu pertama kali | 2 | Belum pernah melihat situs ZAFIRA; berbelanja daring sesekali; bahasa utama Indonesia. |

Rekrutmen adalah wewenang operator (manusia). Jangan gunakan anggota tim internal sebagai
partisipan kecuali memenuhi kriteria di atas dan dicatat sebagai pengecualian.

## 3. Persetujuan & Privasi

- Sebelum sesi, bacakan **pernyataan persetujuan** dan minta persetujuan lisan yang direkam dalam
  catatan sesi.
- Data yang dicatat: inisial, persona, skor paham, dan transkrip singkat jawaban. **Tidak** dicatat:
  nama lengkap, email, telepon, atau isi keranjang pribadi.
- Hasil hanya dipakai internal ZAFIRA untuk perbaikan teks. Kutipan anonim, tanpa nama.

**Pernyataan persetujuan (dibacakan):**

> "Sesi ini untuk menguji kejelasan tulisan di situs ZAFIRA, bukan menguji Anda. Saya akan meminta
> Anda mencoba beberapa hal dan menjawab pertanyaan. Jawaban tidak dinilai benar-salah secara
> pribadi, dan hasil hanya dipakai untuk memperbaiki tulisan. Saya hanya mencatat inisial dan
> jawaban, tanpa nama atau kontak Anda. Boleh berhenti kapan saja. Setuju?"

## 4. Persiapan Operator & Naskah Moderator

Sediakan 30 menit per orang. Gunakan staging/prototype terisolasi dengan bahasa Indonesia,
produk uji tersedia, voucher uji, dan akun VIP uji. Jangan gunakan produksi, uang, atau data pribadi.
Siapkan kontak/alamat fiktif yang ditentukan operator dan pesanan tamu uji beserta kode pelacakannya.
Jangan menaruh kode pelacakan atau kredensial dalam catatan/repo; kode memberi akses pesanan.
Siapkan profil peramban berisi tas lama agar E-06 benar-benar tampil, serta halaman pilihan pita.
Catat URL staging, commit, tanggal, perangkat, moderator, dan bahasa pada lembar sesi.
Bila prasyarat gagal, tandai tugas TERBLOKIR; jangan menebak jawaban atau menghitungnya sebagai lulus.
Jangan rekam layar/suara tanpa persetujuan terpisah. Gunakan P1–P5, bukan inisial nyata.
Simpan catatan anonim pada penyimpanan internal terbatas; hapus setelah 30 hari atau sesuai persetujuan yang lebih singkat.


1. **Sapa & persetujuan** — baca pernyataan persetujuan di bagian 3.
2. **Penjelasan singkat** — "Kami memperbaiki beberapa tulisan. Saya ingin tahu mana yang jelas
   dan mana yang membingungkan. Tolong berpikir lantang (*think aloud*)."
3. **Jalankan skenario** satu per satu (bagian 5). Biarkan partisipan mencari sendiri; jangan
   memberi petunjuk arah.
4. **Ajukan pertanyaan pemahaman** (bagian 6) setelah tiap skenario selesai.
5. **Tutup** — tanyakan satu pertanyaan terbuka: "Adakah tulisan yang terasa janggal?" lalu ucapkan terima kasih.

Aturan moderator: jangan menyebut istilah yang sedang diuji sebelum partisipan mengucapkannya;
catat jawaban verbatim seperlunya; jangan menyalahkan partisipan.

## 5. Skenario Tugas

| # | Tugas | Frasa yang diamati |
|---|---|---|
| S1 | Temukan satu tas/produk dan masukkan ke keranjang. | E-04 `Tas Belanja`, `Pembelian katalog` |
| S2 | Selesaikan checkout sampai layar konfirmasi, lalu jelaskan status pesanan. | E-02 `menunggu pembayaran`, E-07 voucher |
| S3 | Sebagai tamu, temukan cara melacak pesanan tanpa akun. | E-03 `Kode pelacakan` |
| S4 | Jelaskan arti catatan tentang voucher di halaman checkout. | E-07 `Potongan voucher dan total akhir dipastikan saat pesanan dibuat.` |
| S5 | Pilih warna pita kemasan untuk pesanan Anda. | E-09 `Pita Emas` / `Pita Safir` |

Skenario tambahan (diamati saat partisipan menavigasi, bukan tugas terpisah):
- E-01 (title beranda), E-05 (`Buka Ruang Klien`), E-06 (pesan tas lama), E-08 (kalimat pantau).

## 6. Pertanyaan Pemahaman & Kriteria Lulus

Setiap pertanyaan diskor 1 (paham) / 0 (tidak paham). Skor lulus per peserta: **≥ 4 dari 5** benar.

| # | Pertanyaan (tertulis) | Jawaban yang dianggap paham | Kunci frasa |
|---|---|---|---|
| Q1 | "Setelah checkout, status 'menunggu pembayaran' artinya apa?" | Pesanan belum dibayar DAN belum bisa bayar online. | E-02 |
| Q2 | "Apa gunanya 'kode pelacakan'?" | Untuk melacak pesanan tamu tanpa akun. | E-03 |
| Q3 | "Kapan voucher dipotong dari total?" | Saat pesanan dibuat. | E-07 |
| Q4 | "Mana pita yang Anda pilih dan warnanya apa?" | Menyebut Emas atau Safir dengan benar. | E-09 |
| Q5 | "Apa bedanya 'Tas Belanja' dengan isi tas lama?" | Tas Belanja = keranjang aktif; tas lama = data versi lama yang tidak tersambung. | E-04/E-06 |

## 7. Tabel Pencatatan Hasil

**Status: BELUM DIJALANKAN** — belum ada partisipan yang direkrut atau sesi yang dijalankan.

| Partisipan | Persona | Q1 | Q2 | Q3 | Q4 | Q5 | Skor (/5) | Lulus (≥4)? | Catatan kualitatif |
|---|---|---|---|---|---|---|---|---|---|
| P1 | VIP | BELUM DIJALANKAN | | | | | | | |
| P2 | VIP | BELUM DIJALANKAN | | | | | | | |
| P3 | VIP | BELUM DIJALANKAN | | | | | | | |
| P4 | Tamu | BELUM DIJALANKAN | | | | | | | |
| P5 | Tamu | BELUM DIJALANKAN | | | | | | | |

**Rekap (diisi setelah sesi):**

| Metrik | Target | Hasil |
|---|---|---|
| Jumlah partisipan lulus (≥4/5) | ≥4 dari 5 | BELUM DIJALANKAN |
| Pertanyaan paling sering gagal | n/a | BELUM DIJALANKAN |
| Frasa yang paling sering membingungkan | n/a | BELUM DIJALANKAN |

## 8. Aturan Keputusan

Setelah sesi selesai dan tabel terisi, operator memakai aturan ini:

| Hasil | Tindakan |
|---|---|
| Semua 5 sesi lengkap; ≥4 partisipan lulus dan setiap Q dijawab benar oleh ≥4/5 | Pertahankan frasa yang diuji sementara; sampel kecil bukan bukti statistik seluruh pelanggan. |
| Satu peserta menyangka pembayaran online tersedia atau pembayaran otomatis terkonfirmasi | Prioritaskan revisi E-02 sebelum menyatakan teks siap; skor total tidak meniadakan risiko ini. |
| Sebuah Q benar pada <4/5 peserta, atau hanya 3 peserta lulus | Revisi frasa terkait berdasarkan jawaban verbatim, lalu uji ulang. |
| <3 partisipan lulus | Evaluasi ulang keseluruhan teks sebelum revisi dan uji ulang. |
| Sesi tidak lengkap atau tugas terblokir | Keputusan ditunda; lengkapi sesi, jangan mengisi skor perkiraan. |
| Temuan di luar E-01–E-09 | Catat terpisah; jangan dicampur dengan skor lulus teks yang diuji. |

Untuk E-01, E-05, E-08, dan E-09, catat temuan observasi secara kualitatif; lima Q tidak
memberi skor tersendiri pada semua frasa. Jangan menyebut seluruh E-01–E-09 terbukti jelas hanya
dari skor gabungan.

Setiap perubahan akibat uji ini wajib: (a) diperbarui di `STOREFRONT_TEXT_REWRITE_LOG.md`,
(b) ditambahkan ke `docs/README.md` jika membuat berkas baru, dan (c) dicatat di `COUNCIL_DECISIONS.md`
sebagai keputusan tindak lanjut.
