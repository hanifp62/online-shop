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
        Admin::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'admin',
                'password' => env('ADMIN_SEED_PASSWORD') ?: Str::password(32),
            ]
        );

        // Data dump `email_info`: (3, 'admin@gmail.com') — dipertahankan
        // sebagai subscriber agar alur newsletter tetap mencakup admin lama.
        NewsletterSubscriber::firstOrCreate(['email' => 'admin@gmail.com']);
    }
}
