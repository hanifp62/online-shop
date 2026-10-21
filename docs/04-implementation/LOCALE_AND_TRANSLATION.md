# Locale dan Terjemahan

## Cakupan Phase 0–3

ZAFIRA mendukung locale `id`, `en`, `fr`, dan `it`. Locale `zh` tetap tersedia untuk kompatibilitas katalog lama dan tes yang sudah ada.

Urutan resolusi request adalah:

1. Query `?locale=` yang tervalidasi.
2. Locale dalam session.
3. `users.locale` untuk pengguna yang sudah login.
4. Default `id`.

Middleware `App\Http\Middleware\SetLocale` hanya memakai nilai whitelist. Nilai locale tidak pernah dipakai sebagai path file. Middleware berjalan dalam grup web setelah session tersedia.

## Penyimpanan

Guest menyimpan pilihan pada session. Pengguna login dapat menyimpan pilihan melalui `POST /locale`; pilihan itu ditulis ke session dan kolom nullable `users.locale`. Profil juga memvalidasi locale dengan daftar yang sama. Endpoint dilindungi CSRF dan throttle `20,1`.

## Katalog

Katalog berada di `lang/{locale}/zafira.php` dan dipanggil melalui `trans('zafira', [], $locale)`. `resources/views/catalog/labels.php` dipertahankan sebagai shim kompatibilitas untuk audit lama. Tes paritas memastikan semua katalog memiliki key yang sama dengan `id`.

## Phase 4, irisan 1: form auth dan halaman error

Katalog terpisah, bukan di `zafira.php`: `lang/{id,en,fr,it,zh}/auth_ui.php` dan `lang/{id,en,fr,it,zh}/errors_ui.php`. View memakai `__('auth_ui.…')` / `__('errors_ui.…')` terhadap locale aktif, dan `<html lang>` mengikuti `app()->getLocale()`.

Cakupan:

- `resources/views/auth/`: `login`, `register`, `password-email`, `password-reset`, `totp`, `_social`.
- `resources/views/errors/`: `403`, `404`, `419`, `429`, `500`, `503`, `layout`.
- Placeholder dinamis `:provider` pada tombol sosial. Nama `Google` dan `Apple` tetap nama diri.
- Nama merek dan frasa merek (ZAFIRA, Maison, Mon Compte, Votre Monde Privé, Le Cercle ZAFIRA, Créer un Compte) tidak diterjemahkan.
- Copy `id` dipertahankan persis; escaping, atribut HTML, `aria-label`, `alt`, dan `role` tidak berubah.

Bukti:

- `tests/Feature/AuthAndErrorLocaleTest.php` (7 tes, 1094 assertion): copy per locale, `<html lang>`, placeholder, view reset/TOTP, 419, serta paritas key bertingkat dan nilai tidak kosong untuk kedua katalog. Tes ditulis lebih dulu dan gagal (katalog belum ada, `<html lang="id">` statis) sebelum implementasi.
- Regresi hijau: `HumaneErrorPagesTest` dan `SocialLoginTest` (19 tes, copy `id` tidak berubah).
- Pint lulus untuk katalog dan tes baru; `git diff --check` bersih.

Catatan uji: URL tanpa route diproses locale melalui pipeline error 404; regresi menguji cookie sesi terenkripsi, query locale, dan respons JSON. CSRF 419 diuji melalui middleware asli tanpa melonggarkan validasi.


## Perbaikan hasil review independen

- **Profile sync ke session (C1):** `ProfileController::update()` kini menyinkronkan sesi saat field `locale` diubah (`$request->session()->put('locale', $validated['locale'])`). Mencegah fallback sesi lama mengalahkan profil yang baru diperbarui.
- **Default tidak lagi dipaku ke sesi (W1):** `SetLocale` hanya menulis session bila ada pilihan eksplisit (`?locale=` atau session yang sudah ada). Penelusuran anonim tanpa pilihan tidak menulis `id`, sehingga profil `users.locale` tetap berlaku setelah login. Pilihan eksplisit tamu di sesi tetap menang atas profil sampai diganti. Dicakup `test_anonymous_browsing_does_not_pin_default_over_profile_after_login`.
- **Dua sumber kebenaran JS vs Server (W2):** Diselesaikan. Storefront JS (`public/js/zafira-core.js`) dan bootstrap halaman muka (`welcome.blade.php`) kini menjadikan server locale (`data-server-locale="{{ app()->getLocale() }}"`) sebagai sumber kebenaran utama tanpa melakukan POST pada render awal. Pemilihan bahasa melalui UI (`selectLanguage`) menyinkronkan pilihan ke `POST /locale` dengan `X-CSRF-TOKEN`, mengantrekan permintaan beruntun (serialized async writes) agar respons usang tidak menimpa pilihan terbaru, membersihkan query `?locale=` setelah sukses, dan mempertahankan fallback rendering ke `id` untuk kompatibilitas locale server `zh` yang belum memiliki kamus storefront penuh.
- **Teks toggle kata sandi terjemahan (W2/Auth UI):** Teks tombol tampilkan/sembunyikan kata sandi di `resources/js/app.js` tidak lagi di-hardcode dalam bahasa Indonesia. Label teks dan `aria-label` dibaca dinamis dari data atribut (`data-i18n-show`, `data-i18n-hide`, `data-i18n-aria-show`, `data-i18n-aria-hide`) yang diisi dari katalog `auth_ui.common` untuk kelima locale (`id`, `en`, `fr`, `it`, `zh`).

