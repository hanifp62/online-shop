<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledTasksAndEnvParityTest extends TestCase
{
    public function test_example_documents_every_literal_config_environment_key(): void
    {
        $root = dirname(__DIR__, 2);
        preg_match_all('/^\s*(?:#\s*)?([A-Z][A-Z0-9_]*)=/m', file_get_contents($root.'/.env.example'), $documented);
        $required = [];
        foreach (glob($root.'/config/*.php') as $file) {
            preg_match_all('/\benv\s*\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', file_get_contents($file), $matches);
            $required = array_merge($required, $matches[1]);
        }
        $missing = array_values(array_unique(array_diff($required, $documented[1])));
        sort($missing);
        $this->assertSame([], $missing, 'Kunci belum didokumentasikan di .env.example: '.implode(', ', $missing));
    }

    public function test_scheduled_commands_exist_and_keep_their_operational_contract(): void
    {
        $expected = [
            'carts:prune' => ['0 0 * * *', false],
            'cart-reminders:send' => ['0 * * * *', true],
            'payments:prune-dead-letters' => ['0 0 * * *', false],
            'payments:replay-dead-letters' => ['*/5 * * * *', true],
            'orders:retry-notifications' => ['*/5 * * * *', true],
            'reservations:release' => ['* * * * *', true],
            'db:backup --verify' => ['0 2 * * *', true],
        ];
        $events = app(Schedule::class)->events();
        $this->assertCount(count($expected), $events);
        $commands = Artisan::all();
        foreach ($events as $event) {
            $command = preg_replace('/^.*?artisan[\'\"]?\s+/', '', $event->command ?? '');
            $this->assertArrayHasKey($command, $expected);
            $this->assertArrayHasKey(explode(' ', $command)[0], $commands);
            $this->assertSame($expected[$command][0], $event->expression);
            $this->assertSame($expected[$command][1], $event->withoutOverlapping);
            if ($command === 'db:backup --verify') {
                $this->assertTrue($event->onOneServer);
            }
            unset($expected[$command]);
        }
        $this->assertSame([], $expected);
    }
}
