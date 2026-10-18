<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\AuthEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_records_one_success_with_actor_ip_and_time(): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'Password123!']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Password123!'])
            ->assertRedirect('/admin');
        $rows = DB::table('auth_events')->where('event', 'LOGIN_SUCCESS')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($admin->id, $rows[0]->actor_id);
        $this->assertSame('admin', $rows[0]->actor_type);
        $this->assertSame('127.0.0.1', $rows[0]->ip);
        $this->assertNotNull($rows[0]->occurred_at);
        $this->assertDatabaseHas('auth_events', ['event' => 'SESSION_ROTATED', 'actor_id' => $admin->id]);
    }

    public function test_failed_password_is_separate_from_totp_and_has_safe_reason(): void
    {
        Admin::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'Password123!']);
        $this->post('/admin/login', ['email' => 'operator@example.test', 'password' => 'wrong']);
        $this->assertDatabaseHas('auth_events', ['event' => 'LOGIN_FAILURE', 'outcome' => 'failure', 'context' => '{"reason":"invalid_credentials","guard":"admin"}']);
        $this->assertDatabaseMissing('auth_events', ['event' => 'TOTP_FAILURE']);
    }

    public function test_recorder_sanitizes_keys_and_values(): void
    {
        app(AuthEventRecorder::class)->record('TOTP_FAILURE', 'failure', 'admin', 4, '127.0.0.1', [
            'guard' => 'secret', 'reason' => '123456', 'password' => 'Password123!', 'session_id' => 'private',
        ]);
        $row = DB::table('auth_events')->first();
        $this->assertSame('{}', $row->context);
    }

    public function test_recorder_rejects_raw_user_agent_even_when_it_contains_credentials(): void
    {
        app(AuthEventRecorder::class)->record('LOGIN_FAILURE', 'failure', 'admin', 4, '127.0.0.1', [
            'user_agent' => 'Mozilla/5.0 Bearer private-token-873951 recovery-ABCD-1234',
        ]);

        $this->assertSame('{}', DB::table('auth_events')->value('context'));
    }
}
