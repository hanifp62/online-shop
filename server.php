<?php

/**
 * Router dev untuk `php artisan serve` (dipakai Artisan jika file ini ada).
 *
 * Di Windows tanpa Developer Mode, symlink Git `public/prototype` ter-checkout
 * sebagai file teks biasa. Server bawaan PHP lalu menganggapnya skrip dan
 * memecah `/prototype/app.css` menjadi SCRIPT_NAME=/prototype + PATH_INFO=/app.css,
 * sehingga Laravel melihat path `/app.css` dan mengembalikan 404. Berkas ini
 * menormalkan SCRIPT_NAME ke `/index.php` agar `/prototype/*` sampai ke
 * PrototypeAssetController.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '');

// Berkas statis nyata di public/ dilayani langsung oleh server bawaan PHP.
if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
unset($_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO']);

chdir($publicPath);

require_once $publicPath.'/index.php';
