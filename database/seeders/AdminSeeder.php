<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\NewsletterSubscriber;
use App\Services\TotpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(?TotpService $totp = null): void
    {
        $totp ??= app(TotpService::class);
        if (app()->environment('production')) {
            throw new \RuntimeException('Admin seeding is disabled in production.');
        }

        $password = env('ADMIN_SEED_PASSWORD');
        if (! $password) {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('ADMIN_SEED_PASSWORD must be set to seed an admin outside local/testing.');
            }
            // Random, never printed: local/testing operators reset it if they need to log in.
            $password = Str::password(32);
        }

        $admin = Admin::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'admin', 'password' => $password]
        );

        if (! $admin->requiresTotp()) {
            $secret = env('ADMIN_SEED_TOTP_SECRET');
            if (! $secret) {
                if (! app()->environment('testing')) {
                    throw new \RuntimeException('ADMIN_SEED_TOTP_SECRET must be set before seeding an admin.');
                }
                $secret = 'JBSWY3DPEHPK3PXP'; // Public testing fixture only.
            }
            $admin->forceFill(['totp_secret' => $secret, 'totp_required_at' => now()])->save();
            $totp->replaceRecoveryCodes($admin, $totp->generateRecoveryCodes());
        }

        // Data dump `email_info`: (3, 'admin@gmail.com') — dipertahankan
        // sebagai subscriber agar alur newsletter tetap mencakup admin lama.
        NewsletterSubscriber::firstOrCreate(['email' => 'admin@gmail.com']);
    }
}
