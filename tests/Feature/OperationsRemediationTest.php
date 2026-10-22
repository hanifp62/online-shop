<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

/** REM-20261021 / FR-REM-006. No RefreshDatabase or operational transport. */
class OperationsRemediationTest extends TestCase
{
    public function test_smtp_failure_cannot_be_reported_as_success_by_a_log_fallback(): void
    {
        $smtp = Mockery::mock(TransportInterface::class);
        $smtp->shouldReceive('send')->once()->andThrow(new TransportException('Synthetic SMTP failure'));
        $log = Mockery::mock(TransportInterface::class);
        $log->shouldNotReceive('send');

        $manager = $this->app->make('mail.manager');
        $manager->forgetMailers();
        $manager->extend('smtp', fn () => $smtp);
        $manager->extend('log', fn () => $log);

        $this->expectException(TransportException::class);
        $manager->mailer('failover')->raw('Synthetic remediation probe', function ($message) {
            $message->from('sender@example.invalid')->to('recipient@example.invalid')->subject('Isolated probe');
        });
    }

    public function test_auth_event_pruning_is_scheduled_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'auth-events:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    public function test_storefront_cache_reclamation_is_scheduled_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'storefront-cache:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_backup_schedule_uses_url_driver_overrides_without_connecting(): void
    {
        foreach ([
            ['sqlite', 'mysql://fixture:synthetic@database.invalid/fixture', false],
            ['mysql', 'sqlite://localhost/:memory:', true],
        ] as [$driver, $url, $verify]) {
            config([
                'database.default' => 'rem_scheduler',
                'database.connections.rem_scheduler' => ['driver' => $driver, 'url' => $url],
            ]);
            $schedule = new Schedule;
            ScheduleFacade::swap($schedule);
            require base_path('routes/console.php');
            $events = collect($schedule->events())
                ->filter(fn ($event) => str_contains($event->command ?? '', 'db:backup'));

            $this->assertCount(1, $events);
            $this->assertSame($verify, str_contains($events->first()->command, ' --verify'));
        }
    }

    public function test_backup_command_passes_raw_retention_to_service_validation(): void
    {
        config(['backup.keep' => '1.5']);
        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('backup')->once()->andReturn('synthetic.sql.gz');
        $service->shouldReceive('prune')->once()->with('1.5')
            ->andThrow(new \RuntimeException('Synthetic invalid retention'));
        $this->app->instance(DatabaseBackupService::class, $service);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Synthetic invalid retention');
        Artisan::call('db:backup');
    }

    public function test_approved_database_configurations_remain_allowed_without_connecting(): void
    {
        self::validateTestDatabase(['driver' => 'sqlite', 'database' => ':memory:'], false);
        self::validateTestDatabase([
            'driver' => 'mysql',
            'database' => 'zafira_upgrade13_phpunit_20261015_0420',
        ], true);
        $this->addToAssertionCount(2);
    }

    public function test_unapproved_driver_schema_and_split_connections_are_rejected_before_connection(): void
    {
        $approved = ['driver' => 'mysql', 'database' => 'zafira_upgrade13_phpunit_20261015_0420'];
        $cases = [
            [['driver' => 'mysql', 'database' => 'production'], true],
            [['driver' => 'sqlite', 'database' => ':memory:'], true],
            [['driver' => 'mysql', 'database' => ':memory:'], false],
            [['driver' => 'sqlite', 'database' => '/tmp/shared.sqlite'], false],
            [$approved + ['read' => ['host' => 'example.invalid']], true],
            [$approved + ['write' => ['host' => 'example.invalid']], true],
        ];

        foreach ($cases as [$connection, $safeMySql]) {
            try {
                self::validateTestDatabase($connection, $safeMySql);
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);

                continue;
            }

            $this->fail('Unsafe database configuration was accepted.');
        }
    }
}
