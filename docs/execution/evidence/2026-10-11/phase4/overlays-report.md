# Audit Overlay Storefront — Phase 4 (2026-10-11)

**Cakupan:** cart, search, quick view, concierge, profile, size guide, shortcuts.
**Harness:** `audit-overlays.mjs` (read-only, dapat dijalankan ulang: `node audit-overlays.mjs <label-baru>`).
**Server:** isolated `http://127.0.0.1:8015` (SQLite + file session sementara). Port 8000/8001, DB live, dan `.env` tidak disentuh.
**Viewport:** desktop 1440x900, mobile 390x844. **Auth:** guest (tidak ada login, tidak ada submit concierge, tidak ada mutasi backend).

## Hasil eksekusi

| Run | Perintah | Exit code | Log |
| --- | --- | --- | --- |
| overlays-run1 | `node audit-overlays.mjs overlays-run1` | 1 | `overlays-run1.log`, `overlays-run1.exitcode` |
| overlays-run2 | `node audit-overlays.mjs overlays-run2` | 1 (SyntaxError harness, bukan hasil audit) | `overlays-run2.log`, `overlays-run2.exitcode` |
| overlays-run3 | `node audit-overlays.mjs overlays-run3` | 1 | `overlays-run3.log`, `overlays-run3.exitcode` |
| overlays-run4 (final, GET/HEAD-only) | `node audit-overlays.mjs overlays-run4` | 1 | `overlays-run4.log`, `overlays-run4.exitcode` |

Run final (`overlays-run4/results.json`): **164 assertion, 164 dieksekusi, 158 lulus, 6 gagal**, 0 error konsol/pageerror, 0 permintaan jaringan gagal.
Screenshot: 14 berkas (7 overlay x 2 viewport) dengan metadata `route`, `viewport`, `auth`, `overlay` pada `results.json` → `screenshots[]`.

## Defek

### DEF-OVL-01 — Modal "Pintasan Keyboard" terbuka kosong (konten tidak tampil)

- **Dampak:** Bantuan pintasan (Nielsen H10, Shneiderman aturan akselerator) tidak terbaca sama sekali. Pengguna hanya melihat layar gelap tanpa isi dan tanpa target fokus — tidak ada tombol tutup yang bisa diklik atau dijangkau keyboard.
- **Terjadi di:** desktop 1440x900 dan mobile 390x844, rute `/`, guest.
- **Akar masalah (terverifikasi di browser):** `#welcome-shortcuts-backdrop > .modal-window` dihitung `display: none` saat modal terbuka. Aturan CSS aktif: `.modal-window { … display: none; … }` dan `.modal-window.open { display: block; … }`, sedangkan `openElement()` di `resources/js/app.js` hanya menambah kelas `open` pada elemen backdrop (`#welcome-shortcuts-backdrop`), bukan pada `.modal-window` di dalamnya.
- **Repro:** buka `http://127.0.0.1:8015/` → klik tombol footer "Pintasan (?)" (`[data-open-shortcuts]`) → backdrop gelap muncul (`aria-hidden="false"`, kelas `open`), isi modal tidak terlihat.
- **Bukti:** `overlays-run4/shortcuts-1440x900.png`, `overlays-run4/shortcuts-390x844.png`, dan di `overlays-run4/results.json` assertion gagal `focus moves inside`, `has keyboard focus targets` (0 elemen fokus), `shortcuts inner window displayed`.
- **Catatan turunan:** karena tidak ada elemen fokus, fokus tetap tertinggal pada tombol pemicu, sehingga uji Tab containment dan tombol tutup tidak dapat dijalankan untuk overlay ini (bukan lulus, melainkan terblokir oleh defek yang sama). `Escape` tetap menutup backdrop dan mengembalikan fokus ke pemicu.

## Status per overlay (run final)

| Overlay | 1440x900 | 390x844 | Catatan |
| --- | --- | --- | --- |
| cart | lulus 13/13 | lulus 13/13 | backdrop teruji di kedua viewport |
| search | lulus 13/13 | lulus 11/11 | mobile: tidak ada titik backdrop yang terekspos (panel menutupi viewport) → tidak dapat diuji, bukan lulus |
| quick view | lulus 13/13 | lulus 11/11 | sama seperti di atas |
| concierge | lulus 13/13 | lulus 11/11 | pemicu mobile memakai `[data-open-concierge]:not(.mobile-concierge)` karena tombol di menu mobile hanya terjangkau setelah menu dibuka |
| profile | lulus 13/13 | lulus 13/13 | |
| size guide (`/produk/celeste-trench`) | lulus 13/13 | lulus 11/11 | mobile: backdrop tidak terekspos |
| shortcuts | **gagal 3** | **gagal 3** | DEF-OVL-01 |

Assertion per overlay yang lulus mencakup: HTTP 200 rute, pemicu nyata yang terlihat, pemicu membuka dialog (`open` + `aria-hidden="false"` + bukan `inert`), fokus pindah ke dalam, ada target fokus keyboard, Tab membungkus dari terakhir ke pertama, Shift+Tab membungkus dari pertama ke terakhir, `Escape` menutup, `Escape` mengembalikan fokus pemicu, tombol tutup menutup, tombol tutup mengembalikan fokus pemicu, serta (bila backdrop terekspos) backdrop menutup dan mengembalikan fokus.

## Batasan yang diakui

- "Backdrop tidak terekspos di viewport" dicatat eksplisit sebagai `backdrop: not-available-at-viewport` di `results.json`, bukan dihitung sebagai assertion lulus.
- Audit hanya guest. Rute admin/akun dan pengiriman formulir concierge tidak disentuh.
- Uji Tab hanya memverifikasi pembungkusan di batas pertama/terakhir, bukan setiap langkah Tab di tengah dialog.
