# AMADIS Acceptance — Isolated Run (AM-01, AM-02, AM-06, AM-10)

Tanggal: 2026-10-13. Sifat: **usulan kerja, bukan rubrik resmi** (lihat `docs/demo/ZAFIRA_AMADIS_CRITERIA_TRACEABILITY.md` — rubrik AMADIS asli tidak ditemukan di repo).

## Lingkup & batas

- Fixture terisolasi baru (migrate + seed + provision), bukan database operasional.
- Tidak ada reset/ubah pada DB operasional, `.env`, seeder, atau fixture lama.
- Bukti PDF/S0–S5 tidak disentuh.
- Tidak ada commit. Working tree sudah punya perubahan lain (`.agents/`, `.claude/`) di luar task ini.
- F1–F9 tetap catatan historis (NOT RETESTED); run ini tidak menguji ulang F1–F9.
- AM-06 Tab: 12 langkah Tab, 0 kebocoran fokus ke konten halaman; 2 langkah fokus keluar ke UI browser (`BODY`), perilaku native `<dialog>` modal.

## Hasil

| ID | Verdict | Bukti kunci |
|---|---|---|
| AM-01 | PASS | `am01.log`: 76 produk, 76 aktif, 4 kategori × 19, slug `amadis-demo-stock-37` stok 37 |
| AM-02 | PASS | `browser-results.json`: 9 halaman × 8 + halaman-10 × 4 = 76 produk unik |
| AM-06 | PASS | keyboard open (Enter/Space), Escape close, focus restore, Tab containment; escaping via `am06_escape_phpunit.log` (6 passed, 34 assertion) |
| AM-10 | PASS (SQLite) | 3 request paralel 20/20/17 → 201, 422, 201; stok akhir 0, unit terjual 20+17=37, stok+unit=37 |

## AM-10 detail

- Jumlah unit terjual = 20 + 17 = 37 (permintaan 57; satu ditolak 422 "stok tidak mencukupi").
- Stok akhir = 0 (≥ 0), tidak negatif.
- Invariant: stok akhir + unit bersih = 0 + 37 = 37.
- **Keterbatasan:** ini berjalan di SQLite. SQLite tidak membuktikan row-level locking / perilaku isolasi mesin produksi MariaDB. Hasil ini tidak mengklaim bukti concurrency engine produksi.

## Evidence

- `commands.tsv` — setiap langkah dengan timestamp UTC start/end, return code mentah, dan command persis.
- `am01.log`, `am10_db.log`, `am06_escape_phpunit.log`, `browser.log`, `serve.log`.
- `browser-results.json` — output mentah Node/Playwright.
- `invariants.json` — penilaian DB invariant AM-01/AM-10 + rc browser.
- `fixture-identity.json` — salinan identity.json fixture (repo, git_head, file manifest).
- `root.txt` — path fixture terisolasi.
- `SHA256SUMS` — hash SHA-256 setiap file evidence.
