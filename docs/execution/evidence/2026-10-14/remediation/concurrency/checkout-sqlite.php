<?php

// Isolated integration proof. No server, operational database, or application .env is used.
// Salinan bukti berada di luar repositori akar, jadi akar dicari ke atas dari berkas ini.
$root = __DIR__;
while (! is_file($root.'/vendor/autoload.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        throw new RuntimeException('Repository root not found.');
    }
    $root = $parent;
}
require $root.'/vendor/autoload.php';

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
$sources = [];
foreach (['app/Services/CheckoutService.php', 'app/Models/Order.php', 'app/Models/Voucher.php', 'app/Models/Product.php', 'app/Observers/UserObserver.php', 'config/database.php', 'database/migrations/2026_10_12_000002_create_vouchers_table.php', 'vendor/laravel/framework/src/Illuminate/Database/ConcurrencyErrorDetector.php', 'vendor/laravel/framework/src/Illuminate/Database/Connectors/SQLiteConnector.php'] as $source) {
    $sources[$source] = is_file("$root/$source") ? hash_file('sha256', "$root/$source") : 'MISSING';
}
$report = ['harness_sha256' => hash_file('sha256', __FILE__), 'service_sha256' => $sources['app/Services/CheckoutService.php'], 'source_sha256' => $sources, 'php' => PHP_VERSION, 'laravel' => app()->version(), 'engine' => DB::selectOne('select sqlite_version() as version')->version, 'transaction_mode' => config('database.connections.sqlite.transaction_mode'), 'journal_mode' => DB::selectOne('pragma journal_mode')->journal_mode, 'busy_timeout_ms' => (int) DB::selectOne('pragma busy_timeout')->timeout, 'database' => $db, 'mysql' => 'NOT_RUN'];
$failed = false;
foreach (['same-key', 'changed-address', 'distinct-voucher', 'distinct-key', 'exact-one-stock', 'same-key-stock-one', 'voucher-last-per-user-one', 'same-key-voucher-last', 'combined-same-key', 'combined-distinct-key', 'double-cancel'] as $scenario) {
    $user = User::factory()->create();
    $address = Address::create(['user_id' => $user->id, 'label' => 'Test', 'recipient_name' => 'Test', 'phone' => '081234567890', 'line1' => 'Test', 'city' => 'Test', 'province' => 'Test', 'postal_code' => '12345']);
    $otherAddress = $address->replicate();
    $otherAddress->save();
    $cat = Category::create(['cat_title' => $scenario]);
    $product = Product::create(['product_cat' => $cat->cat_id, 'product_brand' => 'Test', 'product_title' => 'Test', 'product_slug' => $scenario, 'product_price' => '100000']);
    $initialStock = in_array($scenario, ['exact-one-stock', 'same-key-stock-one', 'combined-same-key', 'combined-distinct-key'], true) ? 1 : 10;
    DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $initialStock]);
    $quota = in_array($scenario, ['voucher-last-per-user-one', 'same-key-voucher-last', 'combined-same-key', 'combined-distinct-key'], true) ? 1 : 10;
    $voucher = Voucher::create(['code' => strtoupper($scenario), 'discount_amount' => 1000, 'quota' => $quota, 'per_user_limit' => 1]);
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
            $initialLookup = null;
            $initialLookupPassed = null;
            $wait = function (string $phase) use ($dir, $scenario, $worker): float {
                $at = microtime(true);
                file_put_contents("$dir/$scenario-$phase-$worker.ready", (string) $at);
                $deadline = microtime(true) + 5;
                while (! is_file("$dir/$scenario-$phase-".(1 - $worker).'.ready')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Barrier timeout.');
                    }
                    usleep(1000);
                }

                return $at;
            };
            if ($scenario !== 'double-cancel') {
                DB::listen(function ($query) use (&$barrier, &$initialLookup, &$initialLookupPassed, $wait): void {
                    if ($initialLookup === null && DB::transactionLevel() === 0 && str_contains($query->sql, '"idempotency_key"') && str_contains($query->sql, '"orders"')) {
                        $initialLookup = $wait('initial-lookup'); // Both initial lookups completed before either proceeds to commit.
                        $initialLookupPassed = microtime(true);
                    }
                    if ($barrier === null && DB::transactionLevel() > 0 && str_starts_with($query->sql, 'select * from "products"')) {
                        $barrier = $wait('transaction-read'); // Both DEFERRED transactions read before either writes.
                    }
                });
            }
            try {
                if ($scenario === 'double-cancel') {
                    $stale = Order::findOrFail($order->id);
                    $barrier = $wait('cancel');
                    app(CheckoutService::class)->transition($stale, 'cancelled');
                    $status = 200;
                } else {
                    $key = in_array($scenario, ['distinct-key', 'distinct-voucher', 'exact-one-stock', 'voucher-last-per-user-one', 'combined-distinct-key'], true) ? "$scenario-$worker" : $scenario;
                    $voucherCode = in_array($scenario, ['distinct-voucher', 'voucher-last-per-user-one', 'same-key-voucher-last', 'combined-same-key', 'combined-distinct-key'], true) ? $voucher->code : null;
                    [$result, $created] = app(CheckoutService::class)->checkout($user, $scenario === 'changed-address' && $worker === 1 ? $otherAddress : $address, [$product->product_id => 1], $key, $voucherCode);
                    $status = $created ? 201 : 200;
                }
                $error = null;
            } catch (Throwable $e) {
                $status = $e instanceof ValidationException ? 422 : (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
                $error = ['class' => get_class($e), 'code' => $e instanceof QueryException ? $e->errorInfo : $e->getCode()];
            }
            file_put_contents("$dir/$scenario-$worker.json", json_encode(['pid' => getmypid(), 'start' => $start, 'initial_lookup' => $initialLookup, 'initial_lookup_passed' => $initialLookupPassed, 'transaction_read' => $barrier, 'end' => microtime(true), 'status' => $status, 'error' => $error]));
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
        'same-key' => [200, 201], 'changed-address' => [201, 409], 'distinct-voucher' => [201, 422], 'distinct-key' => [201, 201], 'exact-one-stock' => [201, 422],
        'same-key-stock-one' => [200, 201], 'voucher-last-per-user-one' => [201, 422], 'same-key-voucher-last' => [200, 201], 'combined-same-key' => [200, 201], 'combined-distinct-key' => [201, 422], 'double-cancel' => [200, 409]
    };
    $orders = Order::where('user_id', $user->id)->count();
    $stock = (int) $product->fresh()->stock;
    $used = (int) $voucher->fresh()->used;
    $items = DB::table('order_items')->where('product_id', $product->product_id)->count();
    $voucherOrders = Order::where('voucher_id', $voucher->id)->count();
    $expectedUsed = in_array($scenario, ['distinct-voucher', 'voucher-last-per-user-one', 'same-key-voucher-last', 'combined-same-key', 'combined-distinct-key'], true) ? 1 : 0;
    // Bukti barrier: kedua lookup awal selesai sebelum salah satu worker melewatinya (sebelum transaksi/commit apa pun).
    $lookups = array_column($workers, 'initial_lookup');
    $passed = array_column($workers, 'initial_lookup_passed');
    $reads = array_column($workers, 'transaction_read');
    $barrierProof = $scenario === 'double-cancel' ? 'NOT_APPLICABLE' : (! in_array(null, $lookups, true) && ! in_array(null, $passed, true) && max($lookups) <= min($passed) && ! in_array(null, $reads, true));
    $pass = $statuses === $expected && $orders === ($scenario === 'distinct-key' ? 2 : 1) && $items === $orders && $stock === ($scenario === 'double-cancel' ? 10 : $initialStock - $orders) && $used === $expectedUsed && $voucherOrders === ($scenario === 'double-cancel' ? 1 : $expectedUsed) && $barrierProof !== false;
    $failed = $failed || ! $pass;
    $report['scenarios'][$scenario] = compact('workers', 'statuses', 'expected', 'orders', 'items', 'stock', 'used', 'barrierProof', 'pass');
}
$report['pass'] = ! $failed;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed ? 1 : 0);
