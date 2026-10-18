<?php

namespace App\Console\Commands;

use App\Models\AuthEvent;
use Illuminate\Console\Command;

class AuthEventsPrune extends Command
{
    protected $signature = 'auth-events:prune {--days=30 : Number of days of authentication events to retain}';

    protected $description = 'Prune auth events older than the specified retention window';

    public function handle(): int
    {
        $daysInput = (string) $this->option('days');
        if (! ctype_digit($daysInput) || (int) $daysInput <= 0 || (string) (int) $daysInput !== $daysInput) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $days = (int) $daysInput;
        $deleted = AuthEvent::where('occurred_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$deleted} auth events older than {$days} days.");

        return self::SUCCESS;
    }
}
