<?php

// Isolated integration proof. No server, operational database, or application .env is used.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);
if (! extension_loaded('pcntl') || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Requires pcntl and uncached configuration.');
}
$base = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode';
$dir = $base.'/checkout-proof-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
$db = $dir.'/disposable.sqlite';
touch($db);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))] as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
if (getenv('DB_DATABASE') !== $db || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_URL') !== '' || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Unsafe environment.');
}
$app = require $root.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.checkout-proof-does-not-exist');
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.sqlite.database') !== $db || config('database.default') !== 'sqlite') {
    throw new RuntimeException('Unsafe resolved configuration.');
}
config(['database.connections.sqlite.busy_timeout' => 50, 'database.connections.sqlite.journal_mode' => 'WAL']);
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
$report = ['harness_sha256' => hash_file('sha256', __FILE__), 'service_sha256' => hash_file('sha256', $root.'/app/Services/CheckoutService.php'), 'php' => PHP_VERSION, 'engine' => DB::selectOne('select sqlite_version() as version')->version, 'transaction_mode' => config('database.connections.sqlite.transaction_mode'), 'journal_mode' => DB::selectOne('pragma journal_mode')->journal_mode, 'busy_timeout_ms' => 50, 'database' => $db, 'mysql' => 'NOT_RUN'];
$failed = false;
foreach (['same-key', 'changed-address', 'distinct-voucher', 'distinct-key', 'exact-one-stock', 'double-cancel'] as $scenario) {
    $user = User::factory()->create();
    $address = Address::create(['user_id' => $user->id, 'label' => 'Test', 'recipient_name' => 'Test', 'phone' => '081234567890', 'line1' => 'Test', 'city' => 'Test', 'province' => 'Test', 'postal_code' => '12345']);
    $otherAddress = $address->replicate();
    $otherAddress->save();
    $cat = Category::create(['cat_title' => $scenario]);
    $product = Product::create(['product_cat' => $cat->cat_id, 'product_brand' => 'Test', 'product_title' => 'Test', 'product_slug' => $scenario, 'product_price' => '100000']);
    $initialStock = $scenario === 'exact-one-stock' ? 1 : 10;
    DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $initialStock]);
    $voucher = Voucher::create(['code' => strtoupper($scenario), 'discount_amount' => 1000, 'quota' => 10, 'per_user_limit' => 1]);
    $order = null;
    if ($scenario === 'double-cancel') {
        [$order] = app(CheckoutService::class)->checkout($user, $address, [$product->product_id => 1], $scenario, $voucher->code);
    }
    DB::disconnect();
    $pids = [];
    for ($worker = 0; $worker < 2; $worker++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Fork failed.');
        }
        if ($pid === 0) {
            DB::purge(); // Each process opens its own PDO after fork.
            $start = microtime(true);
            $barrier = null;
            $wait = function () use ($dir, $scenario, $worker, &$barrier): void {
                $barrier = microtime(true);
                file_put_contents("$dir/$scenario-$worker.ready", (string) $barrier);
                $deadline = microtime(true) + 5;
                while (! is_file("$dir/$scenario-".(1 - $worker).'.ready')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Barrier timeout.');
                    }
                    usleep(1000);
                }
            };
            if ($scenario !== 'double-cancel') {
                DB::listen(function ($query) use (&$barrier, $wait): void {
                    if ($barrier === null && DB::transactionLevel() > 0 && str_starts_with($query->sql, 'select * from "products"')) {
                        $wait(); // Both DEFERRED transactions have read before either writes.
                    }
                });
            }
            try {
                if ($scenario === 'double-cancel') {
                    $stale = Order::findOrFail($order->id);
                    $wait();
                    app(CheckoutService::class)->transition($stale, 'cancelled');
                    $status = 200;
                } else {
                    $key = in_array($scenario, ['distinct-key', 'distinct-voucher', 'exact-one-stock'], true) ? "$scenario-$worker" : $scenario;
                    [$result, $created] = app(CheckoutService::class)->checkout($user, $scenario === 'changed-address' && $worker === 1 ? $otherAddress : $address, [$product->product_id => 1], $key, $scenario === 'distinct-voucher' ? $voucher->code : null);
                    $status = $created ? 201 : 200;
                }
                $error = null;
            } catch (Throwable $e) {
                $status = $e instanceof ValidationException ? 422 : (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
                $error = ['class' => get_class($e), 'code' => $e instanceof QueryException ? $e->errorInfo : $e->getCode()];
            }
            file_put_contents("$dir/$scenario-$worker.json", json_encode(['pid' => getmypid(), 'start' => $start, 'barrier' => $barrier, 'end' => microtime(true), 'status' => $status, 'error' => $error]));
            exit(0);
        }
        $pids[] = $pid;
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $exit);
        if (pcntl_wexitstatus($exit) !== 0) {
            throw new RuntimeException('Worker failed.');
        }
    }
    DB::purge();
    $workers = [json_decode(file_get_contents("$dir/$scenario-0.json"), true), json_decode(file_get_contents("$dir/$scenario-1.json"), true)];
    $statuses = array_column($workers, 'status');
    sort($statuses);
    $expected = match ($scenario) {
        'same-key' => [200, 201], 'changed-address' => [201, 409], 'distinct-voucher' => [201, 422], 'distinct-key' => [201, 201], 'exact-one-stock' => [201, 422], 'double-cancel' => [200, 409]
    };
    $orders = Order::where('user_id', $user->id)->count();
    $stock = (int) $product->fresh()->stock;
    $used = (int) $voucher->fresh()->used;
    $items = DB::table('order_items')->where('product_id', $product->product_id)->count();
    $pass = $statuses === $expected && $orders === ($scenario === 'distinct-key' ? 2 : 1) && $items === $orders && $stock === ($scenario === 'double-cancel' ? 10 : $initialStock - $orders) && $used === ($scenario === 'distinct-voucher' ? 1 : 0);
    $failed = $failed || ! $pass;
    $report['scenarios'][$scenario] = compact('workers', 'statuses', 'expected', 'orders', 'items', 'stock', 'used', 'pass');
}
$report['pass'] = ! $failed;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed ? 1 : 0);
