<?php

namespace App\Jobs;

use App\Services\ProcessingReadiness;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\RedisJob;

/** Probe sintetis tanpa model, PII, payload transaksi, atau efek bisnis. */
class RecordProcessingHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 10;

    public bool $failOnTimeout = true;

    public function __construct(public string $probeId, public int $requestedAt) {}

    public function handle(ProcessingReadiness $readiness): void
    {
        // dispatchSync()/pemanggilan handle langsung bukan bukti worker async.
        if (! ($this->job instanceof DatabaseJob || $this->job instanceof RedisJob)
            || $this->job->getConnectionName() !== $this->connection
            || $this->job->getQueue() !== $this->queue) {
            return;
        }

        $readiness->recordWorkerHeartbeat($this->probeId, $this->requestedAt);
    }
}
