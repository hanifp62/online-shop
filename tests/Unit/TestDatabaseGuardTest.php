<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestDatabaseGuardTest extends TestCase
{
    public static function targets(): array
    {
        $disposable = 'zafira_upgrade13_phpunit_20261015_0420';

        return [
            'no flag, nothing set' => [[], 'REJECTED'],
            'no flag, operational mysql' => [['PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => 'ecommerce'], 'REJECTED'],
            'no flag, mysql url' => [['PROBE_URL' => 'mysql://u:p@127.0.0.1/ecommerce'], 'REJECTED'],
            'no flag, sqlite file' => [['PROBE_DRIVER' => 'sqlite', 'PROBE_DATABASE' => 'database/database.sqlite'], 'REJECTED'],
            'no flag, disposable mysql still needs flag' => [['PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => $disposable], 'REJECTED'],
            'no flag, sqlite memory split' => [['PROBE_DRIVER' => 'sqlite', 'PROBE_DATABASE' => ':memory:', 'PROBE_READ' => '1'], 'REJECTED'],
            'no flag, sqlite memory' => [['PROBE_DRIVER' => 'sqlite', 'PROBE_DATABASE' => ':memory:'], 'ACCEPTED'],
            'flag, nothing set' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1'], 'REJECTED'],
            'flag, other mysql' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => 'other_tests'], 'REJECTED'],
            'flag, sqlite file' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'sqlite', 'PROBE_DATABASE' => 'database/database.sqlite'], 'REJECTED'],
            'flag, operational mysql' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => 'ecommerce'], 'REJECTED'],
            'flag, url overrides to operational' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => $disposable, 'PROBE_URL' => 'mysql://u:p@127.0.0.1/ecommerce'], 'REJECTED'],
            'flag, sqlite memory' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'sqlite', 'PROBE_DATABASE' => ':memory:'], 'REJECTED'],
            'flag, disposable mysql' => [['ZAFIRA_SAFE_MYSQL_TESTS' => '1', 'PROBE_DRIVER' => 'mysql', 'PROBE_DATABASE' => $disposable], 'ACCEPTED'],
        ];
    }

    #[DataProvider('targets')]
    public function test_guard_resolves_target_before_any_connection(array $env, string $expected): void
    {
        // Child gets only PATH plus fixture vars: no inherited DB_* or flag, no kernel, no PDO.
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__).'/Support/guard-probe.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => getenv('PATH') ?: '/usr/bin:/bin'] + $env,
        );
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $code = proc_close($process);

        self::assertSame(0, $code, $err);
        self::assertSame($expected, trim($out));
    }
}
