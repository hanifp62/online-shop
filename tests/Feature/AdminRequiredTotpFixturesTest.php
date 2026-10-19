<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminTotp;
use App\Models\Admin;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRequiredTotpFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_admin_fixture_is_pre_enrolled_and_required_to_use_totp(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $admin = Admin::create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'Password123!',
            'totp_secret' => $secret,
            'totp_required_at' => now(),
        ]);

        $this->assertTrue($admin->requiresTotp());
        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect('/admin/totp');
        $this->assertNull(session(EnsureAdminTotp::SESSION_KEY));
    }

    public function test_admin_seeder_preserves_existing_totp_secret_and_flag(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $admin = Admin::create([
            'name' => 'Seeded',
            'email' => 'admin@gmail.com',
            'password' => 'Password123!',
            'totp_secret' => $secret,
            'totp_required_at' => now()->subDay(),
        ]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $admin->refresh();
        $this->assertSame($secret, $admin->totp_secret);
        $this->assertNotNull($admin->totp_required_at);
    }
}
