<?php

return [
    // CICD-07 / NFR-REM-002: batas awal, bukan SLO produksi yang sudah diukur.
    // Bukti hanya untuk default queue (database/redis), bukan semua worker/release.
    // Cache database/redis dan lock-nya wajib menunjuk backend yang sama dari
    // scheduler, worker, dan monitor. Array/file/failover tidak diterima.
    'processing' => [
        'heartbeat_max_age_seconds' => 180,
        'probe_max_latency_seconds' => 60,
        'probe_interval_seconds' => 60,
        // Lease habis bukan izin menambah probe di antrean yang belum kosong.
        'probe_retry_after_seconds' => 300,
        'queue_max_age_seconds' => 300,
        'max_backlog' => 100,
    ],
];
