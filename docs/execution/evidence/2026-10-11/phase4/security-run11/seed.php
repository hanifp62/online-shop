<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/router.php';
if (config('database.default') !== 'sqlite' || config('cache.default') !== 'file') {
    throw new RuntimeException('Isolation failed');
}
touch(getenv('DB_DATABASE'));
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'ProductSeeder', '--force' => true]);
foreach ([User::class, Admin::class] as $model) {
    $model::create(['name' => 'Security QA', 'email' => 'security-qa@example.test', 'password' => password_hash(getenv('QA_PASSWORD'), PASSWORD_BCRYPT)]);
}
echo json_encode(['db' => config('database.connections.sqlite.database'), 'session' => config('session.files'), 'cache' => config('cache.stores.file.path')])."\n";
