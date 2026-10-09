# Dokumentasi Proyek ZAFIRA (La Maison de Lumière)

Selamat datang di indeks dokumentasi teknis dan produk untuk proyek e-commerce **ZAFIRA** (`online-shop`). Seluruh dokumen disusun secara sistematis, berbasis bukti (*evidence-driven*), dan saling terhubung (*traceable*).

---

## Indeks Struktur Dokumentasi

```
docs/
├── README.md                                  # Indeks dokumentasi utama (dokumen ini)
├── 00-project/
│   ├── ENVIRONMENT_BASELINE.md               # Baseline hardware, runtime macOS, PHP, & database
│   └── PROJECT_CONTEXT.md                    # Pemahaman konteks brand, arsitektur, dan gap produk
├── 01-audit/
│   ├── PROJECT_INSPECTION.md                 # Hasil audit menyeluruh source code & dependensi
│   ├── FEATURE_INVENTORY.md                  # Matriks inventarisasi seluruh fitur aktual
│   ├── INTEGRATION_AUDIT.md                  # Audit alur integrasi full-stack (UI -> DB)
│   ├── UI_UX_AUDIT.md                        # Audit estetika & kegunaan storefront dan admin
│   ├── SECURITY_AND_QUALITY_AUDIT.md         # Evaluasi keamanan, auth, rate limit, & proteksi data
│   └── COMPATIBILITY_REPORT.md               # Laporan kompatibilitas runtime macOS & tooling
├── 02-product/
│   ├── PRODUCT_BRIEF.md                      # Visi produk, target pengguna, dan proposisi nilai
│   ├── RESEARCH_LOG.md                       # Log riset produk luxury e-commerce & referensi teknis
│   ├── PRD.md                                # Product Requirements Document formal (FR/NFR/US)
│   ├── USER_JOURNEYS.md                      # Pemetaan alur pengalaman pengguna & admin
│   └── REQUIREMENTS_TRACEABILITY.md          # Matriks ketertelusuran kebutuhan ke implementasi
├── 03-architecture/
│   ├── TARGET_ARCHITECTURE.md                # Arsitektur sistem target & struktur modul Laravel
│   ├── DATABASE_DESIGN.md                    # Desain database terpadu, ERD, & kamus data
│   ├── ARCHITECTURE_DECISIONS.md             # Architecture Decision Records (ADR)
│   └── ENVIRONMENT_AND_RUNTIME.md            # Konfigurasi runtime & panduan deployment lokal
├── 04-implementation/
│   ├── IMPLEMENTATION_PLAN.md                # Rencana implementasi komprehensif & pentahapan
│   ├── TASKS.md                              # Backlog task rinci dengan dependensi & acceptance criteria
│   └── ACCEPTANCE_CRITERIA.md                # Rincian kriteria penerimaan per fitur
├── 05-quality/
│   ├── TEST_PLAN.md                          # Rencana pengujian otomatis, manual, & browser
│   ├── QUALITY_REVIEW.md                     # Evaluasi kualitas lintas lapisan & verifikasi hasil
│   ├── PROJECT_READINESS_REPORT.md           # Laporan kesiapan proyek & status deliverable
│   └── FOUNDATION_CHANGELOG.md               # Log perubahan teknis pada fondasi & konfigurasi
└── 06-prototype/
    ├── PROTOTYPE_GUIDE.md                    # Panduan menjalankan dan menjelajahi prototype
    ├── SCREEN_AND_FLOW_MAPPING.md            # Pemetaan layar dan interaksi prototype
    └── PROTOTYPE_EVALUATION.md               # Bukti verifikasi browser, responsivitas, & temuan
```

---

## Status Progres Deliverable

| Fase | Dokumen Utama | Status | Bukti Verifikasi |
|---|---|---|---|
| **Fase 0** | `ENVIRONMENT_BASELINE.md`, `AGENTS.md` | **Selesai** | Git repo initialized, commit baseline aman |
| **Fase 1** | `PROJECT_INSPECTION.md`, `FEATURE_INVENTORY.md`, dll. | **Sedang Berjalan** | Audit file aktual & matrix inventarisasi |
| **Fase 2** | `PROJECT_CONTEXT.md` | **Menunggu** | Disusun setelah Fase 1 |
| **Fase 3** | `COMPATIBILITY_REPORT.md`, `FOUNDATION_CHANGELOG.md` | **Menunggu** | Perbaikan izin binary & uji build |
| **Fase 4** | `PRODUCT_BRIEF.md`, `PRD.md`, `USER_JOURNEYS.md` | **Menunggu** | Riset & penomoran FR/NFR |
| **Fase 5** | `TARGET_ARCHITECTURE.md`, `DATABASE_DESIGN.md` | **Menunggu** | ERD & task backlog terinci |
| **Fase 6** | `PROTOTYPE_GUIDE.md`, Prototype Code | **Menunggu** | Pengujian browser interaktif |
| **Fase 7** | `QUALITY_REVIEW.md`, `PROJECT_READINESS_REPORT.md` | **Menunggu** | Verifikasi test akhir & handoff |
