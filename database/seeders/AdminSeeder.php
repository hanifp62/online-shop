<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
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

        Admin::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'admin', 'password' => $password]
        );

        // Data dump `email_info`: (3, 'admin@gmail.com') — dipertahankan
        // sebagai subscriber agar alur newsletter tetap mencakup admin lama.
        NewsletterSubscriber::firstOrCreate(['email' => 'admin@gmail.com']);
    }
}
