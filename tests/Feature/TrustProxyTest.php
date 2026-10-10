<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxyTest extends TestCase
{
    public function test_header_forwarded_palsu_diabaikan_tanpa_proxy_tepercaya(): void
    {
        Route::get('/_probe-proxy', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]));

        $response = $this->call('GET', '/_probe-proxy', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $response->assertOk();
        $this->assertSame('10.0.0.5', $response->json('ip'));
        $this->assertFalse($response->json('secure'));
    }

    public function test_forwarded_proto_dihormati_bila_proxy_tepercaya(): void
    {
        TrustProxies::at('10.0.0.5');

        try {
            Route::get('/_probe-proxy', fn (Request $request) => response()->json([
                'ip' => $request->ip(),
                'secure' => $request->isSecure(),
                'url' => url('/contoh'),
            ]));

            $response = $this->call('GET', '/_probe-proxy', [], [], [], [
                'REMOTE_ADDR' => '10.0.0.5',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);

            $response->assertOk();
            $this->assertSame('198.51.100.99', $response->json('ip'));
            $this->assertTrue($response->json('secure'));
            $this->assertStringStartsWith('https://', $response->json('url'));
        } finally {
            TrustProxies::at([]);
        }
    }
}
