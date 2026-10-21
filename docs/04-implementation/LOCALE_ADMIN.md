# Lokalisasi Konsol Admin ZAFIRA

## Tujuan

Konsol admin memakai katalog khusus `admin_ui` agar seluruh teks yang terlihat pengguna dapat diterjemahkan tanpa mencampur istilah antarmuka admin dengan katalog storefront. Locale yang didukung adalah `id`, `en`, `fr`, `it`, dan `zh`.

## Struktur katalog

Setiap locale memiliki file `lang/{locale}/admin_ui.php`. Struktur kunci harus sama di semua locale. Katalog dibagi menjadi:

- `common`: navigasi, kontrol tabel, label umum, aksesibilitas, dan pagination.
- `login`: halaman masuk admin.
- `totp`: verifikasi dua langkah.
- `enrol`: aktivasi verifikasi dua langkah.
- `recovery`: kode pemulihan.
- `events`: log autentikasi.
- `dashboard`: katalog, produk, order, pengguna, arsip audit, concierge, subscriber, password, dan status.
- `flash`: pesan hasil aksi dan error yang ditampilkan controller.

Gunakan `__('admin_ui.<key>')` pada Blade dan controller. Placeholder seperti `:title`, `:id`, `:email`, `:status`, dan `:seconds` harus dipertahankan. Nama brand, route, kode status, identifier database, dan data customer atau produk tidak diterjemahkan.

## Locale pada view

Semua view admin menetapkan atribut berikut pada elemen root HTML:

```blade
<html lang="{{ app()->getLocale() }}">
```

Katalog `id` mempertahankan teks Bahasa Indonesia yang sebelumnya tampil pada admin. Test `AdminLocaleTest` memeriksa keberadaan file, parity kunci rekursif, nilai non-kosong, locale pada HTML, dan tidak adanya kunci ter-render mentah.

## Resolusi locale

Locale ditentukan oleh middleware locale aplikasi dari query string `?locale=`, session `locale`, atau fallback konfigurasi aplikasi. Guard admin tidak mempunyai kolom `locale` pada tabel `admins`, sehingga preferensi locale belum disimpan per akun.

Dampaknya, locale pada sesi browser berlaku untuk sesi tersebut dan dapat berubah saat pengguna memilih locale baru. Menambah kolom locale atau menyimpan preferensi akun memerlukan perubahan schema, kontrak autentikasi, dan migrasi terpisah.

## Verifikasi

Jalankan test terarah berikut setelah mengubah katalog atau view admin:

```bash
php artisan test tests/Feature/AdminLocaleTest.php
```

Sebelum perubahan dikirim, jalankan juga test suite penuh, Pint pada file PHP yang berubah, dan `git diff --check`.
