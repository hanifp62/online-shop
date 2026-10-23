<?php

use App\Mail\CartReminder;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Support\ComposerHooks;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Strict allowlist, evaluated before guard, autoload and Laravel. Exact argv vectors only: no Symfony
// abbreviations, no option forms, no configuration/bootstrap/env options. Mirrors launch.py and ComposerHooks callers.
const AMADIS_EXACT_VECTORS = [
    ['amadis:cache-config'],
    ['amadis:provision'],
    ['amadis:provision-react'],
    ['amadis:lifecycle-snapshot'],
    ['migrate', '--force', '--database=sqlite'],
    ['db:seed', '--class=Database\\Seeders\\AmadisDemoSeeder', '--force', '--database=sqlite'],
    ['package:discover', '--ansi'],
    ['vendor:publish', '--tag=laravel-assets', '--ansi', '--force'],
    ['about'],
    ['route:list'],
    ['route:list', '--json'],
    ['--version'],
];

/** Returns the junit path prefix to verify against the guarded root (or null) when argv is allowed; false when rejected. */
function amadisAllowedArgv(array $args): string|false|null
{
    foreach ($args as $arg) {
        if (! is_string($arg)) {
            return false;
        }
    }
    if (in_array($args, AMADIS_EXACT_VECTORS, true)) {
        return null;
    }
    if (($args[0] ?? null) !== 'test') {
        return false;
    }
    $junitRoot = null;
    $seen = [];
    $base = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode';
    foreach (array_slice($args, 1) as $arg) {
        if (preg_match('~^--filter=[A-Za-z0-9_\\\\:]+$~D', $arg)) {
            $kind = 'filter';
        } elseif ($arg === '--order-by=random') {
            $kind = 'order';
        } elseif (preg_match('~^--random-order-seed=[0-9]+$~D', $arg)) {
            $kind = 'seed';
        } elseif (preg_match('~^--log-junit=('.preg_quote($base, '~').'/amadis-[a-f0-9]{24})/evidence/[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\.xml$~D', $arg, $m)) {
            $kind = 'junit';
            $junitRoot = $m[1];
        } elseif (preg_match('~^tests/(?:Feature|Unit)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php$~D', $arg)) {
            $kind = null; // repeatable positional test file; '.' only in the extension, so no '..'
        } else {
            return false;
        }
        if ($kind !== null) {
            if (isset($seen[$kind])) {
                return false;
            }
            $seen[$kind] = true;
        }
    }

    return $junitRoot ?? null;
}

$junitRoot = amadisAllowedArgv(array_slice($argv, 1));
if ($junitRoot === false) {
    fwrite(STDERR, 'Command not allowed.'.PHP_EOL);
    exit(2);
}

