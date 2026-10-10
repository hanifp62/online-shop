<?php

namespace Tests\Feature;

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

    public function test_host_forge_tidak_memercayai_header_tanpa_konfigurasi_proxy(): void
    {
        Route::get('/_probe-proxy', fn (Request $request) => response()->json([
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'url' => url('/contoh'),
        ]));

        $response = $this->call('GET', 'http://zafira.on-forge.com/_probe-proxy', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $response->assertOk();
        $this->assertSame('zafira.on-forge.com', $response->json('host'));
        $this->assertSame('10.0.0.5', $response->json('ip'));
        $this->assertFalse($response->json('secure'));
        $this->assertStringStartsWith('http://', $response->json('url'));
    }

    public function test_forwarded_proto_dihormati_bila_proxy_tepercaya(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.5']]);

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
    }
}
