## Ringkasan

<!-- Apa yang berubah dan mengapa. -->

## Gerbang gaya bahasa (wajib bila ada teks baru/diubah)

- [ ] Teks baru mengikuti `docs/02-product/VOICE_AND_TONE_MATRIX.md` (nada sesuai permukaan, istilah wajib dipakai, istilah terlarang dihindari)
- [ ] `php artisan test tests/Feature/VoiceAndToneConsistencyTest.php` lulus

## Verifikasi

- [ ] Requirement/AC terkait dan risiko perubahan ditautkan
- [ ] Check agregat `php` sukses pada SHA PR terbaru (quality, MySQL/restore, browser, release candidate)
- [ ] PHP lengkap melalui launcher terisolasi dan JUnit gate lulus; hanya tiga skip engine yang disetujui pada lane SQLite
- [ ] Node dan Python lulus tanpa skip; `vendor/bin/pint --test` dan build lulus
- [ ] Scanner secret/PII dan audit dependency lulus; tidak melampirkan log/JUnit/credential mentah
- [ ] Reviewer independen menyetujui push terbaru

## Rilis (jika relevan)

- [ ] Release ID/digest, target staging, hasil smoke, dan approval tercatat
- [ ] Migrasi/N−1, reload worker/scheduler, serta `ops:readiness` diperiksa
- [ ] Rollback aplikasi dibedakan dari kill switch dan restore data; bukti drill/RPO/RTO ditautkan
- [ ] Belum diuji atau terblokir ditulis eksplisit; artefak CI bukan izin deploy produksi
