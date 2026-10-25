<?php

namespace App\Console\Commands;

use App\Models\Product;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use PDO;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class PrepareLocalStock extends Command
{
    protected $signature = 'stock:prepare-local
        {--id=* : ID produk eksplisit, ulangi opsi untuk setiap produk (maksimal 20)}
        {--quantity= : Stok tujuan untuk produk aktif yang stoknya tepat nol (1-100)}
        {--apply : Terapkan perubahan; tanpa opsi ini hanya preview read-only}';

    protected $description = 'Siapkan stok simulasi lokal tanpa mengubah stok positif, produk nonaktif, atau atribut lain.';

    public function handle(): int
    {
        try {
            $ids = $this->option('id');
            $quantity = $this->positiveInteger($this->option('quantity'), 100);
            if (! is_array($ids) || $ids === [] || count($ids) > 20 || $quantity === null) {
                throw new DomainException('Wajib memilih 1-20 ID melalui --id dan --quantity bilangan bulat 1-100.');
            }
            $ids = array_map(fn ($id) => $this->positiveInteger($id, PHP_INT_MAX), $ids);
            if (in_array(null, $ids, true) || count(array_unique($ids)) !== count($ids)) {
                throw new DomainException('Semua ID harus bilangan bulat positif yang berbeda; tidak ada opsi semua produk.');
            }
            sort($ids, SORT_NUMERIC);

            $connection = self::guardedConnection($this->laravel);
            $apply = (bool) $this->option('apply');
            if ($apply) {
                if ($connection->getDriverName() === 'sqlite') {
                    // SQLite ignores FOR UPDATE. Use BEGIN IMMEDIATE without changing shared configuration.
                    $connection = new SQLiteConnection(
                        $connection->getPdo(), $connection->getDatabaseName(), $connection->getTablePrefix(),
                        array_replace($connection->getConfig(), ['transaction_mode' => 'IMMEDIATE']),
                    );
                } else {
                    $table = $connection->getTablePrefix().(new Product)->getTable();
                    $metadata = $connection->selectOne(
                        'SELECT ENGINE AS engine FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                        [$table], false,
                    );
                    if (strtolower((string) ($metadata->engine ?? '')) !== 'innodb') {
                        throw new DomainException('Apply memerlukan tabel products InnoDB agar rollback dan row locking berlaku.');
                    }
                }
                $receipt = $connection->transaction(fn () => $this->prepare($connection, $ids, $quantity, true));
            } else {
                $receipt = $this->prepare($connection, $ids, $quantity, false);
            }

            $this->output->writeln($receipt, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable) {
            // Driver exceptions can contain credentials, DSNs or SQL bindings.
            $this->error('Operasi gagal; commit tidak terkonfirmasi. Jalankan probe read-only sebelum mencoba lagi. Detail koneksi disembunyikan.');
        }

        return self::FAILURE;
    }

    /** Shared with the read-only operator probe; no PDO is opened until both configurations pass. */
    public static function guardedConnection(Application $app): Connection
    {
        if (! $app->environment('local')) {
            throw new DomainException('Ditolak: command dan probe stok hanya untuk environment local.');
        }

        $manager = $app['db'];
        $name = (new Product)->getConnectionName() ?? $manager->getDefaultConnection();
        $raw = $app['config']->get('database.connections.'.$name);
        if (! is_array($raw) || ! self::isLocalConfiguration($raw, $app->basePath())) {
            throw new DomainException('Ditolak: konfigurasi DB harus lokal dan eksplisit, tanpa URL, split connection atau opsi PDO khusus.');
        }
        $connection = $manager->connection($name);
        if (! self::isLocalConfiguration($connection->getConfig(), $app->basePath())) {
            throw new DomainException('Ditolak: koneksi DB efektif bukan endpoint lokal yang tidak ambigu.');
        }
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName() === 'sqlite' ? 'sqlite' : 'mysql';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== $driver) {
            throw new DomainException('Ditolak: driver aktual tidak sesuai konfigurasi.');
        }
        if ($driver === 'mysql' && ! self::isLocalMysqlTransport($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS))) {
            throw new DomainException('Ditolak: transport MySQL aktual bukan loopback atau Unix socket lokal.');
        }
        if ($connection->transactionLevel() !== 0 || $pdo->inTransaction()) {
            throw new DomainException('Ditolak: koneksi sedang dipakai transaksi lain.');
        }

        return $connection;
    }

    public static function isLocalConfiguration(array $config, string $basePath): bool
    {
        if (isset($config['read']) || isset($config['write'])) {
            return false;
        }
        foreach (['url', 'dsn'] as $key) {
            if (isset($config[$key]) && $config[$key] !== '') {
                return false;
            }
        }
        foreach (['options', 'extensions', 'pragmas', 'modes', 'isolation_level', 'timezone'] as $key) {
            if (! empty($config[$key])) {
                return false;
            }
        }
        $driver = $config['driver'] ?? null;
        $database = $config['database'] ?? null;
        if (! is_string($database) || $database === '' || preg_match('/[;`\x00-\x1f\x7f]/', $database)) {
            return false;
        }
        if ($driver === 'sqlite') {
            // A connector journal-mode PRAGMA could write before a preview's first SELECT.
            if (isset($config['journal_mode']) || isset($config['synchronous']) || isset($config['busy_timeout'])
                || ! empty($config['host']) || ! empty($config['unix_socket'])) {
                return false;
            }
            if ($database === ':memory:') {
                return true;
            }
            if (strpbrk($database, ':?\\#') !== false || str_starts_with($database, '//')) {
                return false;
            }
            $root = realpath($basePath);
            $path = realpath($database);

            return $root !== false && $path !== false && is_file($path)
                && str_starts_with($path, $root.DIRECTORY_SEPARATOR);
        }
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }
        foreach (['charset', 'collation'] as $key) {
            if (isset($config[$key]) && (! is_string($config[$key]) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $config[$key]))) {
                return false;
            }
        }
        $host = $config['host'] ?? null;
        $socket = $config['unix_socket'] ?? '';
        if (! is_string($socket) || ! in_array($host, ['127.0.0.1', '::1', 'localhost'], true)) {
            return false;
        }
        if ($socket !== '' && (! str_starts_with($socket, '/') || str_starts_with($socket, '//')
            || preg_match('/[;:\x00-\x1f\x7f]/', $socket) || @filetype($socket) !== 'socket')) {
            return false;
        }
        $port = $config['port'] ?? null;

        return (is_string($port) || is_int($port)) && preg_match('/\A[1-9][0-9]{0,4}\z/', (string) $port)
            && (int) $port <= 65535;
    }

    public static function isLocalMysqlTransport(mixed $status): bool
    {
        return is_string($status) && (bool) preg_match(
            '/\A(?:Localhost via UNIX socket|(?:127\.0\.0\.1|::1) via TCP\/IP)\z/i', $status,
        );
    }

    private function positiveInteger(mixed $value, int $maximum): ?int
    {
        if (! is_string($value) || ! preg_match('/\A[1-9][0-9]*\z/', $value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);

        return $integer === false ? null : $integer;
    }

    private function prepare(Connection $connection, array $ids, int $quantity, bool $apply): string
    {
        $table = (new Product)->getTable();
        $query = $connection->table($table)->useWritePdo()->whereIn('product_id', $ids)->orderBy('product_id');
        if ($apply) {
            $query->lockForUpdate();
        }
        $products = $query->get(['product_id', 'product_slug', 'product_title', 'stock', 'is_active']);
        if ($products->count() !== count($ids)) {
            throw new DomainException('Pilihan ditolak: setidaknya satu ID tidak ditemukan. Tidak ada stok yang diubah.');
        }

        $rows = [];
        $eligible = 0;
        // Validate the entire locked selection before the first UPDATE.
        foreach ($products as $product) {
            $stock = filter_var($product->stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($stock === false || ! in_array($product->is_active, [0, 1, '0', '1', false, true], true)) {
                throw new DomainException('Pilihan ditolak: nilai stock atau is_active tidak valid. Tidak ada stok yang diubah.');
            }
            $active = (bool) $product->is_active;
            $change = $active && $stock === 0;
            $eligible += (int) $change;
            $rows[] = [
                'product_id' => (int) $product->product_id,
                'product_slug' => $product->product_slug,
                'product_title' => $product->product_title,
                'is_active' => $active,
                'stock_before' => $stock,
                'stock_after' => $stock,
                'stock_proposed' => $change ? $quantity : $stock,
                'action' => ! $active ? 'preserved_inactive' : ($change ? 'would_set' : 'preserved_positive'),
            ];
        }

        $changed = 0;
        if ($apply) {
            foreach ($rows as &$row) {
                if ($row['action'] !== 'would_set') {
                    continue;
                }
                // Query builder deliberately preserves updated_at and bypasses unrelated model side effects.
                $affected = $connection->table($table)->where('product_id', $row['product_id'])
                    ->where('stock', 0)->where('is_active', true)->update(['stock' => $quantity]);
                if ($affected !== 1) {
                    throw new DomainException('Perubahan serentak terdeteksi; transaksi stok dibatalkan.');
                }
                $after = $connection->table($table)->useWritePdo()->where('product_id', $row['product_id'])->value('stock');
                if ((int) $after !== $quantity) {
                    throw new DomainException('Verifikasi stok sesudah update gagal; transaksi dibatalkan.');
                }
                $row['stock_after'] = (int) $after;
                $row['action'] = 'set';
                $changed++;
            }
            unset($row);
        }

        // Encode before commit so a receipt serialization failure also rolls back all writes.
        return json_encode([
            'requirement' => 'FR-STOCK-20261025',
            'mode' => $apply ? 'applied' : 'preview',
            'environment' => 'local',
            'driver' => $connection->getDriverName(),
            'local_endpoint' => true,
            'quantity' => $quantity,
            'selected_count' => count($ids),
            'eligible_count' => $eligible,
            'changed_count' => $changed,
            'products' => $rows,
            'cache' => 'unchanged; beranda/rekomendasi dapat menyimpan stok lama hingga TTL 300 detik. Muat ulang katalog/PDP untuk data baru.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
