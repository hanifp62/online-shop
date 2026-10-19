# Final acceptance — final2

## Verdict: BELUM DITERIMA

Semua 16 perintah pada `regression-results-final2.json` keluar 0 setelah perubahan sumber terakhir. Namun, exit 0 pemeriksa kontras bukan bukti penerimaan aksesibilitas penuh.

## Eksekusi

`run-final2.py` menyimpan `.cmd`, `.log`, dan `.exitcode` untuk tiap langkah:

| Langkah | Exit |
|---|---:|
| php-standard-final2 | 0 |
| php-random-final2 (seed 20261012) | 0 |
| pint-final2 | 0 |
| build-final2 | 0 |
| node-tests-final2 | 0 |
| php-syntax-final2 | 0 |
| js-syntax-final2 | 0 |
| sweep-15-final2 | 0 |
| header-breakpoint-final2 | 0 |
| extra-browser-final2 | 0 |
| overlay-final2 | 0 |
| drawer-interactions-final2 | 0 |
| hit-collision-final2 | 0 |
| contrast-final2 | 0 |
| dark-contrast-final2 | 0 |
| diff-check-final2 | 0 |

Sweep: 56 kasus, 56 PNG di `shots-sweep-15-final2/`, nol kegagalan otomatis, overflow maksimum 0. Seluruh 56 PNG final: **NOT RUN untuk inspeksi visual langsung**. Satu crop diagnostik lama `dark-crop-390-editorial-content.png` dibuka langsung; crop bukan screenshot hasil akhir.

Empat artefak security-run11 diformat: `sessions.php`, `seed.php`, `router.php`, `test_throttle.php`. Pint seluruh repo dan pemeriksaan sintaks PHP termasuk direktori tersebut keluar 0. `pint-initial` tetap utuh.

## Kontras: bukti dan batasannya

Bootstrap membaca `zafira-theme`, sedangkan mesin tema bersama membaca `zafira_theme`. Pemeriksa sekarang mengisi keduanya. Hasil mencatat `dark/dark`; tidak lagi memaksa atribut DOM setelah halaman dimuat. Persistensi satu kunci saja belum diperbaiki atau dibuktikan.

| Kandidat | Klasifikasi | Sebelum | Sesudah |
|---|---|---|---|
| .button-light | CONFIRMED: teks hampir putih pada putih | 1.16 | Tidak dicatat per elemen; tidak muncul dalam daftar gagal akhir |
| .eyebrow / Recherche | CONFIRMED: sapphire gelap pada panel gelap | Kandidat sekitar 1.29; nilai per elemen belum direkam | Tidak dicatat per elemen |
| Heading packaging/gallery prototype | CONFIRMED: warna inline gelap pada latar gelap | 1.00 | Tidak dicatat per elemen |
| Product badges | CONFIRMED: teks terang pada badge terang | Sekitar 1:1 dalam probe | Tidak dicatat per elemen |
| Editorial lede mobile | CONFIRMED dari crop: teks keluar area foto dan jatuh di latar terang | 1.16 pada probe pixel | Tidak dicatat per elemen |
| .lang-divider | FALSE POSITIVE untuk persyaratan teks: pemisah dekoratif | 2.27–2.28 | Dikecualikan |
| Depuis 2026 | BELUM TUNTAS: teks informatif dikecualikan checker tanpa dasar memadai | 2.46–2.53 | Dikecualikan; BUKAN bukti lulus |

Pemeriksa hanya menyimpan daftar gagal, bukan rasio semua elemen lulus. Tabel sebelum/sesudah lengkap belum tersedia. Sampling memakai persentil 10% area kotak, bukan seluruh latar tepat di belakang glyph; tidak cukup untuk sertifikasi kontras. Opasitas grup dan pseudo-element belum seluruhnya dimodelkan. Pemeriksaan non-teks >=3:1 belum lengkap. Concierge mobile dilewati karena opener pertama tersembunyi. Status skip tidak menyebabkan exit nonzero.

## Perubahan

- `resources/css/app.css`: aturan dark untuk tombol, eyebrow, badge, marquee, ikon tambah, salinan monogram, scrim foto, dan posisi editorial mobile.
- `public/prototype/app.css`: override dark untuk heading packaging dan editorial-flow dengan warna inline.
- Perubahan CSS preview/ornamen juga terjadi; pemeriksaan visual akhir masih diperlukan agar warna foil pilihan pengguna tidak terganggu.

## Batas proses

Batas maksimal tiga percobaan untuk blocker kontras terlampaui selama diagnosis. Beberapa log diagnostik ditimpa; riwayat percobaan lengkap tidak tersedia. Jangan menganggap hasil ini memenuhi seluruh kontrak closeout.

Tidak ada commit, push, deploy, perubahan .env, atau operasi database destruktif yang dilakukan dalam kelanjutan ini. Browser memakai 8015.
