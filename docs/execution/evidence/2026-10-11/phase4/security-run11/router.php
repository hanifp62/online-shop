<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;

$repo = '/Users/hanifpriyambodo/Documents/online-shop';
$qa = getenv('QA_ROOT');
if (! $qa || ! str_contains($qa, '/zafira-qa/security-run11')) {
    throw new RuntimeException('Isolation missing');
}
if (PHP_SAPI !== 'cli') {
    $uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    if ($uri === '/prototype' || $uri === '/prototype/') {
        header('Content-Type: text/html');
        readfile($repo.'/prototype/index.html');

        return true;
    }
    if ($uri !== '/' && ! str_contains($uri, '..') && is_file($repo.'/public'.$uri) && pathinfo($uri, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}
define('LARAVEL_START', microtime(true));
require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->useStoragePath($qa.'/storage');
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($qa) {
    $app['config']->set([
        'database.default' => getenv('DB_CONNECTION'),
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => getenv('DB_DATABASE'),
        'session.driver' => getenv('SESSION_DRIVER'),
        'session.files' => getenv('SESSION_FILES'),
        'cache.default' => getenv('CACHE_STORE'),
        'cache.stores.file.path' => getenv('CACHE_PATH'),
        'cache.stores.file.lock_path' => getenv('CACHE_PATH'),
        'view.compiled' => $qa.'/storage/framework/views',
        'logging.default' => 'single',
        'logging.channels.single.path' => $qa.'/storage/logs/laravel.log',
    ]);
});
if (PHP_SAPI === 'cli') {
    $app->make(Kernel::class)->bootstrap();

    return;
}
$app->handleRequest(Request::capture());
