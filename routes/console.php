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

Artisan::command('auth:users {--google : Tampilkan hanya akun yang tertaut Google}', function () {
    $onlyGoogle = (bool) $this->option('google');
    $query = \App\Models\User::query()->with('socialAccounts')->orderByDesc('id');
    if ($onlyGoogle) {
        $query->whereHas('socialAccounts', fn ($q) => $q->where('provider', 'google'));
    }

    $users = $query->get(['id', 'name', 'email', 'has_password', 'email_verified_at', 'created_at']);
    if ($users->isEmpty()) {
        $this->warn($onlyGoogle
            ? 'Belum ada pengguna yang login/terdaftar menggunakan Google.'
            : 'Belum ada pengguna terdaftar.');

        return 0;
    }

    $sessions = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    $rows = $users->map(function ($user) use ($sessions) {
        $providers = $user->socialAccounts->pluck('provider')->map(fn ($p) => ucfirst($p))->all();
        if ($user->has_password) {
            array_unshift($providers, 'Password');
        }
        $method = $providers !== [] ? implode(' + ', $providers) : '-';

        $sess = $sessions[$user->id] ?? null;
        $status = 'Offline';
        if ($sess && $sess->last_activity) {
            $last = \Illuminate\Support\Carbon::createFromTimestamp((int) $sess->last_activity);
            $isRecent = $last->greaterThanOrEqualTo(now()->subMinutes((int) config('session.lifetime', 120)));
            $status = ($isRecent ? 'AKTIF · ' : 'Sesi lama · ').$last->diffForHumans();
        }

        return [
            $user->id,
            $user->name,
            $user->email,
            $method,
            $status,
            optional($user->created_at)->format('Y-m-d H:i') ?? '-',
        ];
    })->all();

    $this->table(
        ['ID', 'Nama', 'Email', 'Metode Login', 'Status Sesi', 'Terdaftar'],
        $rows
    );

    return 0;
})->purpose('Lihat daftar pengguna, metode login (Google/Password), dan status sesi aktif');

Artisan::command('auth:admins', function () {
    $admins = \App\Models\Admin::query()->orderBy('id')->get([
        'id', 'name', 'email', 'role', 'totp_required_at', 'created_at',
    ]);

    if ($admins->isEmpty()) {
        $this->warn('Belum ada akun admin terdaftar.');

        return 0;
    }

    $rows = $admins->map(fn ($admin) => [
        $admin->id,
        $admin->name,
        $admin->email,
        $admin->role,
        $admin->requiresTotp() ? 'Wajib TOTP' : 'Tanpa 2FA (Langsung)',
        optional($admin->created_at)->format('Y-m-d H:i') ?? '-',
    ])->all();

    $this->table(['ID', 'Nama', 'Email', 'Role', 'Status 2FA', 'Terdaftar'], $rows);

    return 0;
})->purpose('Lihat daftar akun Admin Atelier beserta role dan status 2FA');

Artisan::command('admin:password {email=admin@gmail.com : Email akun admin yang ingin diatur kata sandinya}', function () {
    if (app()->environment('production')) {
        $this->error('Perintah ini dinonaktifkan di production. Gunakan admin:totp-provision.');

        return 1;
    }

    $email = trim((string) $this->argument('email'));
    $admin = \App\Models\Admin::where('email', $email)->first();

    if (! $admin) {
        $this->error("Akun admin dengan email '{$email}' tidak ditemukan.");
        $this->line('Jalankan `php artisan auth:admins` untuk melihat daftar email admin yang tersedia.');

        return 1;
    }

    $password = (string) $this->secret("Masukkan password baru untuk {$admin->email} (min. 8 karakter, ketikan tersembunyi)");
    if (mb_strlen($password) < 8) {
        $this->error('Password minimal harus 8 karakter.');

        return 1;
    }

    $confirm = (string) $this->secret('Ulangi password baru');
    if (! hash_equals($password, $confirm)) {
        $this->error('Konfirmasi password tidak cocok. Tidak ada perubahan yang disimpan.');

        return 1;
    }

    DB::transaction(function () use ($admin, $password): void {
        $admin->forceFill([
            'password' => $password,
            'remember_token' => \Illuminate\Support\Str::random(60),
        ])->save();
    });

    $this->info("Password untuk admin '{$admin->email}' berhasil diperbarui.");
    $this->line('Silakan login di: http://127.0.0.1:8000/admin/login');

    return 0;
})->purpose('Atur atau reset password akun admin lokal secara interaktif (input tersembunyi)');

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
