"""Reconcile audit documents with evidence, then build a self-consistent allowlisted ZIP."""
import csv
import datetime
import hashlib
import io
import json
import pathlib
import re
import zipfile

D = pathlib.Path(__file__).resolve().parent.parent
R = D.parents[4]
E = D / 'evidence'
assert (R / 'app/Services/CheckoutService.php').is_file(), R


def replace(path, before, after):
    text = path.read_text()
    if before not in text:
        raise AssertionError(f'not found: {path.name}: {before[:70]}')
    path.write_text(text.replace(before, after))


# Source excerpts use actual source line numbers, with no source modification.
excerpt = D / 'SOURCE_EXCERPTS.md'
parts = ['\n## Lampiran UI statis — sumber aktual (tanpa browser)\n',
         'Rentang berikut dikutip apa adanya dari source. Mereka membuktikan struktur, bukan perilaku runtime atau WCAG conformance.\n']
for name, spans in {
    'resources/views/welcome.blade.php': [(557, 565), (698, 703)],
    'resources/views/catalog/index.blade.php': [(532, 543), (591, 616)],
    'resources/views/shop/index.blade.php': [(1, 18), (126, 139)],
    'resources/views/admin/dashboard.blade.php': [(175, 191)],
    'resources/js/app.js': [(1, 9), (68, 84), (176, 210), (355, 390), (795, 832)],
    'resources/css/app.css': [(932, 968)],
}.items():
    lines = (R / name).read_text().splitlines()
    parts.append(f'\n### `{name}`\n\n```text\n')
    for a, b in spans:
        assert 1 <= a <= b <= len(lines)
        parts.extend(f'{i:4}: {lines[i-1]}\n' for i in range(a, b + 1))
        parts.append('...\n')
    parts.append('```\n')
text = excerpt.read_text()
if '## Lampiran UI statis' in text:
    text = text[:text.index('## Lampiran UI statis')].rstrip() + '\n'
excerpt.write_text(text + ''.join(parts))

# A criterion result is not evidence classification; original rows mixed the two.
criteria = {
 '001': ('Ketersediaan produk dalam JSON-LD sesuai stok dan status aktif', 'normative/internal: truthfulness of structured data'),
 '002': ('Tarif kirim dan tahap penagihan dinyatakan eksplisit sebelum konfirmasi', 'user/product decision pending: no shipping tariff policy supplied'),
 '003': ('Status pembayaran hanya ditandai lunas setelah settlement terverifikasi', 'normative/conditional: only if online payment is within product scope'),
 '004': ('Total pesanan konsisten dengan harga item, diskon dan ongkir', 'normative: transaction/accounting invariant'),
 '005': ('Stok dan kuota voucher tidak oversell pada transaksi paralel engine target', 'existing: checkout transaction and NFR003 claim'),
 '006': ('Pembatalan ulang tidak menambah stok atau kuota dua kali', 'existing: cancellation idempotency'),
 '007': ('CSP yang dimaksud sebagai perlindungan benar-benar enforce setelah validasi', 'normative/conditional: security posture; Report-Only is intentional telemetry'),
 '008': ('Fixture audit aktif sesuai snapshot saat diuji; riwayat tidak dijadikan keadaan kini', 'normative: evidence reproducibility'),
 '009': ('Semua sink runtime terisolasi sebelum bootstrap/HTTP uji', 'user: explicit safety gate'),
 '010': ('File env membatasi akses dan penanganan dugaan bocor berdasar bukti', 'user: zero secret leakage; normative least privilege'),
 '011': ('Migrasi engine target dapat dijalankan tanpa galat pada data aman', 'existing: migration support; historical error REPORTED'),
 '012': ('Resource user dibatasi pemilik dan admin dibatasi guard', 'existing: ownership and auth:admin routes'),
 '013': ('Mailable markdown dirender dan input user di-escape', 'normative: email reliability (untested)'),
 '014': ('Debug dan kredensial profil manual aman untuk konteks pemakaian', 'normative: secure defaults; no compromise proven'),
 '015': ('Suite otomatis lulus pada snapshot dan profil yang benar-benar terisolasi', 'user: current verification required'),
 '016': ('10 retailer yang diminta punya sumber primer relevan; skor hanya dari kriteria sebanding', 'user: explicit global retailer benchmark set'),
 '017': ('Hash source dibanding baseline yang sama dan klaim historis diverifikasi', 'normative: integrity/traceability'),
 '018': ('Dokumen requirement eksternal terlacak bila tersedia', 'user: named prompt/PDF, bounded search only'),
 '019': ('UI diuji keyboard, reflow, target sentuh dan reduced motion', 'user: WCAG 2.2 AA and HCI protocol'),
 '020': ('Idempotency key menghasilkan satu order untuk replay sepadan', 'existing: checkout idempotency design'),
 '021': ('PDF admin terlindung, batas 500 baris/366 hari dan byte dapat dibuka', 'existing: route and PdfDocumentService contract'),
 '022': ('Direct markdown mail render berhasil', 'normative: email reliability (untested)'),
 '023': ('Versi Dompdf 3.1.6 dan Commonmark 2.10.3 tidak berubah', 'user: dependency freeze'),
}
with (D / 'GAP_MATRIX.csv').open(newline='') as f:
    old = list(csv.DictReader(f))
