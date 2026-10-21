<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_system_returns_ok_for_every_check(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'ok',
                'checks' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok', 'storage' => 'ok'],
            ]);
    }

    public function test_failing_dependency_returns_503_without_leaking_credentials(): void
    {
        Queue::shouldReceive('size')->andThrow(new \RuntimeException('SQLSTATE[HY000] user=zafira password=rahasia host=db.internal'));

        $response = $this->get('/health');

        $response->assertStatus(503)->assertJson(['status' => 'fail', 'checks' => ['queue' => 'fail', 'database' => 'ok']]);
        $body = $response->getContent();
        $this->assertStringNotContainsString('rahasia', $body);
        $this->assertStringNotContainsString('db.internal', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
    }

    public function test_storage_failure_is_reported_as_fail(): void
    {
        Storage::shouldReceive('disk')->andThrow(new \RuntimeException('disk unavailable'));

        $this->get('/health')->assertStatus(503)->assertJson(['checks' => ['storage' => 'fail']]);
    }

    public function test_endpoint_is_public_and_needs_no_session(): void
    {
        $this->get('/health')->assertOk()->assertHeaderMissing('Set-Cookie');
    }
}
