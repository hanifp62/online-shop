<?php

namespace Tests;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public static function validateTestDatabase(array $connection, bool $safeMySql): void
    {
        $driver = $connection['driver'] ?? null;
        $schema = $connection['database'] ?? null;
        if (isset($connection['read']) || isset($connection['write'])) {
            throw new \RuntimeException('Split test connections are not allowed.');
        }
        if ($safeMySql ? ($driver !== 'mysql' || $schema !== 'zafira_upgrade13_phpunit_20261015_0420')
            : ($driver !== 'sqlite' || $schema !== ':memory:')) {
            throw new \RuntimeException('Unsafe test database rejected before connection.');
        }
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = (new ConfigurationUrlParser)->parseConfiguration(
            $config->get('database.connections.'.$config->get('database.default'), [])
        );
        self::validateTestDatabase($connection, getenv('ZAFIRA_SAFE_MYSQL_TESTS') === '1');
        $schema = $connection['database'];
        if (getenv('ZAFIRA_SAFE_MYSQL_TESTS') !== '1') {
            return $app;
        }

        // Read-only proof of the live target before RefreshDatabase can open a transaction.
        $database = $app->make('db')->connection();
        if ($database->getDriverName() !== 'mysql'
            || $database->getDatabaseName() !== $schema
            || $database->selectOne('select database() as db')->db !== $schema) {
            throw new \RuntimeException('Effective test connection does not match approved schema.');
        }
        $expected = array_map(fn ($file) => basename($file, '.php'), glob($app->databasePath('migrations/*.php')));
        sort($expected);
        $applied = $database->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $engines = array_unique(array_map(fn ($row) => strtolower((string) $row->engine), $database->select(
            "select engine as engine from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'"
        )));
        if (count($expected) !== 30
            || $applied !== $expected
            || $engines !== ['innodb']) {
            throw new \RuntimeException('Isolated, migrated InnoDB MySQL test schema required; refusing refresh.');
        }

        // Schema comes from ordinary `migrate`; RefreshDatabase then only wraps each test in a rolled-back transaction.
        RefreshDatabaseState::$migrated = true;
        $app['events']->listen(CommandStarting::class, function ($event) {
            if (in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'], true)) {
                throw new \RuntimeException("Forbidden destructive command in safe MySQL tests: {$event->command}");
            }
        });

        return $app;
    }
}
