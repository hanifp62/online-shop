<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Endpoint kesehatan untuk monitor/load balancer. Di luar grup `web` agar tidak bergantung pada sesi/cookie:
 * jika DB mati, respons tetap 503 jujur, bukan 500 dari middleware sesi.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => fn () => DB::select('select 1'),
            'cache' => function () {
                $key = 'health:'.Str::random(12);
                Cache::put($key, 1, 10);
                $ok = Cache::get($key) === 1;
                Cache::forget($key);
                throw_unless($ok, \RuntimeException::class, 'cache roundtrip');
            },
            'queue' => fn () => Queue::size(),
            'storage' => function () {
                $path = 'health/'.Str::random(12).'.txt';
                $disk = Storage::disk();
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);
                throw_unless($ok, \RuntimeException::class, 'storage roundtrip');
            },
        ];

        $results = [];
        foreach ($checks as $name => $check) {
            try {
                $check();
                $results[$name] = 'ok';
            } catch (\Throwable $e) {
                $results[$name] = 'fail';
                // Hanya nama kelas: pesan galat driver bisa memuat host/kredensial.
                try {
                    Log::warning('Health check failed', ['check' => $name, 'exception' => $e::class]);
                } catch (\Throwable) {
                }
            }
        }

        $healthy = ! in_array('fail', $results, true);

        return response()->json(
            ['status' => $healthy ? 'ok' : 'fail', 'checks' => $results, 'time' => now()->toIso8601String()],
            $healthy ? 200 : 503,
            ['Cache-Control' => 'no-store']
        );
    }
}
