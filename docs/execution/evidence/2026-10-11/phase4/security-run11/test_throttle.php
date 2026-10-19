<?php

$ev = $argv[1];
$cacheDir = $argv[2];

function clearCacheDir($dir)
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
    }
}

function testEndpoint($url, $formUrl, $logFile)
{
    $results = [];
    $cj = tempnam(sys_get_temp_dir(), 'cj_');
    for ($i = 1; $i <= 12; $i++) {
        $ch = curl_init($formUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cj, CURLOPT_COOKIEFILE => $cj]);
        $html = curl_exec($ch);
        curl_close($ch);
        preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m);
        $csrf = $m[1] ?? '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEJAR => $cj, CURLOPT_COOKIEFILE => $cj,
            CURLOPT_POSTFIELDS => http_build_query(['_token' => $csrf, 'email' => 'security-qa@example.test', 'password' => 'wrongpw']),
            CURLOPT_HEADER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $results[] = ['attempt' => $i, 'code' => $code];
    }
    @unlink($cj);
    file_put_contents($logFile, json_encode($results, JSON_PRETTY_PRINT)."\n");

    return $results;
}

// 1. Customer test
clearCacheDir($cacheDir);
$cust = testEndpoint('http://127.0.0.1:8017/login', 'http://127.0.0.1:8017/login', "$ev/throttle_customer.json");

// 2. Admin test (clear cache first to ensure isolated 10-request window for admin endpoint)
clearCacheDir($cacheDir);
$admin = testEndpoint('http://127.0.0.1:8017/admin/login', 'http://127.0.0.1:8017/admin/login', "$ev/throttle_admin.json");

// 3. Shared-bucket probe: both routes use throttle:login keyed by IP only.
clearCacheDir($cacheDir);
$mix = array_merge(
    testEndpoint('http://127.0.0.1:8017/login', 'http://127.0.0.1:8017/login', "$ev/throttle_shared_customer.json"),
);
$probe = testEndpoint('http://127.0.0.1:8017/admin/login', 'http://127.0.0.1:8017/admin/login', "$ev/throttle_shared_admin_after_customer.json");
echo 'Shared probe admin after 12 customer: '.json_encode(array_column($probe, 'code'))."\n";
clearCacheDir($cacheDir);

echo 'Customer: '.json_encode(array_column($cust, 'code'))."\n";
echo 'Admin:    '.json_encode(array_column($admin, 'code'))."\n";
