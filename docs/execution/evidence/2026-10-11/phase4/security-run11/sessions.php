<?php

require __DIR__.'/router.php';
$rows = [];
function checkIt($name, $ok, $details = [])
{
    global $rows;
    $rows[] = ['assertion' => $name, 'result' => $ok ? 'PASS' : 'FAIL', 'details' => $details];
}
function req(&$jar, $path, $data = null)
{
    $ch = curl_init('http://127.0.0.1:8017'.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($jar), $jar))]);
    if ($data !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $h = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = substr($raw, strlen($h));
    preg_match_all('/^Set-Cookie: ([^=]+)=([^;]*)/mi', $h, $ms, PREG_SET_ORDER);
    foreach ($ms as $m) {
        $jar[$m[1]] = $m[2];
    }
    preg_match('/^Location: (.*)$/mi', $h, $m);

    return ['code' => $code, 'location' => trim($m[1] ?? ''), 'body' => $body];
}
function token(&$j)
{
    $r = req($j, '/');
    preg_match('/name="csrf-token" content="([^"]+)"/', $r['body'], $m);
    if (empty($m[1])) {
        throw new RuntimeException('Missing CSRF');
    }

    return $m[1];
}
function sid($j)
{
    $v = app('encrypter')->decrypt(urldecode($j[config('session.cookie')]), false);

    return substr($v, strrpos($v, '|') + 1);
}
function login(&$j, $guard)
{
    $t = token($j);
    $old = sid($j);
    $r = req($j, $guard === 'customer' ? '/login' : '/admin/login', ['_token' => $t, 'email' => 'security-qa@example.test', 'password' => getenv('QA_PASSWORD')]);
    $new = sid($j);
    checkIt("$guard fixation", $old !== $new, ['before_sha256' => hash('sha256', $old), 'after_sha256' => hash('sha256', $new), 'post_code' => $r['code']]);
    $r = req($j, $guard === 'customer' ? '/akun' : '/admin');
    checkIt("$guard authenticated", $r['code'] === 200, ['code' => $r['code']]);
}
foreach (['customer', 'admin'] as $g) {
    $j = [];
    login($j, $g);
    $t = token($j);
    $old = $j;
    $id = sid($j);
    $r = req($j, $g === 'customer' ? '/logout' : '/admin/logout', ['_token' => $t]);
    $newT = token($j);
    checkIt("$g logout rotates ID", $id !== sid($j), ['post_code' => $r['code']]);
    checkIt("$g logout rotates CSRF", $t !== $newT);
    $r = req($old, $g === 'customer' ? '/akun' : '/admin');
    checkIt("$g old cookie rejected", $r['code'] === 302 && str_ends_with($r['location'], $g === 'customer' ? '/login' : '/admin/login'), ['code' => $r['code'], 'location' => $r['location']]);
}
foreach (['customer', 'admin'] as $out) {
    $j = [];
    login($j, 'customer');
    login($j, 'admin');
    $t = token($j);
    $old = $j;
    $id = sid($j);
    req($j, $out === 'customer' ? '/logout' : '/admin/logout', ['_token' => $t]);
    checkIt("dual logout $out rotates ID", $id !== sid($j));
    checkIt("dual logout $out rotates CSRF", token($j) !== $t);
    $r = req($j, $out === 'customer' ? '/admin' : '/akun');
    checkIt("dual logout $out preserves other guard", $r['code'] === 200, ['code' => $r['code']]);
    $r = req($j, $out === 'customer' ? '/akun' : '/admin');
    checkIt("dual logout $out removes own guard", $r['code'] === 302, ['code' => $r['code']]);
    foreach (['/akun', '/admin'] as $path) {
        $replay = $old;
        $r = req($replay, $path);
        checkIt("dual logout $out old cookie rejected $path", $r['code'] === 302 && str_contains($r['location'], 'login'), ['code' => $r['code'], 'location' => $r['location']]);
    }
}
file_put_contents(__DIR__.'/sessions.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
foreach ($rows as $row) {
    echo $row['result'].' '.$row['assertion']."\n";
}exit(count(array_filter($rows, fn ($r) => $r['result'] === 'FAIL')) ? 1 : 0);