require __DIR__.'/guard.php';
$root = amadisGuard();
if ($junitRoot !== null) {
    // Path-dependent argument: only valid inside the guarded root's real evidence directory.
    $junitArgument = current(array_filter($argv, fn ($a) => str_starts_with($a, '--log-junit=')));
    $junit = substr($junitArgument, strlen('--log-junit='));
    if ($junit === false) {
        fwrite(STDERR, 'Command not allowed.'.PHP_EOL);
        exit(2);
    }
    $evidence = $root.'/evidence';
    if ($junitRoot !== $root || is_link($evidence) || ! is_dir($evidence) || realpath($evidence) !== $evidence
        || is_link($junit) || (file_exists($junit) && (! is_file($junit) || lstat($junit)['nlink'] !== 1))) {
        fwrite(STDERR, 'Command not allowed.'.PHP_EOL);
        exit(2);
    }
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
ComposerHooks::guard();
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($root);
$kernel = $app->make(Kernel::class);

if (($argv[1] ?? '') === 'amadis:cache-config') {
    $path = getenv('APP_CONFIG_CACHE');
    if ($path !== $root.'/bootstrap/config.php') {
        throw new RuntimeException('Config cache outside isolation root.');
    }
    @unlink($path); // stale cache would skip env loading and keep an old key
    $kernel->bootstrap();
    $tmp = $path.'.'.getmypid().'.tmp';
    file_put_contents($tmp, '<?php return '.var_export($app['config']->all(), true).';', LOCK_EX);
    rename($tmp, $path);
    exit(0);
}

if (in_array($argv[1] ?? '', ['amadis:provision', 'amadis:provision-react'], true)) {
    if (count($argv) !== 2) {
        throw new RuntimeException('Provision accepts no arguments.');
    }
    $react = $argv[1] === 'amadis:provision-react';
    $private = $root.'/private-runtime';
    $file = $private.'/credentials.json';
    if (realpath($private) !== $private || ! is_dir($private) || (fileperms($private) & 0077)
        || is_link($file) || ! is_file($file) || lstat($file)['nlink'] !== 1 || (fileperms($file) & 0077)) {
        throw new RuntimeException('Private credential file invalid.');
    }
    $kernel->bootstrap();
    $db = DB::connection();
    if ($db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== $root.'/database.sqlite'
        || $db->table('orders')->exists()
        || $db->table('products')->where('product_slug', 'amadis-demo-stock-37')->value('stock') !== 37) {
        throw new RuntimeException('Provision requires unused fresh demo fixture.');
    }
    $credentials = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    if (array_keys($credentials) !== ['admin', 'customer']) {
        throw new RuntimeException('Invalid credential schema.');
    }
    foreach (['admin', 'customer'] as $role) {
        if (array_keys($credentials[$role] ?? []) !== ['email', 'password']
            || ! filter_var($credentials[$role]['email'], FILTER_VALIDATE_EMAIL)
            || strlen($credentials[$role]['password']) < 24) {
            throw new RuntimeException('Invalid credentials.');
        }
    }
    $origin = (string) config('app.url');
    if ($react && ($origin !== getenv('APP_URL') || ! preg_match('~^http://127\.0\.0\.1:([1-9][0-9]{3,4})$~D', $origin, $port)
        || (int) $port[1] < 1024 || (int) $port[1] > 65535 || in_array((int) $port[1], [8000, 8001], true)
        || file_exists($private.'/react-fixture.json') || is_link($private.'/react-fixture.json')
        || $db->table('reviews')->exists())) {
        throw new RuntimeException('React provision requires a fresh private fixture and explicit loopback origin.');
    }
    $ids = $db->transaction(function () use ($credentials, $react, $db, $private, $origin): array {
        $admin = Admin::firstOrNew(['email' => $credentials['admin']['email']]);
        $admin->name = 'Amadis Admin';
        $admin->password = $credentials['admin']['password'];
        $admin->save();
        $customer = User::firstOrNew(['email' => $credentials['customer']['email']]);
        $customer->name = 'Amadis Client';
        $customer->password = $credentials['customer']['password'];
        if ($react) {
            $customer->cart_reminder_opt_in = true;
        }
        $customer->save();

        if ($react) {
            $product = $db->table('products')->where('product_slug', 'amadis-demo-stock-37')->sole();
            if (! $product->is_active || (int) $product->stock !== 37) {
                throw new RuntimeException('React stock fixture invalid.');
            }
            // A synthetic historical purchase, not a checkout or inventory reservation.
            $order = Order::create([
                'user_id' => $customer->id, 'status' => 'completed',
                'subtotal' => $product->product_price, 'total' => $product->product_price,
                'discount' => 0, 'tax' => 0, 'shipping_fee' => 0,
                'idempotency_key' => 'amadis-react-completed-order-v1',
                'payload_hash' => hash('sha256', 'amadis-react-completed-order-v1'),
            ]);
            $order->items()->create([
                'product_id' => $product->product_id, 'product_title' => $product->product_title,
                'quantity' => 1, 'unit_price' => $product->product_price, 'subtotal' => $product->product_price,
            ]);
            $fixture = [
                'schema' => 1, 'origin' => $origin, 'customer_id' => $customer->id,
                'product_id' => $product->product_id, 'order_id' => $order->id,
                'unsubscribe_url' => (new CartReminder($customer, []))->unsubscribeUrl(),
            ];
            // Private handoff only. stdout remains the existing non-secret ID schema.
            $previousMask = umask(0077);
            try {
                $handle = fopen($private.'/react-fixture.json', 'x');
                if ($handle === false) {
                    throw new RuntimeException('React fixture channel unavailable.');
                }
                try {
                    $json = json_encode($fixture, JSON_THROW_ON_ERROR).PHP_EOL;
                    if (fwrite($handle, $json) !== strlen($json)) {
                        throw new RuntimeException('React fixture channel incomplete.');
                    }
                } finally {
                    fclose($handle);
                }
            } finally {
                umask($previousMask);
            }
        }

        return ['status' => 'provisioned', 'admin_id' => $admin->id, 'customer_id' => $customer->id];
    });
    echo json_encode($ids, JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}

if (($argv[1] ?? '') === 'amadis:lifecycle-snapshot') {
    if (count($argv) !== 2) {
        throw new RuntimeException('Snapshot accepts no arguments.');
    }
    $kernel->bootstrap();
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'sqlite'
        || $connection->getDatabaseName() !== $root.'/database.sqlite') {
        throw new RuntimeException('Snapshot connection outside isolation root.');
    }
    $connection->statement('PRAGMA query_only = ON');
    $snapshot = $connection->transaction(function () use ($connection): array {
        return [
            'product' => $connection->table('products')
                ->where('product_slug', 'amadis-demo-stock-37')
                ->first(['product_id', 'stock', 'is_active']),
            'order_count' => $connection->table('orders')->count(),
            'orders' => $connection->table('orders')->orderBy('id')
                ->get(['id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total'])->all(),
            'items' => $connection->table('order_items')->orderBy('id')
                ->get(['order_id', 'product_id', 'quantity', 'unit_price', 'subtotal'])->all(),
        ];
    });
    echo json_encode($snapshot, JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}

if (($argv[1] ?? '') === 'vendor:publish') {
    if (array_slice($argv, 1) !== ['vendor:publish', '--tag=laravel-assets', '--ansi', '--force']) {
        throw new RuntimeException('Only guarded laravel-assets publish is allowed.');
    }
    $kernel->bootstrap(); // publish targets are registered while providers boot
    foreach (ServiceProvider::pathsToPublish(null, 'laravel-assets') as $to) {
        $to = (string) $to;
        if (! str_starts_with($to, $root.'/') || in_array('..', explode('/', $to), true)) {
            throw new RuntimeException('Publish destination outside isolation root.');
        }
        if (is_dir($to)) {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($entries as $entry) {
                if ($entry->isLink() || (! $entry->isDir() && (! $entry->isFile() || lstat($entry->getPathname())['nlink'] !== 1))) {
                    throw new RuntimeException('Linked publish subtree refused.');
                }
            }
        }
        for ($cursor = $to; $cursor !== $root; $cursor = dirname($cursor)) {
            if (is_link($cursor)) {
                throw new RuntimeException('Linked publish destination refused.');
            }
        }
    }
}

$input = new ArgvInput;
$status = $kernel->handle($input, new ConsoleOutput);
$kernel->terminate($input, $status);
exit($status);
