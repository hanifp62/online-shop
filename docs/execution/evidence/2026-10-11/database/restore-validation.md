# Validasi restore database uji — 11 Oktober 2026

## Server
- MariaDB 10.4.28 (XAMPP), socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, port 3306. Dump dideklarasikan dari MariaDB 10.4.32.
- `mysql@8.4` Homebrew sempat dinyalakan, gagal bind (port 3306 dipakai XAMPP), lalu dihentikan lewat `brew services stop`. Tidak ada data diubah.

## Dump dipilih
`database/zafira_ecommerce_20260921.sql`, 131041 byte, sha256 `3daec2004e10c03acbb50a661e1602e9e166d8cb38281acd685a21dfe63177a1`.
15 CREATE TABLE, tanpa `USE`. Dump `_chat` memiliki `CREATE DATABASE` (dibuang dari input jika dipakai). Semua dump sumber tidak diubah. Metadata lengkap: `dump-metadata.json`.

## Target
Database baru `zafira_restore_test_20261011` (utf8mb4_unicode_ci). DB aplikasi `ecommerce` tidak ditimpa (hanya dibaca untuk hitung baris).

## Hasil
| Pemeriksaan | Hasil |
|---|---|
| Impor | 15 tabel dibuat, 28 indeks, 3 foreign key |
| Prosedur `getcat` | **GAGAL** `ERROR 1558`: kolom `mysql.proc` server XAMPP 20 vs 21 (tabel sistem server belum di-upgrade). Prosedur tidak dipakai aplikasi (Eloquent). Tidak menjalankan `mysql_upgrade` pada server bersama. |
| Jumlah baris vs `ecommerce` | users 1/1, products 12/12, categories 8/8, admins 1/1, newsletter 1/1, migrations 17/17 |
| Hash isi (mysqldump terurut) vs `ecommerce` | concierge_requests dan user_info_backups sama (kosong); users, products, categories, newsletter, admins, migrations **berbeda** — dump 2026-09-21 bukan salinan identik DB aktif. Penyebab perbedaan (tanggal, nilai) belum diselidiki sampai tingkat baris; isi tidak dicetak. |
| `migrate:status` pada DB uji | semua Ran (batch 1–3), 0 pending |
| Foreign key orphan | produk tanpa kategori: 0 |
| HTTP lewat Laravel (`DB_DATABASE` di-override, port 8012) | `/` 200, `/produk/celeste-trench` 200, `/sitemap.xml` 200 (13 `<url>`), `/login` 200, `/admin` 302 → `/admin/login` |
| POST newsletter valid + CSRF | 200, baris bertambah di DB uji (1→2), DB `ecommerce` tetap 1 |
| POST newsletter email invalid | 422 |
| POST newsletter tanpa CSRF | 419 |
| POST concierge valid | 201, baris di DB uji (1), DB `ecommerce` tetap 0; mail driver `log`, queue `database` (jobs=2 di DB uji) |

Batas: tidak menguji login admin/pelanggan (kredensial tidak dibuka), tidak mengirim email nyata.
