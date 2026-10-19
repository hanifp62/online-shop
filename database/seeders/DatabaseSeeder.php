<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo seeding is disabled in production.');
        }

        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            AdminSeeder::class,
        ]);

        $testPassword = env('TEST_USER_SEED_PASSWORD');
        if (! $testPassword) {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('TEST_USER_SEED_PASSWORD must be set to seed demo users outside local/testing.');
            }
            // Random, never printed.
            $testPassword = Str::password(32);
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $testPassword,
        ]);
    }
}
