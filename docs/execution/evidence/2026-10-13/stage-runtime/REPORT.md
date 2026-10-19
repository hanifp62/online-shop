# Stage runtime evidence — Docker egress, runtime sentinel, partial-copy rollback

Tanggal: 2026-10-13 (UTC)
Owner scope: `scripts/amadis/docker_stage.py`, `tests/python/test_amadis_docker_stage.py`, folder ini.
Tidak ada reinstall Composer, tidak ada apply ke repo, tidak ada DB/env operasional yang disentuh, tidak ada commit.

## Hasil

| Item | Verdict | Bukti |
|---|---|---|
| Topologi sama dengan install (image digest, 2 jaringan `--internal`, proxy di bridge+internal, UID host, mount stage/root, `--read-only`, `--cap-drop ALL`, `no-new-privileges`) | PASS | `23bdbb01260e/report.json` → `topology`, `inspect-*.stdout`, `start-*.json` |
| Kontrol reachable: container di `bridge` dengan restriksi sama terhubung langsung ke `1.1.1.1/8.8.8.8/9.9.9.9:443` | PASS | `control-direct-egress.*` |
| Direct literal-IP dari container composer ditolak (`errno 101 ENETUNREACH`, bukan DNS, bukan `--network none`) | PASS | `boundary-probes.stdout` → `direct_egress` |
| Proxy yang diizinkan bekerja (`repo.packagist.org` via `amadis-proxy:8080`, 888 byte JSON valid; 8 paralel TLS) | PASS | `allowed_proxy`, `parallel_tls` |
| Proxy menolak domain lain, target privat/gateway/metadata, dan sentinel (403) | PASS | `forbidden_proxy`, `private_targets`, `sentinel_proxy` |
| Sentinel internal tidak terjangkau langsung (east-west) | PASS | `sentinel_direct_isolation` |
| Runtime identity/mount/effective-access sentinel: uid/gid 501/20, CapEff `0`, NoNewPrivs `1`, rootfs write ditolak, mount `/` `ro`, stage+root `rw` dan sentinel read/write sukses, repo sumber tidak terlihat | PASS | `runtime-sentinel.stdout` (termasuk `mountinfo` mentah) |
| Partial-copy rollback (fixture disposable): `copyfile` gagal pada salinan apply ke-2 setelah salinan ke-1 sudah menimpa repo → digest kembali ke awal, file baru dihapus | PASS | `test_partial_copy_failure_rolls_back_to_initial_digest`; `unittest.*` (28 test, rc 0) |
| Cleanup container dan jaringan | PASS, rc 0 semua | `cleanup-*.json` |

Return code mentah dan timestamp start/finish ada di setiap `<label>.json`. Gate runtime: `report.json` `returncode: 0`. Mode `stage-runtime` tidak pernah mengizinkan install.

## Run yang tercatat di folder ini

| Run | Status | Catatan |
|---|---|---|
| `5328339b1e33` | historis, bukan bukti final | Preflight lama (host init + platform) sebelum probe kontrol/sentinel ditambahkan. Menulis root disposable di `$TMPDIR`; tidak menyentuh DB/env operasional. |
| `b6abd070ecc8` | FAIL `runtime_sentinel` | Bug: root belum di-mount ke container composer. Diperbaiki (`mount_root=root`). |
| `23bdbb01260e` | **PASS — bukti final** | Semua 11 predikat PASS. |
| `5bb2c8c89d71` | tidak selesai, kosong | Docker Desktop hang (`docker info` timeout >30s) saat rerun; tidak ada report. |

## Batasan jujur

- Run final `23bdbb01260e` dijalankan sebelum dua edit kecil terakhir: refactor `return` cabang runtime-only (semantik sama) dan test CLI `stage-runtime`. Rerun live gagal karena Docker Desktop hang. Hash sumber saat ini ada di `23bdbb01260e/source-hashes.json`; unit test 28/28 di sumber saat ini.
- Docker Desktop macOS: hasil membuktikan isolasi di VM Linux Docker Desktop, bukan host Linux produksi.
- Probe kontrol membuktikan internet host hidup pada saat run; tidak membuktikan kebijakan firewall eksternal.

## Artefak lebih awal

- `../pdf-stage/*/report.json` (termasuk `036076473df1`, `ffcb1dd14491`): run historis; `036076473df1` memakai `direct_egress` berbasis DNS failure (curl rc 6) — **bukan bukti valid** menurut aturan ini.
- `../stage-closeout/stage-closeout.md` dan `docker-probe.txt`: probe `--network none` — **bukan bukti valid** topologi.
- **Hash stage-tree historis `ffcb1dd14491` tidak tersedia.** Stage disposable dihapus sesuai desain setelah run; hanya `report.json`, `composer.lock`, `installed.json` yang tersisa. Tidak bisa di-hash ulang dan tidak direkonstruksi.

Hash: `SHA256SUMS` (folder ini, relatif).
