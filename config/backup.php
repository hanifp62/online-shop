<?php

return [
    // Lokasi cadangan; arahkan ke disk terpisah/terpasang di produksi.
    'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
    // Integer positif atau string desimal positif; service memvalidasi SEBELUM cast.
    // Caller wajib meneruskan nilai asli ke prune(), bukan (int) config(...).
    'keep' => env('BACKUP_KEEP', 14),
];
