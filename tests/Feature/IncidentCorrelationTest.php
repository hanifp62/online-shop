<?php

namespace Tests\Feature;

use App\Models\AuthEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentCorrelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_gets_a_request_id_header(): void
    {
        $this->get('/')->assertHeader('X-Request-Id');
    }

    public function test_client_supplied_request_id_is_echoed_when_safe(): void
    {
        $this->get('/', ['X-Request-Id' => 'req-abc123XYZ'])->assertHeader('X-Request-Id', 'req-abc123XYZ');
    }

    public function test_unsafe_header_is_replaced_with_fresh_uuid(): void
    {
        $resp = $this->get('/', ['X-Request-Id' => 'x" onmouseover="alert(1)"']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-/', $resp->headers->get('X-Request-Id'));
    }

    public function test_auth_event_correlates_with_request_id(): void
    {
        $resp = $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Salah#12345']);
        $requestId = $resp->headers->get('X-Request-Id');

        $this->assertNotNull($requestId);
        $this->assertSame(1, AuthEvent::where('request_id', $requestId)->where('event', 'LOGIN_FAILURE')->count());
    }

    public function test_auth_events_are_append_only(): void
    {
        $event = AuthEvent::create([
            'occurred_at' => now(), 'event' => 'LOGIN_SUCCESS', 'outcome' => 'success', 'request_id' => 'req-corr-test-001',
        ]);

        $this->expectException(\LogicException::class);
        $event->update(['outcome' => 'failure']);
    }

    public function test_auth_events_cannot_be_deleted_per_row(): void
    {
        $event = AuthEvent::create([
            'occurred_at' => now(), 'event' => 'LOGIN_SUCCESS', 'outcome' => 'success',
        ]);

        $this->expectException(\LogicException::class);
        $event->delete();
    }

    public function test_prune_still_removes_old_rows(): void
    {
        $event = AuthEvent::create(['occurred_at' => now()->subDays(40), 'event' => 'LOGIN_SUCCESS', 'outcome' => 'success']);

        $this->artisan('auth-events:prune')->assertSuccessful();
        $this->assertDatabaseMissing('auth_events', ['id' => $event->id]);
    }

    public function test_health_endpoint_also_has_request_id(): void
    {
        $this->get('/health')->assertHeader('X-Request-Id');
    }
}
