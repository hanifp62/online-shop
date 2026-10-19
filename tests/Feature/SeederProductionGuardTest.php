<?php

namespace Tests\Feature;

use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Admin seeding is disabled in production.');

        $this->artisan('db:seed', [
            '--class' => AdminSeeder::class,
            '--force' => true,
        ]);
    }

    public function test_admin_seeder_direct_run_refuses_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Admin seeding is disabled in production.');

        $this->app->make(AdminSeeder::class)->run();
    }

    public function test_database_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Demo seeding is disabled in production.');

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);
    }

    public function test_database_seeder_direct_run_refuses_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Demo seeding is disabled in production.');

        $this->app->make(DatabaseSeeder::class)->run();
    }

    public function test_admin_seeder_still_runs_in_testing(): void
    {
        putenv('ADMIN_SEED_PASSWORD=testing-only-password');
        $_SERVER['ADMIN_SEED_PASSWORD'] = 'testing-only-password';
        $_ENV['ADMIN_SEED_PASSWORD'] = 'testing-only-password';

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('admins', ['email' => 'admin@gmail.com']);
    }

    public function test_admin_seeder_does_not_use_hardcoded_plaintext_password_in_testing(): void
    {
        unset($_SERVER['ADMIN_SEED_PASSWORD'], $_ENV['ADMIN_SEED_PASSWORD']);
        putenv('ADMIN_SEED_PASSWORD');

        $this->seed(AdminSeeder::class);

        $admin = \App\Models\Admin::firstOrFail();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('password', $admin->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('12345678', $admin->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('admin', $admin->password));
    }

    public function test_admin_login_page_does_not_contain_seeded_admin_hint(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertDontSee('Akun seed');
        $response->assertDontSee('admin@gmail.com');
    }
}
