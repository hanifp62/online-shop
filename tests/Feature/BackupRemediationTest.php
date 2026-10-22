<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Closure;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * REM-20261021 / FR-REM-003: acceptance, bukan karakterisasi cacat probe arsip.
 * Tanpa RefreshDatabase, migrasi, artisan, server, atau data operasional.
 * Eksekusi hanya oleh integrator melalui launcher test terisolasi yang disetujui.
 */
class BackupRemediationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/zafira-rem-backup-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->dir, 0700);
        config(['backup.path' => $this->dir, 'database.connections.rem_backup' => [
            'driver' => 'mysql', 'host' => 'fallback.invalid', 'port' => 3306,
            'username' => 'fixture_user', 'password' => 'fixture-password-only',
            'database' => 'fixture_database', 'unix_socket' => '', 'options' => [],
        ]]);
        $this->fakeProcesses();
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        try {
            DB::purge('rem_backup');
            File::deleteDirectory($this->dir);
        } finally {
            parent::tearDown();
        }
    }

    private function service(): DatabaseBackupService
    {
        return new DatabaseBackupService('rem_backup');
    }

    private function fakeProcesses(?Closure $callback = null): void
    {
        // Factory::fake() mengganti handler tetapi mempertahankan recorded calls.
        $factory = new ProcessFactory;
        $factory->fake($callback);
        $factory->preventStrayProcesses();
        Process::swap($factory);
    }

    private function fakeDump(?callable $inspect = null): void
    {
        $this->fakeProcesses(function ($process) use ($inspect) {
            if ($inspect !== null) {
                $inspect($process);
            }
            foreach ($process->command as $argument) {
                if (str_starts_with($argument, '--result-file=')) {
                    file_put_contents(substr($argument, strlen('--result-file=')), '-- synthetic SQL dump'."\n");
                }
            }

            return Process::result();
        });
    }

    public function test_ops01_dump_uses_effective_database_url_without_password_in_argv(): void
    {
        config(['database.connections.rem_backup.url' => 'mysql://url_user:fixture%2Burl%3Apassword@effective.invalid:3307/url_database']);
        $this->fakeDump(function ($process): void {
            $command = $process->command;
            // Boolean assertions avoid printing argv/environment on failure.
            $this->assertTrue(in_array('--host=effective.invalid', $command, true), 'Effective URL host required.');
            $this->assertTrue(in_array('--port=3307', $command, true), 'Effective URL port required.');
            $this->assertTrue(in_array('--user=url_user', $command, true), 'Effective URL user required.');
            $this->assertTrue(in_array('url_database', $command, true), 'Effective URL database required.');
            $this->assertFalse(in_array('fixture_database', $command, true), 'Fallback database must not be dumped.');
            $this->assertTrue(($process->environment['MYSQL_PWD'] ?? null) === 'fixture+url:password', 'Password must use private process environment.');
            $this->assertFalse(str_contains(implode(' ', $command), 'password'), 'No password in argv.');
        });

        $path = $this->service()->backup();

        $this->assertFileExists($path);
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_ops01_socket_transport_is_explicit_and_does_not_silently_use_tcp(): void
    {
        config(['database.connections.rem_backup.unix_socket' => '/synthetic/mysql.sock']);
        $this->fakeDump(function ($process): void {
            $this->assertTrue(in_array('--socket=/synthetic/mysql.sock', $process->command, true));
            $this->assertFalse((bool) array_filter($process->command, fn ($arg) => str_starts_with($arg, '--host=') || str_starts_with($arg, '--port=')));
        });

        $this->service()->backup();
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_ops01_tls_ca_is_forwarded_or_fails_closed_before_dump(): void
    {
        if (! defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $this->markTestSkipped('pdo_mysql absent: TLS option mapping requires driver constants.');
        }
        config(['database.connections.rem_backup.options' => [constant('PDO::MYSQL_ATTR_SSL_CA') => '/synthetic/ca.pem']]);
        $tlsSeen = false;
        $this->fakeDump(function ($process) use (&$tlsSeen): void {
            $tlsSeen = in_array('--ssl-ca=/synthetic/ca.pem', $process->command, true);
            $this->assertTrue($tlsSeen, 'Never silently drop configured TLS.');
        });
        try {
            $this->service()->backup();
            $this->assertTrue($tlsSeen);
        } catch (RuntimeException) {
            Process::assertNothingRan();
            $this->assertSame([], $this->service()->backups());
        }
    }

    public function test_ops01_unsupported_pdo_options_fail_closed_before_process(): void
    {
        config(['database.connections.rem_backup.options' => [999999 => 'synthetic-option']]);
        $this->fakeDump();

        $this->assertInstanceOf(RuntimeException::class, $this->failure(fn () => $this->service()->backup()));
        Process::assertNothingRan();
        $this->assertSame([], $this->artifactHashes());
    }

    public function test_ops01_option_like_database_target_is_rejected_before_process(): void
    {
        config(['database.connections.rem_backup.database' => '--all-databases']);
        $this->fakeDump();
        $error = $this->failure(fn () => $this->service()->backup());

        $this->assertInstanceOf(RuntimeException::class, $error);
        Process::assertNothingRan();
        $this->assertSame([], $this->service()->backups());
    }

    public function test_ops01_failed_dump_does_not_expose_credentials_in_exception_logs_or_output(): void
    {
        $marker = 'synthetic-sensitive-marker';
        config(['database.connections.rem_backup.password' => $marker]);
        Log::spy();
        $this->fakeProcesses(fn () => Process::result(output: $marker, errorOutput: $marker, exitCode: 1));
        ob_start();
        try {
            $error = $this->failure(fn () => $this->service()->backup());
        } finally {
            $output = (string) ob_get_clean();
        }

        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertFalse(str_contains($output, $marker), 'Sensitive process output must not be echoed.');
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            $this->assertFalse(str_contains($cause->getMessage(), $marker), 'Sensitive process output must not escape in exception chains.');
        }
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $level) {
            Log::shouldNotHaveReceived($level, fn (...$args) => str_contains(json_encode($args, JSON_PARTIAL_OUTPUT_ON_ERROR), $marker));
        }
        $this->assertSame([], $this->service()->backups());
    }

    #[DataProvider('invalidRetention')]
    public function test_ops02_invalid_retention_is_rejected_without_any_deletion(mixed $keep): void
    {
        $this->fakeDump();
        $first = $this->service()->backup();
        $this->travel(1)->seconds();
        $second = $this->service()->backup();
        $before = $this->artifactHashes();

        $error = $this->failure(fn () => $this->service()->prune($keep));

        $this->assertInstanceOf(Throwable::class, $error, 'Invalid retention must be rejected, not clamped or coerced.');
        $this->assertSame($before, $this->artifactHashes());
        $this->assertFileExists($first);
        $this->assertFileExists($second);
    }

    public static function invalidRetention(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'decimal' => [1.5], 'decimal string' => ['1.5'],
            'text' => ['invalid'], 'empty' => [''], 'null' => [null], 'boolean' => [true],
            'overflow' => [str_repeat('9', 40)], 'numeric exponent' => ['1e2'], 'padded' => [' 1 ']];
    }

    #[DataProvider('invalidRetention')]
    public function test_ops02_invalid_config_retention_fails_before_backup_or_deletion(mixed $keep): void
    {
        $this->fakeDump();
        $previous = $this->service()->backup();
        $before = $this->artifactHashes();
        config(['backup.keep' => $keep]);
        $this->fakeProcesses();

        $this->assertInstanceOf(RuntimeException::class, $this->failure(fn () => $this->service()->backup()));
        Process::assertNothingRan();
        $this->assertSame($before, $this->artifactHashes());
        $this->assertFileExists($previous);
    }

    public function test_ops02_corrupt_newer_gzip_cannot_displace_last_valid_backup(): void
    {
        $this->fakeDump();
        $previous = $this->service()->backup();
        $corrupt = $this->dir.'/rem_backup-99999999.sql.gz';
        file_put_contents($corrupt, 'not gzip');
        file_put_contents($corrupt.'.sha256', hash_file('sha256', $corrupt).'  '.basename($corrupt)."\n");

        $this->assertSame(0, $this->service()->prune(1));
        $this->assertFileExists($previous);
        $this->assertFileExists($previous.'.sha256');
        $this->assertFileExists($corrupt);
    }

    #[DataProvider('invalidSqliteCandidates')]
    public function test_ops02_invalid_sqlite_snapshot_cannot_displace_last_restorable_backup(string $kind): void
    {
        $this->syntheticSqlite();
        $previous = $this->service()->backup();
        $candidate = $this->dir.'/rem_backup-99999999.sqlite.gz';
        $payload = 'synthetic non-database';
        if ($kind === 'foreign-key-invalid') {
            $fixture = $this->dir.'/invalid-source.sqlite';
            $pdo = new PDO('sqlite:'.$fixture);
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->exec('CREATE TABLE parent (id INTEGER PRIMARY KEY)');
            $pdo->exec('CREATE TABLE child (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parent(id))');
            $pdo->exec('INSERT INTO child VALUES (1, 999)');
            $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertCount(1, $pdo->query('PRAGMA foreign_key_check')->fetchAll());
            $pdo = null;
            $payload = file_get_contents($fixture);
        }
        file_put_contents($candidate, gzencode($payload));
        file_put_contents($candidate.'.sha256', hash_file('sha256', $candidate).'  '.basename($candidate)."\n");
        $before = $this->artifactHashes();
        DB::partialMock()->shouldReceive('connection')->never();

        $this->assertInstanceOf(RuntimeException::class, $this->failure(fn () => $this->service()->verify($candidate)));
        $this->assertSame(0, $this->service()->prune(1));
        $this->assertSame($before, $this->artifactHashes());
        $this->assertSame(['fixture' => 1], $this->service()->verify($previous));
        $this->assertFileExists($previous.'.sha256');
    }

    public static function invalidSqliteCandidates(): array
    {
        return ['non-database' => ['non-database'], 'foreign-key-invalid' => ['foreign-key-invalid']];
    }

    public function test_ops02_positive_decimal_string_retention_is_accepted(): void
    {
        config(['backup.keep' => '1']);
        $this->fakeDump();
        $this->service()->backup();
        $this->travel(1)->seconds();
        $newest = $this->service()->backup();

        $this->assertSame(1, $this->service()->prune('1'));
        $this->assertSame([$newest], $this->service()->backups());
    }

    public function test_ops02_retention_one_preserves_last_complete_backup_and_other_connections(): void
    {
        $this->fakeDump();
        $old = $this->service()->backup();
        $this->travel(1)->seconds();
        $new = $this->service()->backup();
        $foreign = $this->dir.'/other-20261021.sql.gz';
        file_put_contents($foreign, 'synthetic foreign artifact');

        $this->assertSame(1, $this->service()->prune(1));
        $this->assertSame(0, $this->service()->prune(1));
        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist($old.'.sha256');
        $this->assertSame([$new], $this->service()->backups());
        $this->assertFileExists($new.'.sha256');
        $this->assertFileExists($foreign);
    }

    public function test_data05_restore_validates_snapshot_not_changed_live_database(): void
    {
        $pdo = $this->syntheticSqlite();
        $path = $this->service()->backup();
        $pdo->exec("UPDATE fixture SET value = 'changed'; INSERT INTO fixture VALUES (2, 'later')");

        $this->assertSame(['fixture' => 1], $this->service()->verify($path));
    }

    public function test_data05_restore_never_needs_the_live_connection(): void
    {
        $this->syntheticSqlite();
        $path = $this->service()->backup();
        DB::partialMock()->shouldReceive('connection')->never();

        $this->assertSame(['fixture' => 1], $this->service()->verify($path));
    }

    public function test_data05_legacy_gzip_and_checksum_artifact_remains_verifiable(): void
    {
        $this->syntheticSqlite();
        $path = $this->dir.'/rem_backup-legacy.sqlite.gz';
        file_put_contents($path, gzencode(file_get_contents($this->dir.'/source.sqlite')));
        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.basename($path)."\n");
        DB::partialMock()->shouldReceive('connection')->never();

        $this->assertSame(['fixture' => 1], $this->service()->verify($path));
    }

    #[DataProvider('invalidSnapshots')]
    public function test_data05_checksum_alone_does_not_prove_restore_integrity(string $payload): void
    {
        config(['database.connections.rem_backup.driver' => 'sqlite']);
        $path = $this->dir.'/rem_backup-invalid.sqlite.gz';
        file_put_contents($path, $payload);
        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.basename($path)."\n");
        DB::partialMock()->shouldReceive('connection')->never();

        $this->assertInstanceOf(RuntimeException::class, $this->failure(fn () => $this->service()->verify($path)));
    }

    public static function invalidSnapshots(): array
    {
        return ['not gzip' => ['not gzip'], 'not sqlite' => [gzencode('not sqlite')],
            'truncated gzip' => [substr(gzencode(str_repeat('synthetic', 100)), 0, -8)]];
    }

    public function test_data06_failed_compression_publishes_nothing_and_preserves_previous_backup(): void
    {
        $this->fakeDump();
        $previous = $this->service()->backup();
        $before = $this->artifactHashes();
        $this->travel(1)->seconds();
        // Successful process with missing output injects a deterministic input-read
        // failure at the compression boundary; no permissions/root assumptions.
        $this->fakeProcesses(fn () => Process::result());

        $this->assertInstanceOf(RuntimeException::class, $this->failure(fn () => $this->service()->backup()));
        $this->assertSame($before, $this->artifactHashes(), 'No partial gzip, checksum, or raw dump may remain.');
        $this->assertSame([$previous], $this->service()->backups());
    }

    public function test_data06_published_gzip_has_matching_hash_and_private_permissions(): void
    {
        $this->fakeDump();
        $path = $this->service()->backup();

        $this->assertSame('-- synthetic SQL dump'."\n", gzdecode(file_get_contents($path)));
        $this->assertSame(hash_file('sha256', $path), strtok(file_get_contents($path.'.sha256'), ' '));
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame(0600, fileperms($path.'.sha256') & 0777);
        $this->assertSame(2, count($this->artifactHashes()), 'Only completed gzip/checksum pair is published.');
    }

    private function syntheticSqlite(): PDO
    {
        config(['database.connections.rem_backup' => ['driver' => 'sqlite',
            'database' => $this->dir.'/source.sqlite', 'prefix' => '', 'foreign_key_constraints' => true]]);
        touch($this->dir.'/source.sqlite');
        $pdo = DB::connection('rem_backup')->getPdo();
        $pdo->exec('CREATE TABLE fixture (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec("INSERT INTO fixture VALUES (1, 'snapshot')");

        return $pdo;
    }

    private function failure(callable $operation): ?Throwable
    {
        try {
            $operation();
        } catch (Throwable $error) {
            return $error;
        }

        return null;
    }

    private function artifactHashes(): array
    {
        $hashes = [];
        foreach (File::allFiles($this->dir) as $file) {
            $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);

        return $hashes;
    }
}