assert len(old) == 23
headers = ['ID','criteria','basis','flow','actual','evidence ID path:line','evidence_status','criteria_status','impact','severity','confidence','principle/source','minimum fix','affected components','acceptance','risk','decision']
for row in old:
    number = row['ID'][-3:]
    assert number in criteria
    row['criteria'], basis = criteria[number]
    row['basis'] = basis + '; evidence: ' + row['basis']
    row.pop('status', None)
# One-shot script: statuses are assigned explicitly per item, not parsed from the swapped legacy columns.
statuses = {
 '001':('OBSERVED','PARTIAL'), '002':('OBSERVED','NOT RUN'), '003':('OBSERVED','NOT IMPLEMENTED'),
 '004':('OBSERVED','PARTIAL'), '005':('OBSERVED','NOT RUN'), '006':('OBSERVED','NOT RUN'),
 '007':('OBSERVED','PARTIAL'), '008':('OBSERVED','PARTIAL'), '009':('OBSERVED','FAIL'),
 '010':('OBSERVED','PARTIAL'), '011':('REPORTED','NOT RUN'), '012':('OBSERVED','PARTIAL'),
 '013':('UNKNOWN','NOT RUN'), '014':('OBSERVED','PARTIAL'), '015':('OBSERVED','NOT RUN'),
 '016':('OBSERVED','PARTIAL'), '017':('REPORTED','NOT RUN'), '018':('OBSERVED','NOT RUN'),
 '019':('UNKNOWN','NOT RUN'), '020':('OBSERVED','NOT RUN'), '021':('OBSERVED','PARTIAL'),
 '022':('UNKNOWN','NOT RUN'), '023':('OBSERVED','PASS'),
}
for row in old:
    n = row['ID'][-3:]
    row['evidence_status'], row['criteria_status'] = statuses[n]
    if n == '002':
        row['actual'] = 'Shipping fee hardcoded 0; no supplied tariff policy. Zero shipping is not by itself a confirmed defect.'
        row['impact'] = 'Potentially unclear shipping/payment terms; actual tariff unknown'
        row['severity'] = 'LOW'
        row['minimum fix'] = 'Decide and disclose shipping policy before charging; do not invent tariff'
        row['acceptance'] = 'Actual charge matches published policy; no nonzero tariff asserted without evidence'
        row['decision'] = 'Open product decision'
    if n == '009':
        row['actual'] = 'Manual DB separate; storage/views/logs shared; coordinator performed two 8016 GET before gate proof; no further requests.'
        row['evidence ID path:line'] = 'evidence/runtime-profile-addendum.json; TEST_RESULTS.md'
        row['severity'] = 'MEDIUM'
        row['decision'] = 'Safety gate incomplete; not proven product P0'
    if n == '010':
        row['actual'] = '.env mode 0666 OBSERVED; manual credential exposure in prior output REPORTED by handoff, not independently reverified here'
        row['minimum fix'] = 'Owner restricts file mode; assess exposure from actual tool logs before deciding rotation'
        row['decision'] = 'Owner review; no compromise asserted'
    if n == '014':
        row['actual'] = 'Manual APP_DEBUG=true via allowlist; prior manual key exposure REPORTED, compromise UNKNOWN'
    if n == '016':
        row['actual'] = 'User-named ten retailers probed; six have relevant-looking public excerpts, four UNKNOWN; comparative scoring NOT RUN'
        row['evidence ID path:line'] = 'evidence/public-benchmarks.json'
        row['acceptance'] = '10 verified comparable policy observations, or explicitly UNKNOWN for inaccessible sources'
    if n == '019':
        row['actual'] = 'Static UI/JS/CSS inspection completed; interactive keyboard/reflow/reduced-motion and WCAG verification NOT RUN'
        row['evidence ID path:line'] = 'SOURCE_EXCERPTS.md: UI annex; TEST_RESULTS.md'
    if n == '008':
        row['actual'] = 'Initial read: products/orders/users 0, migrations 24, sessions 2; later read sessions 6; delta attribution UNKNOWN'
        row['evidence ID path:line'] = 'evidence/stack-isolation.json; evidence/runtime-profile-addendum.json'
    if n == '023':
        row['criteria_status'] = 'PARTIAL' # checked metadata only, no build/runtime dependency verification
