# Checklist Implementasi Auth Events (7476e5f8)

- [x] Tambah migration, enum, model, recorder fail-open + sanitizer (tanpa `user_agent` mentah).
- [x] Tambah instrumentation enrol, login admin, TOTP/recovery, lockout, session rotation, logout/password update.
- [x] Tambah wrapper penerbit token password reset tanpa mengubah config/alur forgot-password.
- [x] Tambah route/controller/view auth-events dengan middleware dan pagination/filter.
- [x] Tambah command prune dengan validasi days.
- [x] Tulis test RED untuk schema, recorder, flow, lockout, authorization, retention, secrets, corrupt enrol secret, lockout fallback, dan reset audit failure.
- [x] Implement minimal sampai GREEN, lalu refactor terbatas.
- [x] Tutup temuan review keamanan: exception TOTP/recovery/enrol ditolak aman tanpa pesan atau trace; `user_agent` mentah dikeluarkan dari allowlist.
- [x] Uji kegagalan penulisan marker session TOTP/recovery/enrol: tidak ada LOGIN_SUCCESS, ENROL_CONFIRMED, atau SESSION_ROTATED. Mutasi event sebelum `put` memberi RED_EXIT=1; file dipulihkan dengan MD5 identik; GREEN_EXIT=0.
- [x] Jalankan full SQLite suite (`EXIT=0`, 288 passed, 4 skipped, 2201 assertions); route TOTAL=60 ADMIN=25 GUEST_ADMIN=2 TOTP_PROTECTED=16.
