<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Cadangan & pemulihan: cadangan terjadwal menghasilkan berkas terkompresi +
 * checksum, retensi membatasi jumlah berkas, dan uji pulih membandingkan
 * jumlah baris tiap tabel dengan sumber.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/backups-test-'.bin2hex(random_bytes(4)));
        config(['backup.path' => $this->dir]);
        // Cadangan SQLite memakai VACUUM INTO, jadi uji butuh berkas nyata, bukan :memory:.
        config(['database.connections.backup_test' => [
            'driver' => 'sqlite',
            'database' => $this->dir.'/source.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        File::ensureDirectoryExists($this->dir);
        touch($this->dir.'/source.sqlite');
        $this->artisan('migrate', ['--database' => 'backup_test', '--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function service(): DatabaseBackupService
    {
        return new DatabaseBackupService('backup_test');
    }

    public function test_backup_creates_compressed_file_with_checksum(): void
    {
        $path = $this->service()->backup();

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.gz', $path);
        $this->assertFileExists($path.'.sha256');
        $this->assertSame(hash_file('sha256', $path), strtok(file_get_contents($path.'.sha256'), ' '));
        $this->assertNotFalse(gzdecode(file_get_contents($path)));
    }

    public function test_verify_restores_backup_and_matches_row_counts(): void
    {
        Category::on('backup_test')->create(['cat_title' => 'Sutra', 'cat_slug' => 'sutra-'.uniqid()]);

        $service = $this->service();
        $counts = $service->verify($service->backup());

        $this->assertArrayHasKey('categories', $counts);
        $this->assertSame(1, $counts['categories']);
    }

    public function test_verify_rejects_mysql_dump_instead_of_claiming_restore_succeeded(): void
    {
        config(['database.connections.backup_test.driver' => 'mysql']);
        $service = $this->service();
        $path = $this->dir.'/mysql.sql.gz';
        file_put_contents($path, gzencode('CREATE TABLE example (id INT);'));
        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  mysql.sql.gz'."\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verifikasi pemulihan MySQL/MariaDB belum didukung.');
        $service->verify($path);
    }

    public function test_verify_rejects_tampered_backup(): void
    {
        $service = $this->service();
        $path = $service->backup();
        file_put_contents($path, gzencode('rusak'));

        $this->expectException(RuntimeException::class);
        $service->verify($path);
    }

    public function test_verify_fails_when_source_drifts_after_backup(): void
    {
        $service = $this->service();
        $path = $service->backup();
        Category::on('backup_test')->create(['cat_title' => 'Tenun', 'cat_slug' => 'tenun-'.uniqid()]);

        $this->expectException(RuntimeException::class);
        $service->verify($path);
    }

    public function test_prune_keeps_only_the_newest_backups(): void
    {
        $service = $this->service();
        foreach (range(1, 3) as $ignored) {
            $service->backup();
        }
        $before = $service->backups();
        $this->assertCount(3, $before);

        $deleted = $service->prune(1);

        $this->assertSame(2, $deleted);
        $this->assertSame([$before[0]], $service->backups());
        $this->assertFileDoesNotExist($before[1].'.sha256');
    }

    public function test_backup_command_verifies_and_applies_retention(): void
    {
        config(['backup.keep' => 1]);
        $this->app->instance(DatabaseBackupService::class, $this->service());

        $this->artisan('db:backup --verify')->assertExitCode(0);
        $this->artisan('db:backup --verify')->assertExitCode(0);

        $this->assertCount(1, $this->service()->backups());
    }

    public function test_mysql_backup_command_keeps_backup_but_fails_verify(): void
    {
        config(['database.connections.backup_test' => [
            'driver' => 'mysql', 'host' => 'db.invalid', 'port' => 3306,
            'username' => 'backup', 'password' => '', 'database' => 'shop',
        ]]);
        // mysqldump palsu: tulis dump valid ke --result-file tanpa menyentuh server nyata.
        Process::fake(function ($process) {
            foreach ($process->command as $arg) {
                if (str_starts_with($arg, '--result-file=')) {
                    file_put_contents(substr($arg, 14), 'CREATE TABLE example (id INT);');
                }
            }

            return Process::result();
        });
        $this->app->instance(DatabaseBackupService::class, $this->service());

        try {
            $this->artisan('db:backup --verify')->run();
            $this->fail('db:backup --verify tidak boleh lulus untuk MySQL.');
        } catch (RuntimeException $e) {
            $this->assertSame('Verifikasi pemulihan MySQL/MariaDB belum didukung.', $e->getMessage());
        }

        $this->assertCount(1, $this->service()->backups());
    }

    public function test_backup_verify_command_fails_when_unsupported_driver(): void
    {
        $path = $this->dir.'/backup_test-20261021-000000-000000.sql.gz';
        file_put_contents($path, gzencode('CREATE TABLE example (id INT);'));
        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.basename($path)."\n");

        config(['database.connections.backup_test.driver' => 'mysql']);
        $this->app->instance(DatabaseBackupService::class, $this->service());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verifikasi pemulihan MySQL/MariaDB belum didukung.');
        $this->artisan('db:backup-verify', ['file' => $path]);
    }

    #[DataProvider('backupDrivers')]
    public function test_schedule_verifies_only_sqlite_and_preserves_daily_locks(string $driver, string $expectedCommand): void
    {
        $events = $this->backupEventsFor($driver);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertStringEndsWith("artisan' {$expectedCommand}", $event->command);
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    /** Muat ulang routes/console.php nyata dengan driver bawaan tertentu, tanpa koneksi DB. */
    private function backupEventsFor(string $driver): array
    {
        $default = config('database.default');
        config(['database.connections.scheduled_backup' => ['driver' => $driver], 'database.default' => 'scheduled_backup']);
        try {
            \Illuminate\Support\Facades\Schedule::swap(new Schedule);
            require base_path('routes/console.php');

            return array_values(array_filter(
                app(Schedule::class)->events(),
                fn ($event) => str_contains($event->command ?? '', 'db:backup'),
            ));
        } finally {
            // RefreshDatabase menutup transaksi pada koneksi bawaan saat tearDown.
            config(['database.default' => $default]);
        }
    }

    public function test_scheduled_mysql_backup_without_verify_applies_retention(): void
    {
        config(['backup.keep' => 1, 'database.connections.backup_test' => [
            'driver' => 'mysql', 'host' => 'db.invalid', 'port' => 3306,
            'username' => 'backup', 'password' => '', 'database' => 'shop',
        ]]);
        Process::fake(function ($process) {
            foreach ($process->command as $arg) {
                if (str_starts_with($arg, '--result-file=')) {
                    file_put_contents(substr($arg, 14), 'CREATE TABLE example (id INT);');
                }
            }

            return Process::result();
        });
        $this->app->instance(DatabaseBackupService::class, $this->service());

        $this->artisan('db:backup')->doesntExpectOutputToContain('Uji pulih lulus')->assertExitCode(0);
        $this->travel(1)->seconds();
        $this->artisan('db:backup')->assertExitCode(0);

        $this->assertCount(1, $this->service()->backups());
    }

    public static function backupDrivers(): array
    {
        return [
            ['sqlite', 'db:backup --verify'],
            ['mysql', 'db:backup'],
            ['mariadb', 'db:backup'],
        ];
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'db:backup'));

        $this->assertCount(1, $events, 'Cadangan harian harus terdaftar di scheduler');
        $this->assertSame('0 2 * * *', $events->first()->expression);
    }
}
