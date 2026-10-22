<?php

use App\Jobs\ReleaseExpiredReservations;
use App\Models\PaymentDeadLetter;
use App\Services\CheckoutService;
use App\Services\DatabaseBackupService;
use App\Services\PaymentWebhookService;
use App\Services\ProcessingReadiness;
use App\Support\StorefrontCacheReclaimer;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('carts:prune', function () {
    // Keranjang akun tidak pernah kedaluwarsa; hanya keranjang tamu yang dipangkas.
    $deleted = DB::table('carts')->whereNotNull('token_hash')
        ->where(fn ($query) => $query->whereNotNull('consumed_at')->orWhere('expires_at', '<=', now()))
        ->delete();
    $this->info("Pruned {$deleted} guest carts.");
})->purpose('Hapus keranjang tamu yang kedaluwarsa atau sudah digabung');

Artisan::command('storefront-cache:prune', function () {
    $deleted = StorefrontCacheReclaimer::pruneExpired(100);
    $this->info("Pruned {$deleted} expired storefront cache entries.");
})->purpose('Reklamasi terbatas cache storefront kedaluwarsa tanpa menghapus cache lain');

Artisan::command('reservations:release', function (CheckoutService $checkout) {
    $released = (new ReleaseExpiredReservations)->handle($checkout);
    $this->info("Released reservations for {$released} expired orders.");
})->purpose('Batalkan order pending yang reservasi stoknya kedaluwarsa');

Artisan::command('payments:replay-dead-letters', function (PaymentWebhookService $service) {
    if (Cache::get('midtrans:circuit_open_until', 0) > now()->timestamp) {
        $this->info('Circuit Midtrans terbuka; replay ditunda.');

        return;
    }
    $replayed = 0;
    PaymentDeadLetter::eligibleForReplay()->orderBy('last_attempted_at')->orderBy('id')
        ->limit(PaymentDeadLetter::REPLAY_BATCH_SIZE)->get()
        ->each(function (PaymentDeadLetter $letter) use ($service, &$replayed) {
            $status = $service->replay($letter);
            if ($status === null) {
                return; // Another worker advanced/resolved this selected observation.
            }
            $replayed++;
            if ($status === 403) {
                Log::critical('midtrans.dead_letter signature gagal saat replay (server key berubah?)', ['dead_letter_id' => $letter->id]);
            } elseif ($status !== 200 && $status !== 503) {
                Log::warning('midtrans.dead_letter replay ditolak; hold tetap terbuka', ['dead_letter_id' => $letter->id, 'status' => $status]);
            }
            $current = $letter->fresh();
            if ($current && $current->resolved_at === null && $current->attempts >= PaymentDeadLetter::MAX_REPLAY_ATTEMPTS) {
                Log::critical('midtrans.dead_letter dikarantina; perlu rekonsiliasi operator', ['dead_letter_id' => $letter->id]);
            }
        });
    $this->info("Replayed {$replayed} payment dead letters.");
})->purpose('Putar ulang notifikasi Midtrans yang gagal diproses');

Artisan::command('payments:prune-dead-letters', function () {
    $deleted = PaymentDeadLetter::whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays(30))->delete();
    $this->info("Pruned {$deleted} resolved payment dead letters.");
})->purpose('Hapus dead-letter pembayaran yang selesai lebih dari 30 hari');

Artisan::command('db:backup {--verify : Uji pulih cadangan baru ke basis data sementara}', function () {
    $service = app(DatabaseBackupService::class);
    $path = $service->backup();
    $this->info('Cadangan dibuat: '.basename($path));
    if ($this->option('verify')) {
        $tables = $service->verify($path);
        $this->info('Uji pulih lulus ('.count($tables).' tabel cocok).');
    }
    $pruned = $service->prune(config('backup.keep'));
    $this->info("Retensi: {$pruned} cadangan lama dihapus.");
})->purpose('Cadangkan basis data, uji pulih opsional, dan terapkan retensi');

Artisan::command('db:backup-verify {file? : Berkas cadangan; default terbaru}', function () {
    $service = app(DatabaseBackupService::class);
    $file = $this->argument('file') ?? ($service->backups()[0] ?? null);
    if (! $file) {
        $this->error('Belum ada cadangan.');

        return 1;
    }
    $tables = $service->verify($file);
    $this->info('Uji pulih lulus untuk '.basename($file).' ('.count($tables).' tabel cocok).');
})->purpose('Uji pulih cadangan ke basis data sementara tanpa menyentuh produksi');

// /health tetap dependency health. Snapshot CLI ini bukan aktivasi alert host.
Artisan::command('ops:readiness', function (ProcessingReadiness $readiness) {
    $report = $readiness->snapshot();
    $this->line(json_encode($report, JSON_THROW_ON_ERROR));

    return $report['status'] === 'ok' ? 0 : 1;
})->purpose('Periksa bukti processing default queue; JSON tanpa detail koneksi');

Artisan::command('ops:heartbeat', function (ProcessingReadiness $readiness) {
    $ok = $readiness->heartbeat();
    $this->line(json_encode(['status' => $ok ? 'ok' : 'fail'], JSON_THROW_ON_ERROR));

    return $ok ? 0 : 1;
})->purpose('Catat tick scheduler dan kirim probe async dengan lease terbatas');

Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping(1);
Schedule::command('carts:prune')->daily();
Schedule::command('storefront-cache:prune')->hourly()->withoutOverlapping();
Schedule::command('auth-events:prune')->daily()->withoutOverlapping();
Schedule::command('cart-reminders:send')->hourly()->withoutOverlapping();
Schedule::command('payments:prune-dead-letters')->daily();
Schedule::command('payments:replay-dead-letters')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('orders:retry-notifications')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:release')->everyMinute()->withoutOverlapping();
// Uji pulih otomatis hanya didukung pada SQLite; MySQL/MariaDB dicadangkan tanpa klaim terverifikasi.
$backupConnection = (new ConfigurationUrlParser)->parseConfiguration(
    config('database.connections.'.config('database.default'), [])
);
$backupVerify = ($backupConnection['driver'] ?? null) === 'sqlite' ? ' --verify' : '';
Schedule::command('db:backup'.$backupVerify)->dailyAt('02:00')->withoutOverlapping()->onOneServer();