with (D / 'GAP_MATRIX.csv').open('w', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=headers)
    writer.writeheader(); writer.writerows(old)

arch = D / 'ARCHITECTURE_AS_IS.md'
text = arch.read_text()
text = text.replace('Data operasional yang dituju konfigurasi `.env` adalah MySQL historis;', 'Konfigurasi `.env` saat dibaca via allowlist menyebut driver MySQL; target sebenarnya tidak dihubungi dan tetap UNKNOWN;')
text = text.replace('`.env` operasional historis MySQL;', '`.env` saat dibaca via allowlist: driver MySQL, target koneksi UNKNOWN;')
text = text.replace('Belum dibootstrap ulang; `.env.manual` tidak dibaca nilainya', 'Belum dibootstrap ulang; kolektor membaca allowlist key/value aman `.env.manual`, tanpa mencatat rahasia')
text = text.replace('Tidak mengakses server aktif selama penilaian baru.', 'Koordinator melaporkan dua GET ke server aktif 8016 sebelum bukti isolasi selesai; lihat insiden di TEST_RESULTS.md. Tidak ada browser/WCAG PASS.')
for line in text.splitlines():
    if re.match(r'  (?:USERS|ADMINS|CATEGORIES|PRODUCTS|ADDRESSES|VOUCHERS|ORDERS|ORDER_ITEMS|REVIEWS|FAVORITES) \{ ', line) and ';' in line:
        name, attrs = line.strip().split(' { ', 1)
        attrs = attrs[:-2] if attrs.endswith(' }') else attrs.rstrip('}')
        block = '  ' + name + ' {\n' + ''.join('    ' + attr.strip() + '\n' for attr in attrs.split(';')) + '  }'
        text = text.replace(line, block)
