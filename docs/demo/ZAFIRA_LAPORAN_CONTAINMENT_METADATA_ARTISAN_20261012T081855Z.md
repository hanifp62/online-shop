# Laporan containment metadata artisan AMADIS

Tanggal: 2026-10-12 (UTC). Cakupan: satu file metadata. Laporan ini tidak memuat nilai sensitif.

## Status

**PASS terbatas.** Turunan baru untuk satu metadata sudah dibuat dan diverifikasi. Status ini **tidak** menyatakan seluruh evidence di root aman.

## Identitas root kanonik

| Pemeriksaan | Hasil |
|---|---|
| Root | `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-ceaa040dea9a6f39f8296b4b` |
| Path kanonik, bukan symlink | PASS |
| Pemilik root = UID proses, mode `0700` | PASS |
| `identity.json`: file reguler, `nlink=1`, mode `0600` | PASS |
| `identity.root`, `identity.database`, `identity.repo` sesuai | PASS |
| `identity.git_head` sesuai HEAD saat audit | PASS |
| SHA-256 `identity.json` | `70991798cf98d393c675d1f723682afcfe7d42b589a3c5e0729f9c76048f4485` |

## Source asli (dipertahankan)

| Atribut | Nilai |
|---|---|
| Path | `<root>/evidence/20261012T032202Z-artisan-3ab3eb.json` |
| SHA-256 | `9f61c418b1362c6e5705744dedd372dfc2cd33a27443b2ac7097678e025b8981` |
| mtime UTC | `2026-10-12T03:22:03Z` |
| Mode | `0600` (sudah privat; tidak diubah) |
| Isi/mtime berubah | Tidak |

Temuan: field `command` dalam source asli memuat satu kredensial demo dalam sintaks array PHP. Nilainya tidak dicetak.

## Sanitizer dan turunan

| Atribut | Nilai |
|---|---|
| Sanitizer | `scripts/amadis/launch.py` (`sanitized`, `sensitive`), diimpor sebagai fungsi |
| SHA-256 sanitizer | `049348f1db3f61422e4a44642309bd54df9ce0fa1bb3bba5b4a9a2299e90ad8c` |
| Run output | `<root>/containment-20261012T081855Z-b79aa3f4/` (mode `0700`) |
| Turunan | `derivative.json`, SHA-256 `f8b33cb19f48c8e6886f4f2e333d3c9d8b425f7ace6441ca23e1215898753c3a`, mode `0600` |
| Manifest | `manifest.json`, SHA-256 `56440b4252f900adc6e37dcf382837ba0ba936096eea358b06eca0e26c17af0e`, mode `0600` |
| Jumlah redaksi | 1 |

Metode: JSON di-decode, lalu `sanitized()` dijalankan pada tiap string dan `sensitive()` pada tiap key. Hasil di-encode ulang.

## Pemeriksaan

| Pemeriksaan | Hasil |
|---|---|
| Nilai kandidat sensitif ditemukan secara privat | 1 |
| Nilai kandidat muncul di turunan (bentuk mentah/escaped JSON) | 0 — PASS |
| Isi run hanya `derivative.json` dan `manifest.json` (allowlist) | PASS |
| `sanitized()` pada teks JSON mentah meredaksi nilai | **Tidak** — lihat temuan |

## Temuan sanitizer

Penerapan `sanitized()` pada teks JSON mentah tidak meredaksi kredensial ini. Penyebabnya, kutip ganda di dalam string JSON di-escape (`\"`). Pola array PHP lalu tidak cocok. Turunan pertama sesi ini memakai cara itu dan gagal verifikasi praktis. Run `containment-20261012T081810Z-cdce343d` sudah dihapus. Penggantinya adalah run di atas.

Implikasi: metadata `.json` lain dari `capture()` dengan pola argumen sejenis mungkin juga memuat nilai tidak teredaksi. `launch.py` tidak diubah, sesuai larangan mengubah source.

## Batas

- Hanya satu file metadata yang dicakup. File `.stdout`, `.stderr`, dan metadata lain tidak diaudit.
- Verifikasi hanya mencakup pola yang dikenali sanitizer. Format kredensial lain bisa lolos.
- Tidak membuka `.env`, DB, `demo-key`, atau cookie. Tidak menjalankan PHP, server, atau launcher. Tidak mengubah state bisnis atau git.
- Kredensial demo pada source asli tetap ada. Rotasi atau penghapusan perlu keputusan pemilik.
