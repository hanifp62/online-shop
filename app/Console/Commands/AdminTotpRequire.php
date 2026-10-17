<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class AdminTotpRequire extends Command
{
    protected $signature = 'admin:totp-require {--all : Wajibkan TOTP untuk semua admin}';

    protected $description = 'Wajibkan TOTP; menolak bila ada admin tanpa secret agar tidak ada akun terkunci.';

    public function handle(): int
    {
        if (! $this->option('all')) {
            $this->error('Gunakan --all. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $missing = Admin::whereNull('totp_secret')->orderBy('id')->pluck('email');
        if ($missing->isNotEmpty()) {
            $this->error('Dibatalkan. Admin berikut belum memiliki secret TOTP:');
            foreach ($missing as $email) {
                $this->line('  '.$email);
            }

            return self::FAILURE;
        }

        // Single UPDATE: all admins flagged, or none.
        $count = Admin::whereNull('totp_required_at')->update(['totp_required_at' => now()]);
        $this->info("Berhasil mewajibkan TOTP untuk {$count} admin.");

        return self::SUCCESS;
    }
}