text = text.replace('Snapshot benchmark publik memeriksa halaman kebijakan pengembalian resmi untuk 10 retailer (bukan audit fitur atau klaim mutu keseluruhan). Nike, Zara, IKEA, Apple, dan Allbirds menghasilkan HTTP 200; Amazon, UNIQLO, H&M, Warby Parker, dan Etsy diblokir (403/akses). Bukti, URL, potongan teks dan batasannya ada di `evidence/public-benchmarks.json`. Lima sumber sukses hanya membuktikan halaman resmi yang dapat diakses; tidak cukup untuk skor komparatif lintas retailer. Seluruh analisis posisi kompetitif tetap UNKNOWN/NOT RUN.', 'Snapshot baru memakai tepat 10 nama yang diminta: Amazon, Alibaba/Tmall, JD.com, PDD/Temu, Walmart, eBay, MercadoLibre, Sea/Shopee, Rakuten, Coupang. Enam punya cuplikan halaman primer yang tampak relevan; empat tetap UNKNOWN karena akses/JS/sertifikat. Sebagian sumber cadangan berupa laman korporat, bukan kebijakan retur: per retailer, teks perlu validasi relevansi sebelum dibandingkan. HTTP 200 sendiri bukan bukti fitur atau mutu. Skor komparatif tetap NOT RUN. Detail URL, upaya, teks, dan keterbatasan ada di `evidence/public-benchmarks.json`.')
text = text.replace('A. **Minimum:** jangan rewrite. Selesaikan migration compatibility MySQL/MariaDB dengan rehearsal terisolasi, concurrency tests lintas proses, payment/fulfillment truth and copy, security enforcement, browser WCAG evidence; lakukan only blocking fixes.', 'A. **Minimum:** jangan rewrite. Buktikan isolasi sink, rehearsal migration MySQL/MariaDB terisolasi, uji concurrency lintas proses, kejelasan status pembayaran dan ongkir berdasarkan kebijakan yang diputuskan, keamanan proporsional, lalu kumpulkan bukti browser/WCAG; hanya perbaikan yang dibuktikan.')
text += '''\n\n## Pemeriksaan statis UI (bukan lulus browser/WCAG)\n\n- Blade storefront `resources/views/welcome.blade.php:557-565` menyediakan dialog keranjang dan kontrol Undo. `resources/views/catalog/index.blade.php:536,591-616` memiliki `<dialog>` dan jalur fokus; sumber tidak membuktikan interaksi pada perangkat.\n- `resources/js/app.js:68-84,176-210,795-832` menetapkan `inert`, `aria-hidden`, fokus, penanganan Escape dan pembatasan Tab. `app.js:1-9` membaca reduced-motion untuk scroll. Hasil runtime termasuk trap fokus, screen reader, dan Escape tetap NOT RUN.\n- `resources/css/app.css:932-968` memperluas sebagian area target menjadi 44px, namun beberapa kontrol compact 24px dan klaim spacing exception dalam komentar belum diverifikasi geometri browser. Kontras/reflow/zoom tetap UNKNOWN.\n- `resources/views/shop/index.blade.php:126-139` memperlihatkan guard alamat/stok, idempotency key, konfirmasi hapus, dan pesan `pending / belum dibayar`. JS lokal keranjang storefront berbeda dari keranjang server; perilaku lintas alur perlu uji.\n- Cuplikan literal bernomor ada pada lampiran `SOURCE_EXCERPTS.md`. Penilaian heuristik tujuh tokoh HCI pada AGENTS.md bersifat pemeriksaan pola statis, bukan usability study.\n\n## Batas insiden dan status keseluruhan\n\nKoordinator melaporkan GET `/koleksi` dan `/` pada server 8016 berstatus 200 sekitar 08:48 WIB, sebelum pembuktian sink lengkap. Snapshot SQLite manual read-only pertama memuat 24 migration, 0 products/orders/users, 2 sessions; pembacaan lain setelah itu menemukan 6 sessions dan mtime berubah. Kausalitas, waktu spesifik perubahan dan kaitannya dengan kedua GET tersebut **UNKNOWN**. Jangan mengklaim tidak ada HTTP secara global atau browser PASS. Server tidak dihentikan. Audit statis tercapai, asesmen penuh **INCOMPLETE**; runtime, browser, concurrency, migrasi target dan uji otomatis tetap NOT RUN.\n'''
arch.write_text(text)

