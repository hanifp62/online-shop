<?php

return [
    // Setiap flag ditentukan oleh env. Default aktif untuk fitur yang sudah ada,
    // sehingga ketiadaan env tidak mengubah perilaku rilis saat ini.
    //
    //   ZAFIRA_FEATURE_<NAMA>_ENABLED = true|false (kill switch)
    //   ZAFIRA_FEATURE_<NAMA>_ROLLOUT = 0..100    (persentase cohort; kunci default)
    //
    // `rollout: 100` berarti aktif untuk semua; `rollout: 0` berarti nonaktif total.
    // Nilai rusak (mis. 101, -1, "abc") membuat flag mati: gagal tertutup.
    // Slice MSI mati bawaan; aktivasi eksplisit setelah verifikasi query/engine.
    'admin_ops_summary' => [
        'enabled' => env('ZAFIRA_FEATURE_ADMIN_OPS_SUMMARY_ENABLED', false),
        'rollout' => env('ZAFIRA_FEATURE_ADMIN_OPS_SUMMARY_ROLLOUT', 100),
    ],
    'admin_sales_summary' => [
        'enabled' => env('ZAFIRA_FEATURE_ADMIN_SALES_SUMMARY_ENABLED', true),
        'rollout' => env('ZAFIRA_FEATURE_ADMIN_SALES_SUMMARY_ROLLOUT', 100),
    ],
];
