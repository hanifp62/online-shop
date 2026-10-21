<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_access_operational_dashboard_but_not_sensitive_admin_routes(): void
    {
        $staff = Admin::create([
            'name' => 'Staff',
            'email' => 'staff@example.test',
            'password' => 'Password123!',
            'role' => Admin::ROLE_STAFF,
        ]);

        $this->actingAs($staff, 'admin')->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff, 'admin')->get(route('admin.auth-events.index'))->assertForbidden();
    }

    public function test_admin_can_access_audit_log_and_unknown_role_is_denied(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'Password123!']);
        $this->actingAs($admin, 'admin')->get(route('admin.auth-events.index'))->assertOk();

        $admin->forceFill(['role' => 'unknown'])->save();
        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_provision_command_rejects_unknown_role_and_creates_staff(): void
    {
        $this->artisan('admin:totp-provision', ['email' => 'x@example.test', '--role' => 'owner'])->assertFailed();
        $this->assertDatabaseMissing('admins', ['email' => 'x@example.test']);

        $this->artisan('admin:totp-provision', ['email' => 'y@example.test', '--role' => 'staff'])->assertSuccessful();
        $this->assertSame(Admin::ROLE_STAFF, Admin::where('email', 'y@example.test')->value('role'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
