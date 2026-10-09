<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Data dump `admin_info`: (1, 'admin', 'admin@gmail.com', MD5 '12345678').
        // Password di-hash ulang dengan bcrypt; ganti setelah login pertama.
        Admin::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'admin',
                'password' => '12345678',
            ]
        );

        // Data dump `email_info`: (3, 'admin@gmail.com') — dipertahankan
        // sebagai subscriber agar alur newsletter tetap mencakup admin lama.
        NewsletterSubscriber::firstOrCreate(['email' => 'admin@gmail.com']);
    }
}
