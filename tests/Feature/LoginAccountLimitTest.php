<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAccountLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_account_is_limited_across_different_ips_with_retry_seconds(): void
    {
        $this->assertAccountLimit('/login');
    }

    public function test_admin_account_is_limited_across_different_ips_with_retry_seconds(): void
    {
        $this->assertAccountLimit('/admin/login');
    }

    private function assertAccountLimit(string $path): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->post($path, ['email' => 'limit@example.test', 'password' => 'WrongPassword123!'])
                ->assertSessionHasErrors('email');
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post($path, ['email' => 'limit@example.test', 'password' => 'WrongPassword123!']);

        $response->assertStatus(429);
        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $seconds);
        $response->assertSee("Coba lagi dalam {$seconds} detik.");
    }
}