hand = D / 'AUDIT_HANDOFF.md'
t = hand.read_text()
t = t.replace('Paket ini menyelesaikan audit arsitektur as-is berbasis bukti statis.', 'Paket ini menyelesaikan pemeriksaan statis parsial; asesmen penuh **INCOMPLETE**.')
t = t.replace('1. **P0 — isolasi runtime belum aman.** `evidence/stack-isolation.json` menyatakan PARTIAL: database manual terpisah, tetapi storage, compiled views, dan logs berbagi repo. Laravel bootstrap dan HTTP sengaja NOT RUN. Server PID 76656 di 8015 dan server 8016 tidak disentuh.', '1. **Gate keselamatan belum lengkap, bukan P0 produk yang terbukti.** DB manual terpisah; storage, compiled views, logs berbagi repo. Tidak ada bootstrap baru. Koordinator melaporkan dua GET pada server 8016 sebelum pembuktian gate; server 8015 PID 76656 dan server 8016 tidak dihentikan. Sumber: `TEST_RESULTS.md`, `evidence/runtime-profile-addendum.json`.')
t = t.replace('3. **P1 — harga checkout.** Ongkir disimpan nol (`SOURCE_EXCERPTS.md`, `CheckoutService::place` baris 148-157). Tidak ditemukan payment gateway. Order `pending` bukan pembayaran atau pendapatan terverifikasi.', '3. **Keputusan produk terbuka, bukan defect ongkir terkonfirmasi.** Checkout menyimpan ongkir nol; tidak tersedia kebijakan tarif ongkir untuk membuktikan nilai itu salah. Tidak ditemukan settlement pembayaran di alur yang diperiksa. Pesan UI menyatakan order `pending / belum dibayar`, bukan pendapatan terverifikasi.')
t = t.replace('4. **P1 — secret hygiene.** `.env` teramati mode `0666`. Insiden sebelumnya mencetak `.env.manual` ke tool output; nilai rahasia tidak disalin ke paket. Rotasi APP_KEY manual dan kredensial uji perlu persetujuan pemilik.', '4. **Higiene konfigurasi.** `.env` mode `0666` OBSERVED. Paparan isi `.env.manual` pada output terdahulu REPORTED oleh handoff, belum dibuktikan ulang di paket ini; jangan menyatakan kredensial kompromi. Tinjau log akses aktual dan putuskan rotasi bersama pemilik.')
t = t.replace('Sepuluh retailer bernama diperiksa melalui halaman kebijakan pengembalian resmi. Nike, Zara, IKEA, Apple, dan Allbirds memberi HTTP 200; Amazon, UNIQLO, H&M, Warby Parker, Etsy diblokir/403. Potongan sumber resmi disimpan di `evidence/public-benchmarks.json`. Ini bukan skor komparatif atau audit kapabilitas internal retailer; benchmark kompetitif tetap UNKNOWN/NOT RUN.', 'Set permintaan kini tepat: Amazon, Alibaba/Tmall, JD.com, PDD/Temu, Walmart, eBay, MercadoLibre, Sea/Shopee, Rakuten dan Coupang. Enam halaman primer memberi cuplikan tampak relevan; empat UNKNOWN. Cadangan korporat dan halaman yang hanya HTML shell tidak disetarakan dengan kebijakan retur. Lihat `evidence/public-benchmarks.json`; skor komparatif NOT RUN.')
t = t.replace('Dokumen menilai risiko berdasar sumber: affordance/feedback, ownership, escape route, undo, keyboard, target sentuh, status, reflow, reduced motion, dan copy. Evaluasi visual serta interaksi aktual tidak dilakukan karena isolasi gagal.', 'Inspeksi statis Blade/CSS/JS kini ada di `SOURCE_EXCERPTS.md` dan `ARCHITECTURE_AS_IS.md`: affordance, escape route, undo, fokus, target sentuh, status, dan reduced motion. Reflow, kontras, interaksi keyboard dan uji visual belum dijalankan karena gate isolasi belum lengkap.')
t = t.replace('- Manual DB sekarang kosong walau VERIFIKASI.md historis melaporkan 12 produk dan order #1 cancelled.', '- Pada pembacaan pertama manual DB berisi 0 produk/order/user dan 2 session. Pembacaan lain kemudian menemukan 6 session; penyebab selisih UNKNOWN. VERIFIKASI.md historis melaporkan 12 produk dan order #1 cancelled.')
t = t.replace('1. Pemilik meninjau insiden kredensial dan menyetujui rotasi APP_KEY/test credentials.', '1. Pemilik meninjau log akses/output insiden; putuskan apakah rotasi APP_KEY/kredensial manual diperlukan.')
t = t.replace('- `SOURCE_EXCERPTS.md` — fungsi kritis penuh bernomor baris.', '- `SOURCE_EXCERPTS.md` — fungsi kritis dan lampiran Blade/CSS/JS bernomor baris.')
t = t.replace('- `GAP_MATRIX.csv` — 23 gap dengan kolom wajib.', '- `GAP_MATRIX.csv` — 23 item, kriteria dan basis eksplisit, status bukti terpisah dari hasil kriteria.')
t += '\n## Insiden run ini\n\nKoordinator melaporkan GET `/koleksi` dan `/` ke server lama port 8016 menghasilkan HTTP 200 sekitar 08:48 WIB sebelum isolasi sink dibuktikan. Tidak dilakukan HTTP tambahan setelah temuan. Pembacaan DB manual pertama 2 session; pembacaan berikutnya 6 session. Perubahan tidak boleh diatribusikan ke GET tanpa bukti korelasi. Snapshot awal mtime 08:28 WIB dilaporkan mendahului audit; penyebab hilangnya 12 produk historis UNKNOWN. Bukti tambahan: `evidence/runtime-profile-addendum.json`. Asesmen keseluruhan **INCOMPLETE**, bukan production-ready.\n'
hand.write_text(t)