## Verifikasi lanjutan — 21 Oktober 2026

- Review independen perbaikan kartu produk dan kutipan hadiah: APPROVE, tanpa temuan penghambat. Label tombol, nama aksesibel, kutipan hadiah, dan tautan portal diuji dari DOM hasil SSR dalam lima locale.
- Fixture nama produk `Robe "Aurore" & <Soie>` menguji round-trip escaping atribut. Pengujian scoped: 8 lulus, 359 assertions.
- Gate penuh terbaru: 924 tes PHP lulus, 4 dilewati, 23.912 assertions; 304 tes Node lulus; build, Pint, dan `git diff --check` exit 0.
- Browser pada server terisolasi port 8019 dan SQLite sementara: lima locale halaman muka; penyimpanan locale desktop/mobile HTTP 204; tambah keranjang Mandarin HTTP 200; persistensi locale dan keranjang; viewport 390×844 tanpa overflow horizontal. Tidak ada console error atau page error. Skrip `smoke2/continue.mjs` dijalankan ulang agent utama dengan exit 0.
- Bukti sementara: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/smoke2/`. Screenshot port 8011 sebelumnya tidak sah untuk membuktikan isolasi database dan tidak digunakan.
- Belum diverifikasi melalui browser: login admin, invoice/PDF Mandarin, checkout, perubahan kuantitas, undo, dan alur ukuran pakaian. Tes PDF otomatis tidak menggantikan bukti browser.
- Insiden APP_KEY masih terbuka; nilai tidak dicatat di dokumen. Kesamaan dengan kunci produksi dan jangkauan akses keluaran tool belum diketahui. Jangan rotasi tanpa rencana migrasi data terenkripsi. Status produksi: belum disetujui.
- Terjemahan belum ditinjau penutur asli; nama produk/kategori berasal dari data katalog. Mata uang tetap rupiah.

## Catatan baseline sebelum verifikasi lanjutan

Daftar berikut merupakan snapshot historis Phase 4, bukan status terbaru. Sebagian sudah diselesaikan dan membutuhkan rekonsiliasi inventaris sebelum task ditutup:

- URL tanpa route kini mempertahankan 404 dan membaca locale dari query atau sesi melalui pipeline cookie/session khusus error. Route yang sudah cocok tetap mengikuti middleware web; 405 dan CSRF 419 tidak diubah menjadi 404. `POST /locale` tetap membutuhkan token CSRF.
- Storefront mempertahankan pilihan `zh` pada `<html>` dan state, tetapi salinan fallback berbahasa Indonesia diberi `lang="id"` pada `<body class="storefront">`; pulau bahasa dengan `lang` eksplisit tetap berlaku. Region `#locale-status` tersedia untuk umpan balik penyimpanan. Sukses penyimpanan berantai selalu membersihkan query locale usang, termasuk bila pilihan berikutnya gagal.
- Salinan Blade dan JavaScript storefront/admin kini mengikuti lima locale melalui `shop_ui.app_js`, `admin_ui.table_js`, dan kamus `public/js/zafira-core.js`. Yang masih berupa literal Indonesia: salinan ketersediaan stok di templat keranjang `resources/js/app.js` (dijaga `tests/cart-escape.test.mjs`), nama kategori dari data katalog, serta fallback JS yang sengaja dipertahankan berbahasa Indonesia.
- Teks email, sebagian teks checkout/akun, metadata SEO, serta label route dan pesan domain.
- Terjemahan en/fr/it/zh pada irisan ini belum ditinjau penutur asli.

Migrasi permukaan tersebut memerlukan inventaris dan keputusan copy terpisah.
