# Retest F1–F9 — 2026-10-14

## Ruang lingkup

Retest read-only terhadap server manual yang sudah berjalan di `http://127.0.0.1:8016`. Server tidak dihentikan atau dimulai ulang. Port `8015`, `8000`, dan `8001` tidak digunakan.

Sumber definisi dibaca penuh:

- `docs/execution/evidence/2026-10-12/final-acceptance/triage-final3/triage-final3.md`
- `docs/execution/evidence/2026-10-13/f-findings-closeout/README.md`

Check dijalankan melalui Playwright CLI pada `/prototype/` dan `/`, locale `en`, `id`, `fr`, `it`, viewport `1024×768`, `1366×768`, `1440×900`, dan `1650×927`. Setiap kombinasi menghasilkan screenshot. Tidak ada login, submit form, order, atau perubahan database.

Fixture manual hanya memuat **12 produk dan 1 cancelled order**. Retest tidak membuktikan perilaku yang bergantung pada fixture Amadis 76 produk.

## Ringkasan verdict

| Finding | Verdict | Bukti utama | Gap / batas |
|---|---|---|---|
| F1 | **STILL PRESENT** | `/prototype/`, 1024px: header `y=73.69..155.81`; H1 `y=580.66..658.16` (ID), CTA/manifesto berada dekat batas bawah. Desktop 1366px H1 tidak terpotong. Screenshot `prototype-id-1024.png`. | Crowding adalah penilaian scanability, bukan overlap geometris. Check menemukan 2 dock intersections di viewport 1024, sehingga bottom content terdorong/tertutup.
| F2 | **STILL PRESENT** | `/prototype/`, 1024px: `.floating-luxury-dock` intersect dengan H1 dan manifesto; raw `check.json` record `prototype-en-1024` dan screenshot `prototype-en-1024.png`. | Pada desktop yang diuji, dock tidak menutup teks Kebaya pada first paint; image intersection yang dilaporkan berasal dari dock terhadap visible hero image. Tidak menguji full-page composite.
| F3 | **STILL PRESENT** | `/prototype/` EN tetap memuat visible Indonesian no-key strings: `Tampilan Editorial`, `Bordir tambal-tangan 60 jam...`, `60+ Jam`, `3 Minggu`, `9 Kali`, `Koleksi Kriya Pilihan`, serta dynamic product copy. Raw `check.json`, screenshot `prototype-en-1366.png`. | Legitimate proper names/brand terms tidak dihitung sebagai defect. Fixture produk terbatas pada data manual.
| F4 | **STILL PRESENT** | `/` ID selector `[data-i18n="editorial_title"]` merender `Songket & Sutra, Deux Rives.`. `/` FR merender `Deux Rives`; `/` IT merender `Due Rive`. Raw `check.json`, screenshot `store-id-1366.png`. | Tidak ada.
| F5 | **STILL PRESENT** | `/` ID selector `service_1_title` = `Private Styling`; `service_3_title` = `Care for Life`. EN tetap sama secara intentional. Raw `check.json`, screenshot `store-id-1366.png`. | Tidak ada.
| F6 | **STILL PRESENT** | `/` IT selector `service_4_title` merender `Scheda artigianale`, sementara service lain memakai title case. Raw `check.json`, screenshot `store-it-1366.png`. | Status copy inconsistency tetap memerlukan keputusan owner bila ingin mengubah casing.
| F7 | **NOT REPRODUCIBLE ON THIS DATA** | `/` hero text dan overlay terukur pada seluruh locale/viewport; tidak ada clipping geometris. Contoh IT 1366: H1 `y=299.70..449.30`, subtitle `y=473.30..524.30`, overlay menutup hero. Screenshot `store-it-1366.png`. | Tidak melakukan per-pixel WCAG contrast sampling. Kesimpulan hanya “tidak terlihat defect clipping”; historical background-only contrast method tetap aproksimasi.
| F8 | **NOT REPRODUCIBLE ON THIS DATA** | `/prototype/`, selector valid visible `[data-i18n="announcement"]` di dalam `.announcement-col-center`. Pada 1366 EN text range terbagi 2 baris dan berada di dalam parent; pada 1024 terbagi 4 baris, tanpa `overflow:hidden` pada parent center. Screenshot `prototype-en-1366.png`; raw dimensions di `check.json`. | Elemen span legacy melaporkan `clientWidth=0`, tetapi Range text rect valid. Ini menjelaskan mengapa selector/measurement lama invalid. Mobile/narrow width di luar matriks historis tidak diuji.
| F9 | **INVALID ORIGINAL CHECK** | `/` IT selector `[data-i18n="maison_title"]` merender 6 text lines pada 1366, box `417×433`, scroll height `454`; tidak ada CSS overflow. Screenshot `store-it-1366.png`, raw `maison` di `check.json`. | Wrapping memang ada, tetapi canonical definition menyatakan wrap bukan defect. Tidak ada line-limit requirement baru.

## Catatan F8 selector

Historical check tidak mengukur visible text node dengan benar. Retest memakai selector valid `[data-i18n="announcement"]` lalu mengukur `Range.getClientRects()` dan ancestor clipping bounds. Karena span legacy memiliki `clientWidth=0`, `clientWidth/scrollWidth` span bukan bukti clipping; text range dan parent bounds menjadi observasi utama.

## Command record

Perintah dan UTC start/end/return code ada di `commands.tsv`. Script acceptance-owned ada di `check.js`. Raw JSON ada di `check.json`; stderr ada di `check.stderr`; screenshot PNG dan hash ada di `SHA256SUMS`.

## Data safety

Tidak ada source edit, `.env` edit, dependency change, guard change, DB write, login, order creation, server restart, atau port terlarang.
