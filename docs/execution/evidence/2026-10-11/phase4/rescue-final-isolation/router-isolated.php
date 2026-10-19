<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;

// Runtime-only QA bootstrap; configuration is fixed before providers boot.
$repo = '/Users/hanifpriyambodo/Documents/online-shop';
$qa = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-qa';
$proof = PHP_SAPI === 'cli';
if (! $proof) {
    $uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    $file = realpath($repo.'/public'.$uri);
    if ($uri !== '/' && $file && str_starts_with($file, $repo.'/public/') && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}
define('LARAVEL_START', microtime(true));
require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->useStoragePath($qa.'/storage');
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($qa) {
    $app['config']->set([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => $qa.'/db.sqlite',
        'session.driver' => 'file',
        'session.files' => $qa.'/storage/framework/sessions',
        'cache.default' => 'array',
        'view.compiled' => $qa.'/storage/framework/views',
        'logging.default' => 'single',
        'logging.channels.single.path' => $qa.'/storage/logs/laravel.log',
    ]);
});
if ($proof) {
    $app->make(Kernel::class)->bootstrap();
    echo json_encode([
        'database.default' => config('database.default'),
        'database.sqlite.database' => config('database.connections.sqlite.database'),
        'database.sqlite.url' => config('database.connections.sqlite.url'),
        'session.driver' => config('session.driver'),
        'session.files' => config('session.files'),
        'cache.default' => config('cache.default'),
        'storage' => $app->storagePath(),
        'view.compiled' => config('view.compiled'),
        'logging.single.path' => config('logging.channels.single.path'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    exit;
}
$app->handleRequest(Request::capture());
