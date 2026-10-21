<?php

return [
    // Lokasi cadangan; arahkan ke disk terpisah/terpasang di produksi.
    'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
    // Jumlah cadangan terbaru yang disimpan per koneksi.
    'keep' => (int) env('BACKUP_KEEP', 14),
];
