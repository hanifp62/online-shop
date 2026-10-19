# Stage closeout — `ffcb1dd14491`

Tanggal: 2026-10-13  
Scope: guarded Docker PDF/Composer stage, tanpa reinstall Composer dan tanpa apply ulang.

## Ringkasan

| Item | Klasifikasi | Hasil | Bukti |
|---|---|---|---|
| Guarded stage dan lima pin PDF | evidence gap | Stage historis PASS, tetapi stage tree disposable tidak tersedia lengkap untuk hash ulang | `pdf-stage/ffcb1dd14491/report.json`, `composer.lock`, `installed.json` |
| Direct-egress literal-IP | code fix; evidence gap | Perbaikan predicate mensyaratkan kegagalan koneksi literal-IP dan errno jaringan. Probe closeout memakai `--network none`, bukan jaringan stage aktual yang memakai proxy internal; belum membuktikan direct-egress pada topologi stage. | `docker-probe.txt`, `docker_stage.py:181-191` |
| Generated Composer autoload maps | code fix | Sudah diperbaiki sebelum closeout; 26 unit test PASS | `unittest.txt`, `apply-readiness.json` |
| Host platform requirements | evidence | PASS, rc 0 | `composer-platform.txt`, `apply-host.json` |
| Composer audit | open risk | FAIL rc 1; `league/commonmark` memiliki 2 advisory (medium/high), versi terdampak `<=2.10.1` | `composer-audit.txt` |
| Docker Desktop uid check | evidence gap | Host invariant terbukti: root uid `501`, mode `0700`, bukan symlink; process container berjalan sebagai `501:20`. Ownership bind mount di dalam container belum dibuktikan. | `mac-verification.json`, `docker-probe.txt` |
| UI PDF acceptance | out-of-scope di closeout | Closeout ini tidak menjalankan UI; bukti UI terpisah tersedia | `../pdf-ui/README.md` |
| Rollback file baru | code fix + regression test | Unit test membuktikan file baru dihapus dan digest kembali setelah verifikasi gagal. Tidak menguji kegagalan salin parsial atau kegagalan rollback. | `test_amadis_docker_stage.py:223-231` |

## Perintah dan return code

- `python3 -m unittest -v tests.python.test_amadis_docker_stage` — **rc 0**, 26 tests PASS.
- `composer check-platform-reqs --lock` — **rc 0**.
- `composer audit --locked` — **rc 1**; advisory ditemukan pada `league/commonmark`.
- Guarded Docker probe melalui `container_command(...)`, pinned image, `--network none`, `--user 501:20`, `--cap-drop ALL`, `no-new-privileges`, read-only — **rc 0**.
- `docker version` — **rc 0**, Docker Server `29.8.2`.
- Tidak menjalankan `stage-install`, Composer install/require, apply ulang, raw `php -r`, database operation, atau port 8000/8001.

## Blocker dan risiko

1. **Open risk — security advisory.** `league/commonmark` memiliki advisory `PKSA-m2dq-1fhr-29b1` dan `PKSA-m4t9-vsgq-8khn`. Jangan klaim dependency closure bebas advisory sebelum versi dependency diperbarui dan diuji.
2. **Evidence gap — renderer hash.** Bukti archive SHA-256 historis tersedia. Rehash penuh terhadap stage tree disposable tidak dapat diselesaikan karena stage tree hasil apply historis tidak menyimpan seluruh closure yang diperlukan pada lokasi sementara saat ini.
3. **Evidence gap — topologi egress.** Ulangi probe `direct_egress` pada konfigurasi stage yang memakai jaringan internal dan proxy, dengan sasaran literal-IP yang diizinkan.
4. **Open risk — advisory mailable.** Jalur `league/commonmark` ke mailable terbukti ada; eksploitabilitas belum diuji.

## Guard preservation

Tidak ada file kode yang diedit pada closeout ini. Guard dan topology existing tetap dipakai. Evidence reviewed disimpan di folder ini. Raw command output disimpan di `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/stage-closeout-raw`.

## Independent review request

Mohon reviewer independen memeriksa tiga hal: advisory `league/commonmark`, kecukupan substitute invariant uid pada Docker Desktop, dan apakah acceptance UI PDF harus menjadi gate terpisah.
