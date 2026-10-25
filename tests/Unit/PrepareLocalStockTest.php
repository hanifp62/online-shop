<?php

namespace Tests\Unit;

use App\Console\Commands\PrepareLocalStock;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** FR-STOCK-20261025 / NFR-SAFE-20261025: never bootstrap the caller's application/database. */
final class PrepareLocalStockTest extends TestCase
{
    private Application $app;

    private DatabaseManager $database;

    private Container $previousContainer;

    private LocalStockRecordingPdo $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('env', 'local');
        $this->app->instance('config', new Repository([
            'database' => [
                'default' => 'stock_unit',
                'connections' => ['stock_unit' => [
                    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                ]],
            ],
        ]));
        $this->database = new DatabaseManager($this->app, new ConnectionFactory($this->app));
        $this->app->instance('db', $this->database);
        $this->pdo = new LocalStockRecordingPdo;
        $this->database->connection()->setPdo($this->pdo);
        $this->database->connection()->getSchemaBuilder()->create('products', function (Blueprint $table): void {
            $table->id('product_id');
            $table->string('product_slug');
            $table->string('product_title');
            $table->integer('stock');
            $table->boolean('is_active');
            $table->integer('product_cat');
            $table->string('product_brand');
            $table->integer('product_price');
            $table->string('product_image');
            $table->string('product_description');
            $table->timestamps();
        });
        foreach ([[11, 0, true], [12, 7, true], [13, 0, false], [14, 0, true], [15, 9, false]] as [$id, $stock, $active]) {
            $this->database->connection()->table('products')->insert([
                'product_id' => $id, 'product_slug' => 'unit-product-'.$id,
                'product_title' => 'Unit product '.$id, 'stock' => $stock, 'is_active' => $active,
                'product_cat' => 3, 'product_brand' => 'Unit fixture', 'product_price' => 125000,
                'product_image' => 'fixture.jpg', 'product_description' => 'Must remain unchanged',
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00',
            ]);
        }
        $this->pdo->statements = [];
    }

    protected function tearDown(): void
    {
        $this->database->purge('stock_unit');
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_default_is_read_only_preview_with_honest_before_after_and_proposal(): void
    {
        $before = $this->rows();
        $this->pdo->statements = [];
        [$exit, $output] = $this->runCommand(['--id' => ['11', '12', '13'], '--quantity' => '5']);
        $statements = $this->pdo->statements;
        $receipt = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exit);
        self::assertSame('preview', $receipt['mode']);
        self::assertSame(3, $receipt['selected_count']);
        self::assertSame(1, $receipt['eligible_count']);
        self::assertSame(0, $receipt['changed_count']);
        self::assertSame(0, $receipt['products'][0]['stock_before']);
        self::assertSame(0, $receipt['products'][0]['stock_after']);
        self::assertSame(5, $receipt['products'][0]['stock_proposed']);
        self::assertSame('would_set', $receipt['products'][0]['action']);
        self::assertSame('preserved_positive', $receipt['products'][1]['action']);
        self::assertSame('preserved_inactive', $receipt['products'][2]['action']);
        self::assertSame($before, $this->rows());
        foreach ($statements as $sql) {
            self::assertMatchesRegularExpression('/\Aselect\b/i', $sql);
        }
    }

    public function test_apply_changes_only_selected_active_zero_stock_and_is_idempotent(): void
    {
        $before = $this->rows();
        [$exit, $output] = $this->runCommand([
            '--id' => ['15', '13', '12', '11'], '--quantity' => '5', '--apply' => true,
        ]);
        $receipt = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $exit);
        self::assertSame('applied', $receipt['mode']);
        self::assertSame(1, $receipt['changed_count']);
        self::assertSame([11, 12, 13, 15], array_column($receipt['products'], 'product_id'));
        self::assertSame(5, $receipt['products'][0]['stock_after']);
        $expected = $before;
        $expected[0]['stock'] = 5;
        self::assertSame($expected, $this->rows(), 'Including timestamps and every non-stock attribute.');

        [$retryExit, $retryOutput] = $this->runCommand([
            '--id' => ['11', '12', '13', '15'], '--quantity' => '100', '--apply' => true,
        ]);
        self::assertSame(0, $retryExit);
        self::assertSame(0, json_decode($retryOutput, true, flags: JSON_THROW_ON_ERROR)['changed_count']);
        self::assertSame($expected, $this->rows());
    }

    public function test_apply_takes_sqlite_write_lock_before_reading_and_commits_after_updates(): void
    {
        [$exit] = $this->runCommand(['--id' => ['11'], '--quantity' => '1', '--apply' => true]);
        self::assertSame(0, $exit);
        self::assertSame('BEGIN IMMEDIATE TRANSACTION', $this->pdo->statements[0]);
        self::assertStringStartsWith('select ', $this->pdo->statements[1]);
        self::assertSame('COMMIT', $this->pdo->statements[array_key_last($this->pdo->statements)]);
        self::assertFalse($this->pdo->inTransaction());
    }

    public function test_maximum_quantity_can_be_applied_to_multiple_explicit_zero_stock_products(): void
    {
        [$exit, $output] = $this->runCommand(['--id' => ['11', '14'], '--quantity' => '100', '--apply' => true]);
        $receipt = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $exit);
        self::assertSame(2, $receipt['changed_count']);
        self::assertSame([100, 100], array_column($receipt['products'], 'stock_after'));
        self::assertSame([100, 7, 0, 100, 9], array_column($this->rows(), 'stock'));
    }

    public function test_missing_id_rejects_entire_selection_before_any_update(): void
    {
        $before = $this->rows();
        $this->pdo->statements = [];
        [$exit] = $this->runCommand(['--id' => ['11', '999'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertSame($before, $this->rows());
        self::assertSame([], array_values(array_filter($this->pdo->statements, fn ($sql) => str_starts_with($sql, 'update '))));
        self::assertContains('ROLLBACK', $this->pdo->statements);
    }

    public function test_later_write_failure_rolls_back_earlier_updates_and_redacts_exception(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_second BEFORE UPDATE ON products WHEN NEW.product_id = 14 BEGIN SELECT RAISE(ABORT, 'private-dsn-sentinel'); END");
        $before = $this->rows();
        [$exit, $output] = $this->runCommand(['--id' => ['11', '14'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertSame($before, $this->rows());
        self::assertStringNotContainsString('private-dsn-sentinel', $output);
        self::assertStringNotContainsString('"mode": "applied"', $output);
        self::assertContains('ROLLBACK', $this->pdo->statements);
        self::assertFalse($this->pdo->inTransaction());
    }

    public function test_invalid_stock_in_any_selected_row_rejects_all_writes(): void
    {
        $this->database->connection()->table('products')->where('product_id', 14)->update(['stock' => -1]);
        $before = $this->rows();
        [$exit] = $this->runCommand(['--id' => ['11', '14'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertSame($before, $this->rows());
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_arguments_fail_without_queries(array $options): void
    {
        [$exit] = $this->runCommand($options);
        self::assertSame(1, $exit);
        self::assertSame([], $this->pdo->statements);
    }

    public static function invalidArguments(): array
    {
        $valid = ['--id' => ['11'], '--quantity' => '5', '--apply' => true];

        return [
            'no IDs' => [['--quantity' => '5', '--apply' => true]],
            'no quantity' => [['--id' => ['11'], '--apply' => true]],
            'all is not an ID' => [array_replace($valid, ['--id' => ['all']])],
            'comma list' => [array_replace($valid, ['--id' => ['11,14']])],
            'duplicate' => [array_replace($valid, ['--id' => ['11', '11']])],
            'zero ID' => [array_replace($valid, ['--id' => ['0']])],
            'negative ID' => [array_replace($valid, ['--id' => ['-11']])],
            'overflow ID' => [array_replace($valid, ['--id' => ['999999999999999999999']])],
            'invalid last ID' => [array_replace($valid, ['--id' => ['11', 'oops']])],
            'too many IDs' => [array_replace($valid, ['--id' => array_map('strval', range(1, 21))])],
            'zero quantity' => [array_replace($valid, ['--quantity' => '0'])],
            'negative quantity' => [array_replace($valid, ['--quantity' => '-1'])],
            'over limit' => [array_replace($valid, ['--quantity' => '101'])],
            'decimal' => [array_replace($valid, ['--quantity' => '1.5'])],
            'scientific notation' => [array_replace($valid, ['--quantity' => '1e1'])],
            'whitespace' => [array_replace($valid, ['--quantity' => ' 5'])],
        ];
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_non_local_environment_is_rejected_before_connection(string $environment): void
    {
        $this->app->instance('env', $environment);
        [$exit] = $this->runCommand(['--id' => ['11'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertSame([], $this->pdo->statements);
    }

    public static function nonLocalEnvironments(): array
    {
        return array_map(fn ($env) => [$env], ['production', 'staging', 'testing', 'LOCAL', '']);
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_configuration_is_rejected_without_opening_connection(array $configuration): void
    {
        $this->app['config']->set('database.default', 'unsafe');
        $this->app['config']->set('database.connections.unsafe', $configuration);
        [$exit, $output] = $this->runCommand(['--id' => ['11'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertArrayNotHasKey('unsafe', $this->database->getConnections());
        self::assertSame([], $this->pdo->statements);
        self::assertStringNotContainsString('private-dsn-sentinel', $output);
    }

    public static function unsafeConnections(): array
    {
        $mysql = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'database' => 'unit_only'];
        $sqlite = ['driver' => 'sqlite', 'database' => ':memory:'];

        return [
            'remote host' => [array_replace($mysql, ['host' => 'db.example.test'])],
            'loopback-looking DNS' => [array_replace($mysql, ['host' => '127.0.0.1.example.test'])],
            'host list' => [array_replace($mysql, ['host' => ['127.0.0.1', 'db.example.test']])],
            'missing host' => [['driver' => 'mysql', 'database' => 'unit_only']],
            'remote URL' => [array_replace($mysql, ['url' => 'mysql://private-dsn-sentinel@db.example.test/private'])],
            'even local URL is ambiguous' => [array_replace($mysql, ['url' => 'mysql://localhost/unit_only'])],
            'URL must not be an array' => [array_replace($mysql, ['url' => []])],
            'read split' => [array_replace($mysql, ['read' => ['host' => '127.0.0.1']])],
            'empty read split' => [array_replace($mysql, ['read' => []])],
            'empty write split' => [array_replace($mysql, ['write' => []])],
            'write split' => [array_replace($mysql, ['write' => ['host' => 'db.example.test']])],
            'custom DSN' => [array_replace($mysql, ['dsn' => 'private-dsn-sentinel'])],
            'database DSN injection' => [array_replace($mysql, ['database' => 'unit;host=db.example.test'])],
            'port DSN injection' => [array_replace($mysql, ['port' => '3306;host=db.example.test'])],
            'out of range port' => [array_replace($mysql, ['port' => '65536'])],
            'PDO options' => [array_replace($mysql, ['options' => [1002 => 'private-dsn-sentinel']])],
            'charset SQL' => [array_replace($mysql, ['charset' => "utf8mb4'; private-dsn-sentinel"])],
            'session setup SQL' => [array_replace($mysql, ['isolation_level' => 'private-dsn-sentinel'])],
            'socket URI' => [array_replace($mysql, ['unix_socket' => 'tcp://db.example.test:3306'])],
            'nonexistent socket' => [array_replace($mysql, ['unix_socket' => '/no-such-local-stock-socket'])],
            'sqlite URI' => [array_replace($sqlite, ['database' => 'file:/tmp/unit.sqlite?mode=rw'])],
            'sqlite UNC' => [array_replace($sqlite, ['database' => '//server/share/unit.sqlite'])],
            'sqlite missing file' => [array_replace($sqlite, ['database' => '/no-such-local-stock-database'])],
            'sqlite journal mutation on connect' => [array_replace($sqlite, ['journal_mode' => 'WAL'])],
            'sqlite pragma mutation on connect' => [array_replace($sqlite, ['pragmas' => ['user_version' => 99]])],
            'sqlite custom synchronous pragma' => [array_replace($sqlite, ['synchronous' => 'OFF'])],
            'sqlite memory URI suffix' => [array_replace($sqlite, ['database' => 'unit?mode=memory'])],
            'sqlite extension' => [array_replace($sqlite, ['extensions' => ['/tmp/extension']])],
            'other driver' => [['driver' => 'pgsql', 'host' => '127.0.0.1']],
        ];
    }

    public function test_local_mysql_literals_and_memory_sqlite_configuration_are_allowed(): void
    {
        foreach (['127.0.0.1', '::1', 'localhost'] as $host) {
            self::assertTrue(PrepareLocalStock::isLocalConfiguration([
                'driver' => 'mysql', 'host' => $host, 'port' => 3306, 'database' => 'unit_only',
            ], $this->app->basePath()));
        }
        self::assertTrue(PrepareLocalStock::isLocalConfiguration([
            'driver' => 'sqlite', 'database' => ':memory:',
        ], $this->app->basePath()));
    }

    public function test_sqlite_file_must_exist_inside_canonical_application_root(): void
    {
        $file = tmpfile();
        self::assertNotFalse($file);
        try {
            $path = stream_get_meta_data($file)['uri'];
            $config = ['driver' => 'sqlite', 'database' => $path];
            self::assertTrue(PrepareLocalStock::isLocalConfiguration($config, dirname($path)));
            self::assertFalse(PrepareLocalStock::isLocalConfiguration($config, $this->app->basePath()));
        } finally {
            fclose($file);
        }
    }

    #[DataProvider('mysqlTransports')]
    public function test_mysql_transport_is_verified_not_just_the_app_url(mixed $status, bool $local): void
    {
        self::assertSame($local, PrepareLocalStock::isLocalMysqlTransport($status));
    }

    public static function mysqlTransports(): array
    {
        return [
            ['127.0.0.1 via TCP/IP', true],
            ['::1 via TCP/IP', true],
            ['Localhost via UNIX socket', true],
            ['localhost via TCP/IP', false],
            ['db.example.test via TCP/IP', false],
            ['127.0.0.1.example.test via TCP/IP', false],
            ['10.0.0.1 via TCP/IP', false],
            ['', false],
            [null, false],
        ];
    }

    public function test_resolved_configuration_is_checked_even_if_repository_configuration_is_local(): void
    {
        $original = $this->app['config']->get('database.connections.stock_unit');
        $this->database->purge('stock_unit');
        $this->app['config']->set('database.connections.stock_unit', ['driver' => 'sqlite', 'database' => 'file:private-dsn-sentinel']);
        $this->database->connection(); // Resolves configuration only, PDO remains lazy.
        $this->app['config']->set('database.connections.stock_unit', $original);
        [$exit, $output] = $this->runCommand(['--id' => ['11'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertStringNotContainsString('private-dsn-sentinel', $output);
    }

    public function test_existing_transaction_is_rejected_without_committing_callers_work(): void
    {
        $this->database->connection()->beginTransaction();
        [$exit] = $this->runCommand(['--id' => ['11'], '--quantity' => '5', '--apply' => true]);
        self::assertSame(1, $exit);
        self::assertTrue($this->pdo->inTransaction());
        self::assertNotContains('COMMIT', $this->pdo->statements);
        $this->database->connection()->rollBack();
    }

    private function runCommand(array $options): array
    {
        $command = new PrepareLocalStock;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $exit = $tester->execute($options, ['interactive' => false, 'decorated' => false]);

        return [$exit, $tester->getDisplay()];
    }

    private function rows(): array
    {
        return $this->database->connection()->table('products')->orderBy('product_id')->get()
            ->map(fn ($product) => (array) $product)->all();
    }
}

final class LocalStockRecordingPdo extends PDO
{
    public array $statements = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function exec(string $statement): int|false
    {
        $this->statements[] = $statement;

        return parent::exec($statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->statements[] = $query;

        return parent::prepare($query, $options);
    }

    public function commit(): bool
    {
        $this->statements[] = 'COMMIT';

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->statements[] = 'ROLLBACK';

        return parent::rollBack();
    }
}
