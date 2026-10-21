# Lokalisasi Akun & Email (Fase 4)

**Cakupan:** `resources/views/account/**`, `resources/views/mail/**`, katalog `account_ui.php` dan `mail_ui.php` untuk `id/en/fr/it/zh`.
**Tes:** `tests/Feature/AccountMailLocaleSliceTest.php`

## Perubahan

- 4 view akun dan 4 view email memakai `__()`. Teks terlihat, aria-label, alt, dan placeholder ada di katalog.
- `<html lang>` diisi dari `app()->getLocale()` (pola sama dengan view auth).
- Teks `id` dipertahankan persis seperti sebelumnya.
- Email terkirim memakai locale penerima lewat `Mail::to(...)->locale(RecipientLocale::resolve(...))`. Locale divalidasi terhadap `config('app.supported_locales')`, fallback `id`. Sumber: pesanan dan keranjang memakai `users.locale`; newsletter memakai locale sesi; concierge memakai locale sesi, lalu `users.locale`.
- Subjek semua mailable dan `OrderStatusChanged::MESSAGES` kini berupa key `mail_ui.*`. Kode status tidak berubah. Tes: `tests/Feature/DeliveredMailLocaleTest.php`.

## Keputusan

- Nama merek tetap: `Mon Compte`, `Votre Compte`, `Le Salon Privé`, `Bonjour`, `Merci`, `La Lettre ZAFIRA`, `ZAFIRA — La Maison de Lumière`, Google/Apple.
- Frasa hapus akun `HAPUS AKUN SAYA` tetap literal lewat placeholder `:phrase`. `ProfileController` memvalidasinya (`in:HAPUS AKUN SAYA`), jadi tidak boleh diterjemahkan.
- String `confirm()` JS di-escape dengan `Js::from(__(...))`.
- Badge status concierge memakai `Lang::has('account_ui.status.<kode>')`; kode tak dikenal tampil apa adanya.
- Konten pengguna (judul item, nama layanan, catatan) dan kode domain tidak diterjemahkan.
- Format tanggal tetap `d M Y`.

## Bukti (Continuous Evaluation Record)

| Langkah | Hasil |
|---|---|
| RED: tes mail locale sebelum implementasi | 6 gagal, 8 assertion, EXIT=1 |
| GREEN: `DeliveredMailLocaleTest` | 6 lulus, 173 assertion, EXIT=0 |
| GREEN: `AccountMailLocaleSliceTest` + regresi mail/storefront | 26 lulus, 1337 assertion, EXIT=0 |
| Regresi penuh `php artisan test` | 877 lulus, 4 gagal, 4 skipped, 11711 assertion, EXIT=1; gagal hanya `AdminLocaleTest` dari pekerjaan admin paralel |
| `vendor/bin/pint` pada file berubah | passed |
| `git diff --check` (lingkup) | bersih |

## Batas yang tersisa

- Pesanan guest tidak menyimpan locale. Email status pesanan tanpa `user` jatuh ke `id`.
- Flash message controller dan pesan validasi belum dimigrasi.
- Format tanggal belum dilokalkan.
- Salinan en/fr/it/zh belum ditinjau penutur asli.
- Belum ada tinjauan independen (crosscheck).
