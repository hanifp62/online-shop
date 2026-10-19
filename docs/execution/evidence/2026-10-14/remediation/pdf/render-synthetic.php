<?php

use App\Services\PdfDocumentService;
use Illuminate\Foundation\Application;

// Synthetic PDF via the real PdfDocumentService WITHOUT bootstrapping the app:
// no .env, no config, no DB, no providers. A bare Application only binds paths;
// storage is redirected to a fresh isolated root before the service is touched.
// Usage: php render-synthetic.php <new-isolated-root> <out.pdf>
// Fails closed (exit 1) if the root already exists, is not under the system temp dir,
// or if storage_path() does not resolve inside it.

$repo = dirname(__DIR__, 6);
[$self, $root, $out] = $argv + [null, null, null];
$fail = function (string $msg): never {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
};
if ($root === null || $out === null) {
    $fail('usage: php render-synthetic.php <new-isolated-root> <out.pdf>');
}
$tmp = realpath(sys_get_temp_dir());
$parent = realpath(dirname($root));
$name = basename($root);
if ($tmp === false || $parent !== $tmp || ! preg_match('/^zafira-pdf-[A-Za-z0-9-]+$/D', $name)) {
    $fail('isolated root must be a new zafira-pdf-* directory directly under the system temp dir');
}
$root = $parent.'/'.$name;
if (file_exists($root) || is_link($root)) {
    $fail('isolated root already exists; refusing to reuse it');
}
if (! mkdir($root, 0700)) {
    $fail('cannot create isolated root');
}

if (dirname($out) !== $root || file_exists($out) || is_link($out)) {
    $fail('output must be a new file inside the isolated root');
}

require $repo.'/vendor/autoload.php';
$app = new Application($repo);
$app->useStoragePath($root.'/storage');
mkdir($root.'/storage', 0700);

if (! str_starts_with(storage_path('app/private/pdf'), $root.'/storage/')) {
    $fail('storage_path() escaped the isolated root');
}
if ($app->bound('db') || $app->bound('config') || getenv('APP_KEY') !== false) {
    $fail('application state leaked into the isolated renderer');
}

$lines = ['Periode: 2026-09-14 00:00 UTC — 2026-10-14 23:59 UTC', ''];
$names = ['Zoë Ångström-Łukasiewicz', '王小明 (Wáng Xiǎomíng)', 'Ĳsbrand Ñúñez d\'Øresund', str_repeat('Nama Pelanggan Sintetis Sangat Panjang ', 4)];
for ($i = 1; $i <= 70; $i++) {
    $lines[] = sprintf('#%04d  %s  pending  Rp%s', $i, $names[$i % 4], number_format(125000 * ($i % 5 + 1), 0, ',', '.'));
}
$lines[] = '';
$lines[] = 'Total (tidak termasuk dibatalkan): Rp26.250.000';

$pdf = (new PdfDocumentService)->render('Laporan Pesanan — SINTETIS', $lines);
if (! str_starts_with($pdf, '%PDF-') || file_put_contents($out, $pdf) !== strlen($pdf)) {
    $fail('render or write failed');
}
echo 'root='.$root."\nbytes=".strlen($pdf)."\nsha256=".hash('sha256', $pdf)."\n";
