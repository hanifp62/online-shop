<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\TotpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminTotpProvision extends Command
{
    protected $signature = 'admin:totp-provision {email} {--name=Admin Atelier}';

    protected $description = 'Buat SATU akun admin baru yang wajib TOTP; kredensial tampil sekali di stdout.';

    public function handle(TotpService $totp): int
    {
        $email = (string) $this->argument('email');
        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }
        if (Admin::where('email', $email)->exists()) {
            $this->error('Email sudah terdaftar. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $password = Str::password(24);
        $secret = $totp->generateSecret();
        $codes = $totp->generateRecoveryCodes();

        DB::transaction(function () use ($email, $password, $secret, $codes, $totp): void {
            $admin = Admin::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password]);
            $admin->forceFill(['totp_secret' => $secret, 'totp_required_at' => now()])->save();
            $totp->replaceRecoveryCodes($admin, $codes);
        });

        // Printed only after commit, only to stdout, never logged.
        $this->line('Simpan sekarang. Data ini tidak akan ditampilkan lagi.');
        $this->line('Password: '.$password);
        $this->line('Kunci setup: '.$secret);
        $this->line('URI: '.$totp->provisioningUri($email, $secret));
        $this->line('Kode cadangan 2FA (sekali pakai):');
        foreach ($codes as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }
}
