<?php

use App\Jobs\ReleaseExpiredReservations;
use App\Models\PaymentDeadLetter;
use App\Services\CheckoutService;
use App\Services\DatabaseBackupService;
use App\Services\PaymentWebhookService;
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

Artisan::command('reservations:release', function (CheckoutService $checkout) {
    $released = (new ReleaseExpiredReservations)->handle($checkout);
    $this->info("Released reservations for {$released} expired orders.");
})->purpose('Batalkan order pending yang reservasi stoknya kedaluwarsa');

Artisan::command('payments:replay-dead-letters', function (PaymentWebhookService $service) {
    if (Cache::get('midtrans:circuit_open_until', 0) > now()->timestamp) {
        $this->info('Circuit Midtrans terbuka; replay ditunda.');

        return;
    }
    $maxAttempts = 10;
    $replayed = 0;
    PaymentDeadLetter::whereNull('resolved_at')->where('attempts', '<', $maxAttempts)->orderBy('id')->limit(50)->get()
        ->each(function (PaymentDeadLetter $letter) use ($service, $maxAttempts, &$replayed) {
            $status = $service->handle($letter->raw_body, json_decode($letter->raw_body, true) ?: []);
            $replayed++;
            if ($status === 403) {
                Log::critical('midtrans.dead_letter signature gagal saat replay (server key berubah?)', ['dead_letter_id' => $letter->id]);
            } elseif ($status !== 503) {
                // 200 sudah ditandai selesai oleh service; 4xx berarti notifikasi tidak dapat diproses ulang.
                $letter->refresh()->resolved_at ?? $letter->markResolved();
                if ($status !== 200) {
                    Log::warning('midtrans.dead_letter ditutup tanpa pembayaran', ['dead_letter_id' => $letter->id, 'status' => $status]);
                }
            } elseif ($letter->refresh()->attempts >= $maxAttempts) {
                Log::critical('midtrans.dead_letter melewati batas percobaan; perlu rekonsiliasi operator', ['dead_letter_id' => $letter->id]);
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
    $pruned = $service->prune((int) config('backup.keep'));
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

Schedule::command('carts:prune')->daily();
Schedule::command('cart-reminders:send')->hourly()->withoutOverlapping();
Schedule::command('payments:prune-dead-letters')->daily();
Schedule::command('payments:replay-dead-letters')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('orders:retry-notifications')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:release')->everyMinute()->withoutOverlapping();
// Uji pulih otomatis hanya didukung pada SQLite; MySQL/MariaDB dicadangkan tanpa klaim terverifikasi.
$backupVerify = config('database.connections.'.config('database.default').'.driver') === 'sqlite' ? ' --verify' : '';
Schedule::command('db:backup'.$backupVerify)->dailyAt('02:00')->withoutOverlapping()->onOneServer();
