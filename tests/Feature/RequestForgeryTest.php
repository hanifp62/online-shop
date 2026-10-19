<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestForgeryTest extends TestCase
{
    public static function requests(): array
    {
        return [
            'http valid token' => ['http', null, 'session-token', 200],
            'http missing token' => ['http', null, null, 419],
            'http wrong token' => ['http', null, 'wrong-token', 419],
            'https same origin without token' => ['https', 'same-origin', null, 200],
            'https cross site without token' => ['https', 'cross-site', null, 419],
            'https same site without token' => ['https', 'same-site', null, 419],
            'https cross site valid token falls back' => ['https', 'cross-site', 'session-token', 200],
            'http same origin header has no vendor TLS gate' => ['http', 'same-origin', null, 200],
        ];
    }

    #[DataProvider('requests')]
    public function test_web_middleware_checks_request_forgery(string $scheme, ?string $origin, ?string $token, int $status): void
    {
        // Keep the real web middleware; disable only Laravel's testing-environment bypass.
        $this->app->instance('env', 'local');
        Route::middleware('web')->post('/_csrf-probe', fn () => response()->json(['ok' => true]));
        $headers = $origin === null ? [] : ['Sec-Fetch-Site' => $origin];
        $this->withSession(['_token' => 'session-token'])
            ->postJson($scheme.'://localhost/_csrf-probe', $token === null ? [] : ['_token' => $token], $headers)
            ->assertStatus($status);
    }
}