test = D / 'TEST_RESULTS.md'
t = test.read_text()
t = t.replace('| T-006 | HTTP/browser | tidak dijalankan | — | NOT RUN | isolation gate PARTIAL; server 8015/8016 tidak disentuh |', '| T-006 | HTTP/browser | koordinator melaporkan dua GET ke 8016, `/koleksi` dan `/` sekitar 08:48 WIB; browser/WCAG tidak dijalankan | — | PARTIAL | HTTP 200 REPORTED; browser result NOT RUN; `evidence/runtime-profile-addendum.json` |')
t = t.replace('5 HTTP 200; 5 blocked/403;', '6 cuplikan primer tampak relevan; 4 UNKNOWN; skor NOT RUN;')
t = t.replace('dan 2 session rows.', 'dan 2 session rows pada pembacaan pertama; pembacaan berikutnya: 6 session rows, mtime 08:49:42 WIB; sebab selisih UNKNOWN.')
t = t.replace('Therefore this audit did not bootstrap Laravel or make HTTP requests. The active 8015 and 8016 servers remain untouched.', 'No new Laravel bootstrap was done by this packet builder. Coordinator reported two GETs on existing 8016 before sink proof. No subsequent application HTTP was authorized or executed here. Neither server was stopped.')
t = t.replace('Earlier audit work printed full `.env.manual` contents into tool output. Values are not repeated here. Rotate manual APP_KEY and test credentials after owner approval. Restrict `.env` mode `0666`; no rotation or permission mutation was performed in this audit.', 'Prior full `.env.manual` print to tool output was REPORTED by the handoff, not independently verified from original output in this pass. No values are repeated. File `.env` mode `0666` is OBSERVED; consult owner and actual access logs before deciding rotation. No rotation or permission mutation occurred. Coordinator separately REPORTED GET `/koleksi` and `/` on existing 8016, HTTP 200 before full isolation; no browser test PASS. Direct read-only DB snapshots showed sessions 2, later 6, but attribution of changes is UNKNOWN.')
t = t.replace('The packet proves static architecture, dependency state, source excerpts, read-only DB state, and the isolation decision. It does not prove production readiness', 'This is an INCOMPLETE full assessment: static architecture, dependency metadata, UI source inspection, and direct read-only DB snapshots are covered; full isolation is unproven. It does not prove production readiness')
t += '\n## Addendum sumber profil aman dan integritas paket\n\n`evidence/runtime-profile-addendum.json` mencatat allowlist key konfigurasi `.env` dan `.env.manual`, mode berkas, driver/sink yang aman disebut, dan proses listening 8015/8016 tanpa menampilkan password/key/DB name. `.env` saat dibaca menyebut MySQL; target operational sebenarnya UNKNOWN karena tidak ada koneksi/bootstrapping. `evidence/stack-isolation.json` membaca allowlist `.env.manual` juga; pernyataan terdahulu “tidak dibaca” keliru. Perbandingan SHA 211 source hanya berlaku pada manifest `_srcset.txt` di waktu pembacaan, bukan bukti seluruh repo tak berubah.\n'
test.write_text(t)

# Do not include code-generated caches or any unrelated file in the packet.
allow = [D / n for n in ['ARCHITECTURE_AS_IS.md','AUDIT_HANDOFF.md','GAP_MATRIX.csv','SOURCE_EXCERPTS.md','TEST_RESULTS.md']]
allow += sorted(p for p in E.iterdir() if p.is_file() and p.name not in {'collect-run.txt','benchmark-run.txt'})
idx = json.loads((D / 'EVIDENCE_INDEX.json').read_text())
idx['audit_metadata']['updated_at'] = datetime.datetime.now().astimezone().isoformat()
idx['audit_metadata']['assessment_status'] = 'INCOMPLETE (static scope only; runtime and browser not verified)'
idx['audit_metadata']['isolation_verdict'] = 'PARTIAL: manual DB separate, storage/views/logs shared; coordinator reported two 8016 GET before gate proof'
idx['audit_metadata']['operational_db_target'] = 'UNKNOWN (default env driver MySQL; target never connected)'
idx['files'] = {p.relative_to(D).as_posix(): {'size':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()} for p in allow}
idx['mappings']['critical_sources']['ui_static'] = ['resources/views/welcome.blade.php','resources/views/catalog/index.blade.php','resources/views/shop/index.blade.php','resources/views/admin/dashboard.blade.php','resources/js/app.js','resources/css/app.css']
idx['mappings']['test_coverage_status']['wcag_browser_status'] = 'NOT RUN; static Blade/CSS/JS inspection only'
idx['mappings']['test_coverage_status']['http_incident'] = 'REPORTED: two 8016 GET before sink proof; no browser PASS'
idx['mappings']['test_coverage_status']['benchmark_comparative_score'] = 'NOT RUN'
(D / 'EVIDENCE_INDEX.json').write_text(json.dumps(idx,ensure_ascii=False,indent=2) + '\n')
allow.append(D / 'EVIDENCE_INDEX.json')
with zipfile.ZipFile(D/'AUDIT_PACKET.zip', 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for p in allow:
        z.write(p, p.relative_to(D).as_posix())
print('indexed files',len(idx['files']),'ZIP entries',len(allow))
